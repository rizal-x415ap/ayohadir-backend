<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\GuestGroup;
use App\Models\Template;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchGuestAndPublicSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_batch_store_guests(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);
        $group = GuestGroup::create(['wedding_id' => $wedding->id, 'name' => 'Keluarga']);

        $payload = [
            'guest_group_id' => $group->id,
            'default_max_attendees' => 2,
            'guests' => [
                ['name' => 'Budi Santoso', 'phone' => '081234567890', 'max_attendees' => 2],
                ['name' => 'Siti Rahmawati', 'phone' => '08987654321', 'max_attendees' => 1],
                ['name' => 'Keluarga Ahmad', 'phone' => '082111223344', 'max_attendees' => 4],
            ],
        ];

        $response = $this->actingAs($user)->postJson("/api/v1/weddings/{$wedding->id}/guests/batch", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('count', 3);

        $this->assertDatabaseHas('guests', [
            'wedding_id' => $wedding->id,
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
        ]);

        $this->assertDatabaseHas('guests', [
            'wedding_id' => $wedding->id,
            'name' => 'Siti Rahmawati',
            'phone' => '08987654321',
        ]);

        $this->assertDatabaseHas('guests', [
            'wedding_id' => $wedding->id,
            'name' => 'Keluarga Ahmad',
            'max_attendees' => 4,
        ]);

        // Verify invitations were created with unique tokens
        $budi = Guest::where('wedding_id', $wedding->id)->where('name', 'Budi Santoso')->first();
        $this->assertNotNull($budi->invitation);
        $this->assertNotEmpty($budi->invitation->token);
    }

    public function test_owner_can_mark_whatsapp_sent(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);
        $guest = Guest::create([
            'wedding_id' => $wedding->id,
            'name' => 'Budi Pratama',
            'phone' => '081234567890',
            'max_attendees' => 2,
        ]);
        $guest->invitation()->create([
            'wedding_id' => $wedding->id,
            'token' => 'tok123456',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/v1/weddings/{$wedding->id}/guests/{$guest->id}/mark-wa-sent");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Status pengiriman WhatsApp berhasil diperbarui.');

        $guest->invitation->refresh();
        $this->assertNotNull($guest->invitation->whatsapp_sent_at);
    }

    public function test_owner_can_get_and_save_whatsapp_template(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        // Default template
        $getRes = $this->actingAs($user)->getJson("/api/v1/weddings/{$wedding->id}/whatsapp-template");
        $getRes->assertStatus(200)
            ->assertJsonStructure(['template']);

        // Custom template
        $customTemplate = "Halo {nama_tamu}, kami mengundang Anda ke pernikahan {nama_pengantin}. Tautan: {link_undangan}";
        $saveRes = $this->actingAs($user)->postJson("/api/v1/weddings/{$wedding->id}/whatsapp-template", [
            'template' => $customTemplate,
        ]);

        $saveRes->assertStatus(200)
            ->assertJsonPath('template', $customTemplate);

        $wedding->refresh();
        $this->assertEquals($customTemplate, $wedding->custom_content['whatsapp_template']);
    }

    public function test_public_invitation_resolves_with_slug(): void
    {
        $template = Template::create([
            'name' => 'Rustic Elegance',
            'slug' => 'rustic-elegance',
            'category' => 'botanical',
            'is_active' => true,
            'price' => 0,
            'schema_version' => 1,
            'schema' => ['schemaVersion' => 1, 'sections' => []],
            'contract' => ['sections' => []],
        ]);

        $user = User::factory()->create();
        $wedding = Wedding::create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'title' => 'Romeo & Juliet Wedding',
            'bride_name' => 'Juliet',
            'groom_name' => 'Romeo',
            'wedding_date' => '2026-10-20',
            'slug' => 'romeo-juliet-special',
            'status' => 'published',
            'is_unlocked' => true,
            'theme_config' => ['palette' => 'emerald', 'font' => 'serif'],
        ]);

        $wedding->design()->create([
            'schema' => ['schemaVersion' => 1, 'sections' => []],
            'published_schema' => [
                'wedding' => [
                    'id' => $wedding->id,
                    'title' => 'Romeo & Juliet Wedding',
                    'slug' => 'romeo-juliet-special',
                ],
                'design' => [
                    'schema' => ['schemaVersion' => 1, 'sections' => []],
                ],
            ],
            'published_at' => now(),
        ]);

        $guest = Guest::create([
            'wedding_id' => $wedding->id,
            'name' => 'Sahabat Romeo',
            'max_attendees' => 2,
        ]);

        $guest->invitation()->create([
            'wedding_id' => $wedding->id,
            'token' => 'tkn-guest-special',
        ]);

        $response = $this->getJson("/api/v1/public/invitations/romeo-juliet-special?guest=tkn-guest-special");

        $response->assertStatus(200)
            ->assertJsonPath('data.wedding.slug', 'romeo-juliet-special')
            ->assertJsonPath('data.guest.name', 'Sahabat Romeo');
    }

    public function test_invitation_token_is_compact_four_characters_and_resolves_correctly(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'adam-hawa',
            'status' => 'published',
        ]);

        // 1. Test model token generation generates exactly 4 chars
        $token = \App\Models\Invitation::generateUniqueToken();
        $this->assertSame(4, strlen($token));

        // 2. Create guest through API
        $response = $this->actingAs($user)->postJson("/api/v1/weddings/{$wedding->id}/guests", [
            'name' => 'Tamu Terhormat',
            'max_attendees' => 2,
        ]);

        $response->assertStatus(201);
        $guestToken = $response->json('data.invitationToken');
        $this->assertNotNull($guestToken);
        $this->assertSame(4, strlen($guestToken));

        // 3. Verify invitation URL includes guest name parameter ?to=
        $invitationUrl = $response->json('data.invitationUrl');
        $this->assertStringContainsString("?to=Tamu+Terhormat", $invitationUrl);

        // 4. Verify batch creation also generates 4-character tokens
        $batchResponse = $this->actingAs($user)->postJson("/api/v1/weddings/{$wedding->id}/guests/batch", [
            'guests' => [
                ['name' => 'Tamu Batch 1', 'max_attendees' => 2],
                ['name' => 'Tamu Batch 2', 'max_attendees' => 3],
            ],
        ]);
        $batchResponse->assertStatus(201);
        $batchGuests = $batchResponse->json('data');
        foreach ($batchGuests as $bg) {
            $this->assertSame(4, strlen($bg['invitationToken']));
        }
    }

    public function test_public_og_endpoints_resolve_metadata_with_couple_photo_and_guest_name(): void
    {
        $user = User::factory()->create();
        $template = Template::create([
            'name' => 'Royal Emerald',
            'slug' => 'royal-emerald',
            'category' => 'luxury',
            'description' => 'Tema mewah royal emerald.',
            'thumbnail' => 'https://example.com/royal.jpg',
            'schema_version' => 1,
            'contract' => ['slots' => []],
            'schema' => [
                'theme' => ['colors' => ['primary' => '#03AC0E']],
                'desktopCover' => [
                    'elements' => [
                        [
                            'bindingKey' => 'couple.couplePhotoUrl',
                            'props' => ['url' => 'https://images.unsplash.com/couple-sample.jpg'],
                        ],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'applied_template_id' => $template->id,
            'slug' => 'budi-ani',
            'bride_name' => 'Ani',
            'groom_name' => 'Budi',
            'status' => 'published',
            'custom_content' => [
                'couple.couplePhotoUrl' => 'https://images.unsplash.com/budi-ani-couple.jpg',
            ],
        ]);

        $guest = Guest::create([
            'wedding_id' => $wedding->id,
            'name' => 'Bpk. Hendra Wijaya',
            'max_attendees' => 2,
        ]);
        $guest->invitation()->create([
            'wedding_id' => $wedding->id,
            'token' => 't123',
        ]);

        // 1. Test template OG endpoint
        $resTemplate = $this->getJson("/api/v1/public/og/template/royal-emerald");
        $resTemplate->assertStatus(200)
            ->assertJsonPath('data.title', 'Template Undangan: Royal Emerald — Ayo Hadir')
            ->assertJsonPath('data.image', 'https://images.unsplash.com/couple-sample.jpg')
            ->assertJsonPath('data.themeColor', '#03AC0E');

        // 2. Test wedding OG endpoint without guest
        $resWedding = $this->getJson("/api/v1/public/og/budi-ani");
        $resWedding->assertStatus(200)
            ->assertJsonPath('data.title', 'The Wedding of Ani & Budi — Ayo Hadir')
            ->assertJsonPath('data.image', 'https://images.unsplash.com/budi-ani-couple.jpg');

        // 3. Test wedding OG endpoint with guest ?to=
        $resWeddingGuest = $this->getJson("/api/v1/public/og/budi-ani?to=" . urlencode('Bpk. Hendra Wijaya'));
        $resWeddingGuest->assertStatus(200)
            ->assertJsonPath('data.title', 'Undangan Pernikahan untuk Bpk. Hendra Wijaya — Ani & Budi')
            ->assertJsonPath('data.guestName', 'Bpk. Hendra Wijaya');

        // 4. Test public invitation resolves registered guest by name ?to=
        $resInv = $this->getJson("/api/v1/public/invitations/budi-ani?to=" . urlencode('Bpk. Hendra Wijaya'));
        $resInv->assertStatus(200)
            ->assertJsonPath('data.guest.name', 'Bpk. Hendra Wijaya')
            ->assertJsonPath('data.guest.isRegistered', true);

        // Verify open count incremented
        $this->assertEquals(1, $guest->invitation->fresh()->open_count);
    }
}
