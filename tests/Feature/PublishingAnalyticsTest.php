<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\PageView;
use App\Models\Rsvp;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublishingAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_analytics_aggregates(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'budi-rina-analytics',
            'status' => 'published',
        ]);

        // Create mock page views
        PageView::create([
            'wedding_id' => $wedding->id,
            'session_hash' => 'hash1',
            'source' => 'direct',
            'created_at' => now(),
        ]);
        PageView::create([
            'wedding_id' => $wedding->id,
            'session_hash' => 'hash2',
            'source' => 'guest_token',
            'created_at' => now(),
        ]);

        // Create guests & rsvp
        $g1 = Guest::create(['wedding_id' => $wedding->id, 'name' => 'Guest 1', 'max_attendees' => 2]);
        $inv1 = Invitation::create(['wedding_id' => $wedding->id, 'guest_id' => $g1->id, 'token' => 'tok1', 'open_count' => 3]);
        Rsvp::create([
            'wedding_id' => $wedding->id,
            'invitation_id' => $inv1->id,
            'guest_id' => $g1->id,
            'attending' => true,
            'attendee_count' => 2,
            'responded_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/weddings/{$wedding->id}/analytics");

        $response->assertStatus(200)
            ->assertJsonPath('data.overview.totalViews', 2)
            ->assertJsonPath('data.overview.uniqueVisitors', 2)
            ->assertJsonPath('data.overview.totalGuests', 1)
            ->assertJsonPath('data.overview.confirmed', 1)
            ->assertJsonPath('data.overview.totalAttendees', 2)
            ->assertJsonPath('data.overview.attendanceRate', 100)
            ->assertJsonPath('data.publishing.status', 'published');
    }

    public function test_stranger_cannot_access_analytics_idor(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($stranger)->getJson("/api/v1/weddings/{$wedding->id}/analytics");

        $response->assertStatus(403);
    }

    public function test_public_invitation_view_records_anonymized_page_view(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'live-view-wedding',
            'status' => 'published',
        ]);
        $wedding->design()->create([
            'schema_version' => 1,
            'schema' => ['sections' => []],
            'published_schema' => ['sections' => []],
            'version' => 1,
        ]);

        $this->getJson('/api/v1/public/invitations/live-view-wedding');

        $this->assertDatabaseCount('page_views', 1);
        $this->assertDatabaseHas('page_views', [
            'wedding_id' => $wedding->id,
            'source' => 'direct',
        ]);
    }

    public function test_publish_and_unpublish_invalidates_cache(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create([
            'user_id' => $user->id,
            'slug' => 'cache-test-wedding',
            'status' => 'draft',
            'bride_name' => 'Rina',
            'groom_name' => 'Budi',
            'wedding_date' => '2026-10-10',
            'venue_name' => 'Grand Hotel',
        ]);

        Cache::put('public:wedding:cache-test-wedding', ['cached' => true]);

        // Publish wedding
        $this->actingAs($user)->postJson("/api/v1/weddings/{$wedding->id}/publish");

        // Verify cache was refreshed with new published snapshot (not stale mock)
        $cached = Cache::get('public:wedding:cache-test-wedding');
        $this->assertNotNull($cached);
        $this->assertEquals('published', $cached['wedding']['status']);

        // Unpublish wedding
        $this->actingAs($user)->postJson("/api/v1/weddings/{$wedding->id}/unpublish");

        // Verify cache was cleared during unpublish
        $this->assertNull(Cache::get('public:wedding:cache-test-wedding'));
    }
}
