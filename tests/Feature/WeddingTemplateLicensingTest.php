<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeddingTemplateLicensingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Template $freeTemplate;
    protected Template $paidTemplateA;
    protected Template $paidTemplateB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->freeTemplate = Template::create([
            'name' => 'Free Template',
            'slug' => 'free-template',
            'price' => 0,
            'is_active' => true,
            'schema_version' => 1,
            'schema' => ['sections' => [['id' => 'cover', 'name' => 'Cover', 'type' => 'cover']]],
            'contract' => ['sections' => []],
        ]);

        $this->paidTemplateA = Template::create([
            'name' => 'Paid Template Alpha',
            'slug' => 'paid-template-alpha',
            'price' => 50000,
            'is_active' => true,
            'schema_version' => 1,
            'schema' => ['sections' => [['id' => 'cover', 'name' => 'Cover', 'type' => 'cover']]],
            'contract' => ['sections' => []],
        ]);

        $this->paidTemplateB = Template::create([
            'name' => 'Paid Template Beta',
            'slug' => 'paid-template-beta',
            'price' => 75000,
            'is_active' => true,
            'schema_version' => 1,
            'schema' => ['sections' => [['id' => 'cover', 'name' => 'Cover', 'type' => 'cover']]],
            'contract' => ['sections' => []],
        ]);
    }

    public function test_template_purchases_are_isolated_per_wedding_and_per_template(): void
    {
        // 1. Create Wedding 1 and Wedding 2 for same user
        $wedding1 = Wedding::create([
            'user_id' => $this->user->id,
            'slug' => 'wedding-satu',
            'bride_name' => 'Rina',
            'groom_name' => 'Budi',
            'wedding_date' => '2026-10-10',
            'venue_name' => 'Ballroom A',
            'applied_template_id' => $this->paidTemplateA->id,
            'status' => 'draft',
        ]);

        $wedding2 = Wedding::create([
            'user_id' => $this->user->id,
            'slug' => 'wedding-dua',
            'bride_name' => 'Siti',
            'groom_name' => 'Agus',
            'wedding_date' => '2026-11-11',
            'venue_name' => 'Ballroom B',
            'applied_template_id' => $this->paidTemplateA->id,
            'status' => 'draft',
        ]);

        // Both initially require payment for Paid Template Alpha
        $this->assertFalse($wedding1->isTemplateUnlocked($this->paidTemplateA, $this->user));
        $this->assertFalse($wedding2->isTemplateUnlocked($this->paidTemplateA, $this->user));

        // Unlock Paid Template Alpha ONLY for Wedding 1
        $wedding1->unlockTemplate($this->paidTemplateA);

        // Assert Wedding 1 is unlocked for Template Alpha
        $this->assertTrue($wedding1->fresh()->isTemplateUnlocked($this->paidTemplateA, $this->user));

        // CRITICAL: Assert Wedding 2 STILL requires payment for Template Alpha
        $this->assertFalse($wedding2->fresh()->isTemplateUnlocked($this->paidTemplateA, $this->user));

        // Validating Wedding 2 via API reports requiresPayment = true
        $resWedding2 = $this->actingAs($this->user)
            ->getJson("/api/v1/weddings/{$wedding2->id}/publish/validate");

        $resWedding2->assertOk()
            ->assertJsonPath('data.paymentInfo.requiresPayment', true)
            ->assertJsonPath('data.paymentInfo.isUnlocked', false);
    }

    public function test_switching_to_unpaid_template_requires_payment_and_resets_published_to_draft(): void
    {
        // 1. Create wedding and unlock Template Alpha
        $wedding = Wedding::create([
            'user_id' => $this->user->id,
            'slug' => 'wedding-alpha',
            'bride_name' => 'Rina',
            'groom_name' => 'Budi',
            'wedding_date' => '2026-10-10',
            'venue_name' => 'Ballroom A',
            'applied_template_id' => $this->paidTemplateA->id,
            'status' => 'draft',
        ]);

        $wedding->unlockTemplate($this->paidTemplateA);

        // 2. Publish wedding with Template Alpha
        $pubRes = $this->actingAs($this->user)
            ->postJson("/api/v1/weddings/{$wedding->id}/publish");
        $pubRes->assertOk();
        $this->assertEquals('published', $wedding->fresh()->status);

        // 3. User switches to Paid Template Beta
        $switchRes = $this->actingAs($this->user)
            ->postJson("/api/v1/weddings/{$wedding->id}/apply-template/{$this->paidTemplateB->id}");

        $switchRes->assertOk();
        $weddingFresh = $wedding->fresh();

        // 4. Status must be automatically reverted to draft!
        $this->assertEquals('draft', $weddingFresh->status);

        // 5. Template Beta requires payment because it was never unlocked for this wedding
        $this->assertFalse($weddingFresh->isTemplateUnlocked($this->paidTemplateB, $this->user));
        $this->assertFalse((bool) $weddingFresh->is_premium_unlocked);

        // 6. Pre-publish validation must show requiresPayment = true
        $valRes = $this->actingAs($this->user)
            ->getJson("/api/v1/weddings/{$wedding->id}/publish/validate");

        $valRes->assertOk()
            ->assertJsonPath('data.paymentInfo.requiresPayment', true)
            ->assertJsonPath('data.paymentInfo.isUnlocked', false)
            ->assertJsonPath('data.paymentInfo.templateId', $this->paidTemplateB->id);
    }

    public function test_switching_back_to_previously_paid_template_does_not_require_payment_again(): void
    {
        $wedding = Wedding::create([
            'user_id' => $this->user->id,
            'slug' => 'wedding-reuse',
            'bride_name' => 'Rina',
            'groom_name' => 'Budi',
            'wedding_date' => '2026-10-10',
            'venue_name' => 'Ballroom A',
            'applied_template_id' => $this->paidTemplateA->id,
            'status' => 'draft',
        ]);

        // Purchase Template Alpha
        $wedding->unlockTemplate($this->paidTemplateA);

        // Switch to Template Beta (unpaid)
        $this->actingAs($this->user)
            ->postJson("/api/v1/weddings/{$wedding->id}/apply-template/{$this->paidTemplateB->id}");

        $this->assertFalse($wedding->fresh()->isTemplateUnlocked($this->paidTemplateB, $this->user));

        // Switch back to Template Alpha
        $this->actingAs($this->user)
            ->postJson("/api/v1/weddings/{$wedding->id}/apply-template/{$this->paidTemplateA->id}");

        // Assert Template Alpha is still unlocked without paying again!
        $weddingFresh = $wedding->fresh();
        $this->assertTrue($weddingFresh->isTemplateUnlocked($this->paidTemplateA, $this->user));
        $this->assertTrue((bool) $weddingFresh->is_premium_unlocked);

        // API validation confirms requiresPayment is false
        $valRes = $this->actingAs($this->user)
            ->getJson("/api/v1/weddings/{$wedding->id}/publish/validate");

        $valRes->assertOk()
            ->assertJsonPath('data.paymentInfo.requiresPayment', false)
            ->assertJsonPath('data.paymentInfo.isUnlocked', true);
    }

    public function test_both_get_and_post_validate_endpoints_work(): void
    {
        $wedding = Wedding::create([
            'user_id' => $this->user->id,
            'slug' => 'wedding-routes',
            'bride_name' => 'Rina',
            'groom_name' => 'Budi',
            'wedding_date' => '2026-10-10',
            'venue_name' => 'Ballroom A',
            'applied_template_id' => $this->paidTemplateA->id,
            'status' => 'draft',
        ]);

        // 1. GET /weddings/{id}/publish/validate
        $res1 = $this->actingAs($this->user)
            ->getJson("/api/v1/weddings/{$wedding->id}/publish/validate");
        $res1->assertOk()
            ->assertJsonPath('data.canPublish', true)
            ->assertJsonPath('data.paymentInfo.requiresPayment', true);

        // 2. POST /weddings/{id}/validate
        $res2 = $this->actingAs($this->user)
            ->postJson("/api/v1/weddings/{$wedding->id}/validate");
        $res2->assertOk()
            ->assertJsonPath('data.canPublish', true)
            ->assertJsonPath('data.paymentInfo.requiresPayment', true);

        // 3. GET /weddings/{id}/validate
        $res3 = $this->actingAs($this->user)
            ->getJson("/api/v1/weddings/{$wedding->id}/validate");
        $res3->assertOk()
            ->assertJsonPath('data.canPublish', true);
    }
}
