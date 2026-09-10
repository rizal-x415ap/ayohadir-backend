<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_settings_returns_phone_images_array(): void
    {
        AppSetting::set('landing_hero_phone_image', 'https://example.com/mockup1.png');
        AppSetting::set('landing_hero_phone_image_2', 'https://example.com/mockup2.png');
        AppSetting::set('landing_hero_phone_image_3', 'https://example.com/mockup3.png');

        $response = $this->getJson('/api/v1/public/settings');

        $response->assertOk()
            ->assertJsonPath('data.hero.phoneImage', 'https://example.com/mockup1.png')
            ->assertJsonPath('data.hero.phoneImages.0', 'https://example.com/mockup1.png')
            ->assertJsonPath('data.hero.phoneImages.1', 'https://example.com/mockup2.png')
            ->assertJsonPath('data.hero.phoneImages.2', 'https://example.com/mockup3.png');
    }

    public function test_admin_can_update_and_fetch_three_hero_slots(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $payload = [
            'hero' => [
                'phone_image' => 'https://example.com/slot1.png',
                'phone_image_2' => 'https://example.com/slot2.png',
                'phone_image_3' => 'https://example.com/slot3.png',
                'phone_link' => 'https://example.com/link',
            ],
        ];

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/landing-settings', $payload);

        $updateResponse->assertOk();

        $getResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/landing-settings');

        $getResponse->assertOk()
            ->assertJsonPath('data.hero.phone_image', 'https://example.com/slot1.png')
            ->assertJsonPath('data.hero.phone_image_2', 'https://example.com/slot2.png')
            ->assertJsonPath('data.hero.phone_image_3', 'https://example.com/slot3.png')
            ->assertJsonPath('data.hero.phone_images.0', 'https://example.com/slot1.png')
            ->assertJsonPath('data.hero.phone_images.1', 'https://example.com/slot2.png')
            ->assertJsonPath('data.hero.phone_images.2', 'https://example.com/slot3.png');
    }

    public function test_public_settings_returns_contact_details_and_admin_can_update(): void
    {
        $response = $this->getJson('/api/v1/public/settings');
        $response->assertOk()
            ->assertJsonPath('data.contact.email', 'support@ayohadir.id')
            ->assertJsonPath('data.contact.phoneNumber', '085156476048')
            ->assertJsonPath('data.contact.address', 'Pematang Sidamanik, Kab.Simalungung, Sumatera Utara');

        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/landing-settings', [
                'links' => [
                    'email' => 'help@ayohadir.id',
                    'phone_number' => '081234567890',
                    'address' => 'Kota Medan, Sumatera Utara',
                ],
            ]);

        $updateResponse->assertOk();

        $updatedPublicResponse = $this->getJson('/api/v1/public/settings');
        $updatedPublicResponse->assertOk()
            ->assertJsonPath('data.contact.email', 'help@ayohadir.id')
            ->assertJsonPath('data.contact.phoneNumber', '081234567890')
            ->assertJsonPath('data.contact.address', 'Kota Medan, Sumatera Utara');
    }
}
