<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected Wedding $userWedding;
    protected Template $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->regularUser = User::factory()->create(['role' => 'user']);

        $this->template = Template::create([
            'name' => 'Royal Emerald Luxury',
            'slug' => 'royal-emerald-luxury',
            'category' => 'luxury',
            'price' => 125000,
            'tier' => 'premium',
            'schema_version' => 1,
            'schema' => ['schemaVersion' => 1, 'sections' => []],
            'contract' => ['sections' => []],
            'is_active' => true,
        ]);

        $this->userWedding = Wedding::create([
            'user_id' => $this->regularUser->id,
            'slug' => 'ali-fatimah',
            'bride_name' => 'Fatimah',
            'groom_name' => 'Ali',
            'wedding_date' => now()->addDays(45)->toDateString(),
            'applied_template_id' => $this->template->id,
            'status' => 'draft',
            'is_premium_unlocked' => false,
        ]);
    }

    public function test_non_admin_cannot_access_admin_weddings_and_users(): void
    {
        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/admin/weddings')
            ->assertStatus(403);

        $this->actingAs($this->regularUser)
            ->getJson('/api/v1/admin/users')
            ->assertStatus(403);
    }

    public function test_admin_can_fetch_all_weddings_and_statistics(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/weddings');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $this->userWedding->id)
            ->assertJsonPath('data.0.bride_name', 'Fatimah')
            ->assertJsonPath('data.0.user.email', $this->regularUser->email);

        $stats = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/weddings/statistics');

        $stats->assertOk()
            ->assertJsonPath('total_weddings', 1)
            ->assertJsonPath('draft_weddings', 1);
    }

    public function test_admin_can_update_any_wedding_metadata(): void
    {
        $payload = [
            'bride_name' => 'Fatimah Zahra',
            'groom_name' => 'Ali bin Abi Thalib',
            'wedding_date' => '2026-11-20',
            'wedding_time' => '08:00 - 13:00',
            'venue_name' => 'Masjid Raya',
            'venue_address' => 'Jl. Pahlawan No. 1',
            'slug' => 'ali-dan-fatimah-zahra',
            'status' => 'published',
            'is_premium_unlocked' => true,
            'rsvp_enabled' => true,
            'wishes_enabled' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/weddings/{$this->userWedding->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('data.bride_name', 'Fatimah Zahra')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.is_premium_unlocked', true);

        $this->userWedding->refresh();
        $this->assertEquals('Fatimah Zahra', $this->userWedding->bride_name);
        $this->assertEquals('published', $this->userWedding->status);
        $this->assertTrue($this->userWedding->is_premium_unlocked);
    }

    public function test_admin_can_toggle_publish_and_premium(): void
    {
        // Toggle publish: draft -> published
        $resPub = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/weddings/{$this->userWedding->id}/toggle-publish");

        $resPub->assertOk()
            ->assertJsonPath('status', 'published');

        // Toggle publish: published -> draft
        $resDraft = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/weddings/{$this->userWedding->id}/toggle-publish");

        $resDraft->assertOk()
            ->assertJsonPath('status', 'draft');

        // Toggle premium: false -> true
        $resPrem = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/weddings/{$this->userWedding->id}/toggle-premium");

        $resPrem->assertOk()
            ->assertJsonPath('is_premium_unlocked', true);
    }

    public function test_admin_can_delete_wedding(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/weddings/{$this->userWedding->id}");

        $response->assertOk();
        $this->assertSoftDeleted('weddings', ['id' => $this->userWedding->id]);
    }

    public function test_admin_can_manage_users_crud(): void
    {
        // 1. List users
        $resList = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/users');

        $resList->assertOk()
            ->assertJsonCount(2, 'data'); // admin + regularUser

        // 2. Create new user
        $resCreate = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Budi Santoso',
                'email' => 'budi@example.com',
                'password' => 'secret12345',
                'role' => 'user',
            ]);

        $resCreate->assertStatus(201)
            ->assertJsonPath('data.name', 'Budi Santoso');

        $newUserId = $resCreate->json('data.id');

        // 3. Update user
        $resUpdate = $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/users/{$newUserId}", [
                'name' => 'Budi Santoso Updated',
                'email' => 'budi.new@example.com',
                'role' => 'admin',
                'password' => 'newpassword123',
            ]);

        $resUpdate->assertOk()
            ->assertJsonPath('data.name', 'Budi Santoso Updated')
            ->assertJsonPath('data.role', 'admin');

        // 4. Delete user
        $resDelete = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/users/{$newUserId}");

        $resDelete->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $newUserId]);

        // 5. Admin cannot delete self
        $resSelf = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/users/{$this->admin->id}");

        $resSelf->assertStatus(422);
    }
}
