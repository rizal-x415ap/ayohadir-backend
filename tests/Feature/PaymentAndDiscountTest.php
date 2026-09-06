<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Coupon;
use App\Models\Design;
use App\Models\PaymentTransaction;
use App\Models\Template;
use App\Models\User;
use App\Models\Wedding;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAndDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected Template $template;
    protected Wedding $wedding;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'user']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->template = Template::create([
            'name' => 'Rustic Floral Luxury',
            'slug' => 'rustic-floral-luxury',
            'category' => 'Floral',
            'price' => 100000,
            'tier' => 'premium',
            'schema_version' => 1,
            'schema' => ['schemaVersion' => 1, 'sections' => []],
            'contract' => ['sections' => []],
            'is_active' => true,
        ]);

        $this->wedding = Wedding::create([
            'user_id' => $this->user->id,
            'slug' => 'romeo-juliet',
            'bride_name' => 'Juliet',
            'groom_name' => 'Romeo',
            'wedding_date' => now()->addDays(30)->toDateString(),
            'applied_template_id' => $this->template->id,
            'status' => 'draft',
            'is_premium_unlocked' => false,
        ]);

        Design::create([
            'wedding_id' => $this->wedding->id,
            'template_id' => $this->template->id,
            'schema_version' => 1,
            'draft_schema' => ['sections' => []],
        ]);
    }

    public function test_pricing_service_calculates_stackable_global_and_coupon_discounts(): void
    {
        // 1. Enable Global Discount: 20%
        AppSetting::set('discount_global_enabled', '1', 'discount');
        AppSetting::set('discount_global_type', 'percentage', 'discount');
        AppSetting::set('discount_global_value', '20', 'discount');
        AppSetting::set('discount_global_title', 'Promo Peluncuran 20%', 'discount');

        // 2. Create Coupon: Flat Rp 10.000
        $coupon = Coupon::create([
            'code' => 'HEMAT10',
            'title' => 'Voucher Tambahan 10rb',
            'type' => 'fixed',
            'value' => 10000,
            'is_active' => true,
        ]);

        $pricingService = new PricingService();
        $breakdown = $pricingService->calculate(100000, 'HEMAT10');

        // Base price: 100.000
        // Global 20%: 20.000 (remaining: 80.000)
        // Coupon: 10.000
        // Final: 70.000
        $this->assertEquals(100000, $breakdown['base_price']);
        $this->assertEquals(20000, $breakdown['global_discount']['amount']);
        $this->assertEquals(10000, $breakdown['coupon']['amount']);
        $this->assertEquals(30000, $breakdown['total_discount']);
        $this->assertEquals(70000, $breakdown['final_amount']);
        $this->assertFalse($breakdown['is_free']);
    }

    public function test_coupon_validation_endpoint(): void
    {
        Coupon::create([
            'code' => 'SUPER50',
            'title' => 'Diskon 50%',
            'type' => 'percentage',
            'value' => 50,
            'min_spend' => 50000,
            'is_active' => true,
        ]);

        // Valid code
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/coupons/validate', [
                'code' => 'SUPER50',
                'amount' => 100000,
            ]);

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('coupon.discount_amount', 50000);

        // Invalid minimum spend
        $responseMin = $this->actingAs($this->user)
            ->postJson('/api/v1/coupons/validate', [
                'code' => 'SUPER50',
                'amount' => 20000,
            ]);

        $responseMin->assertStatus(422)
            ->assertJsonPath('valid', false);
    }

    public function test_admin_can_manage_duitku_and_global_discount_settings(): void
    {
        $payload = [
            'duitku' => [
                'merchant_code' => 'D99999',
                'api_key' => 'secret_test_key_12345',
                'environment' => 'sandbox',
            ],
            'global_discount' => [
                'enabled' => true,
                'type' => 'fixed',
                'value' => 25000,
                'title' => 'Potongan Langsung 25rb',
            ],
        ];

        $res = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/finance/settings', $payload);

        $res->assertOk();

        $getRes = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/finance/settings');

        $getRes->assertOk()
            ->assertJsonPath('duitku.merchant_code', 'D99999')
            ->assertJsonPath('global_discount.enabled', true)
            ->assertJsonPath('global_discount.value', 25000);
    }

    public function test_checkout_unlocks_immediately_when_total_discount_is_100_percent(): void
    {
        // 100% discount coupon
        Coupon::create([
            'code' => 'GRATIS100',
            'title' => 'Gratis 100%',
            'type' => 'percentage',
            'value' => 100,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/weddings/{$this->wedding->id}/checkout", [
                'coupon_code' => 'GRATIS100',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_free', true);

        $this->wedding->refresh();
        $this->assertTrue($this->wedding->is_premium_unlocked);

        $this->assertDatabaseHas('payment_transactions', [
            'wedding_id' => $this->wedding->id,
            'status' => 'paid',
            'amount' => 0,
            'coupon_code' => 'GRATIS100',
        ]);
    }

    public function test_duitku_callback_webhook_verifies_signature_and_unlocks_wedding(): void
    {
        $merchantCode = 'DTEST123';
        $apiKey = 'test_api_key_xyz';

        AppSetting::set('duitku_merchant_code', $merchantCode, 'payment');
        AppSetting::set('duitku_api_key', $apiKey, 'payment');

        $orderId = 'AYO-TEST-ORDER-001';
        $amount = 80000;

        PaymentTransaction::create([
            'merchant_order_id' => $orderId,
            'user_id' => $this->user->id,
            'wedding_id' => $this->wedding->id,
            'template_id' => $this->template->id,
            'base_price' => 100000,
            'amount' => $amount,
            'status' => 'pending',
        ]);

        // Signature SHA256 HMAC for callback: hash_hmac('sha256', merchantCode + amount + merchantOrderId, apiKey)
        $signature = hash_hmac('sha256', $merchantCode . $amount . $orderId, $apiKey);

        $callbackPayload = [
            'merchantCode' => $merchantCode,
            'amount' => (string) $amount,
            'merchantOrderId' => $orderId,
            'signature' => $signature,
            'resultCode' => '00',
            'reference' => 'REF-DUITKU-12345',
            'paymentCode' => 'QRIS',
        ];

        $response = $this->post('/api/v1/payment/duitku/callback', $callbackPayload);

        $response->assertStatus(200);

        $this->wedding->refresh();
        $this->assertTrue($this->wedding->is_premium_unlocked);

        $this->assertDatabaseHas('payment_transactions', [
            'merchant_order_id' => $orderId,
            'status' => 'paid',
            'payment_method' => 'QRIS',
            'duitku_reference' => 'REF-DUITKU-12345',
        ]);
    }

    public function test_template_resource_calculates_discounts_and_strikethrough_price(): void
    {
        // 1. Without discount
        $res = $this->actingAs($this->user)->getJson('/api/v1/templates');
        $res->assertStatus(200);
        $tplData = collect($res->json('data'))->firstWhere('id', $this->template->id);
        $this->assertEquals(100000, $tplData['price']);
        $this->assertFalse($tplData['hasDiscount']);
        $this->assertNull($tplData['originalPrice']);

        // 2. With Global Discount 25%
        AppSetting::set('discount_global_enabled', '1', 'discount');
        AppSetting::set('discount_global_type', 'percentage', 'discount');
        AppSetting::set('discount_global_value', '25', 'discount');
        AppSetting::set('discount_global_title', 'Promo Super 25%', 'discount');

        $resWithGlobal = $this->actingAs($this->user)->getJson('/api/v1/templates');
        $resWithGlobal->assertStatus(200);
        $tplDiscounted = collect($resWithGlobal->json('data'))->firstWhere('id', $this->template->id);
        $this->assertTrue($tplDiscounted['hasDiscount']);
        $this->assertEquals(75000, $tplDiscounted['price']);
        $this->assertEquals(100000, $tplDiscounted['originalPrice']);
        $this->assertEquals('Rp 75.000', $tplDiscounted['formattedPrice']);
        $this->assertEquals('Rp 100.000', $tplDiscounted['formattedOriginalPrice']);
        $this->assertEquals(25, $tplDiscounted['discountPercentage']);

        // 3. With manual original_price higher than base price
        $this->template->update(['original_price' => 150000]);
        $resWithManual = $this->actingAs($this->user)->getJson('/api/v1/templates');
        $tplManual = collect($resWithManual->json('data'))->firstWhere('id', $this->template->id);
        $this->assertTrue($tplManual['hasDiscount']);
        $this->assertEquals(150000, $tplManual['originalPrice']);
        $this->assertEquals(75000, $tplManual['price']);
        $this->assertEquals(50, $tplManual['discountPercentage']); // (150k - 75k) / 150k = 50%
    }

    public function test_admin_can_update_template_original_price(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/api/v1/templates/{$this->template->id}", [
            'name' => 'Rustic Floral Updated',
            'slug' => 'rustic-floral-updated',
            'category' => 'Floral',
            'price' => 99000,
            'original_price' => 149000,
        ]);

        $response->assertStatus(200);
        $this->template->refresh();
        $this->assertEquals(99000, $this->template->price);
        $this->assertEquals(149000, $this->template->original_price);
    }
}
