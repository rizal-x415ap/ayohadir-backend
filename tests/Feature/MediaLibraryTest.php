<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_upload_image_to_their_wedding_media_library(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->image('couple.jpg', 800, 600);

        $response = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/media', [
            'file' => $file,
            'category' => 'couple',
            'tags' => ['prewedding', 'outdoor'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.filename', 'couple.jpg')
            ->assertJsonPath('data.category', 'couple')
            ->assertJsonPath('data.isSystem', false);

        $this->assertDatabaseHas('media', [
            'wedding_id' => $wedding->id,
            'user_id' => $user->id,
            'filename' => 'couple.jpg',
            'is_system' => false,
        ]);
    }

    public function test_user_can_list_their_wedding_media(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        Media::factory()->create([
            'user_id' => $user->id,
            'wedding_id' => $wedding->id,
            'filename' => 'photo1.jpg',
            'path' => 'media/photo1.jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/weddings/' . $wedding->id . '/media');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.filename', 'photo1.jpg');
    }

    public function test_user_cannot_view_or_access_media_from_another_wedding_idor(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $wedding2 = Wedding::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->getJson('/api/v1/weddings/' . $wedding2->id . '/media');

        $response->assertStatus(403);
    }

    public function test_user_can_delete_unreferenced_media(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $filePath = 'media/weddings/' . $wedding->id . '/image/todelete.jpg';
        Storage::disk('public')->put($filePath, 'fake image content');
        $this->assertTrue(Storage::disk('public')->exists($filePath));

        $media = Media::create([
            'user_id' => $user->id,
            'wedding_id' => $wedding->id,
            'type' => 'image',
            'filename' => 'todelete.jpg',
            'disk' => 'public',
            'path' => $filePath,
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'usage_count' => 0,
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/weddings/' . $wedding->id . '/media/' . $media->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('media', [
            'id' => $media->id,
        ]);

        // Verify storage file was permanently removed to prevent disk bloat
        $this->assertFalse(Storage::disk('public')->exists($filePath));
    }

    public function test_user_cannot_delete_media_from_another_wedding(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $wedding2 = Wedding::factory()->create(['user_id' => $user2->id]);

        $media2 = Media::create([
            'user_id' => $user2->id,
            'wedding_id' => $wedding2->id,
            'type' => 'image',
            'filename' => 'secret.jpg',
            'disk' => 'public',
            'path' => 'media/weddings/' . $wedding2->id . '/image/secret.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
        ]);

        $response = $this->actingAs($user1)->deleteJson('/api/v1/weddings/' . $wedding2->id . '/media/' . $media2->id);

        $response->assertStatus(403);
    }

    public function test_svg_sanitization_removes_malicious_scripts_and_event_handlers(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $maliciousSvg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
    <script>alert('XSS')</script>
    <circle cx="50" cy="50" r="40" onload="alert('XSS2')" onclick="alert('XSS3')" fill="red" />
    <foreignObject width="100" height="50">
        <body xmlns="http://www.w3.org/1999/xhtml"><script>alert('XSS4')</script></body>
    </foreignObject>
</svg>
SVG;

        $file = UploadedFile::fake()->createWithContent('malicious.svg', $maliciousSvg);

        $response = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/media', [
            'file' => $file,
            'category' => 'ornament',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'vector');

        $storedPath = Media::latest('id')->first()->path;
        $savedContent = Storage::disk('public')->get($storedPath);

        $this->assertStringNotContainsString('<script>', $savedContent);
        $this->assertStringNotContainsString('onload', $savedContent);
        $this->assertStringNotContainsString('onclick', $savedContent);
        $this->assertStringNotContainsString('<foreignObject', $savedContent);
    }

    public function test_admin_can_upload_and_manage_global_assets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $file = UploadedFile::fake()->image('global_ornament.png', 400, 400);

        // Upload global asset
        $uploadRes = $this->actingAs($admin)->postJson('/api/v1/admin/assets', [
            'file' => $file,
            'category' => 'ornament',
            'tags' => ['botanical', 'gold'],
        ]);

        $uploadRes->assertStatus(201)
            ->assertJsonPath('data.isSystem', true)
            ->assertJsonPath('data.weddingId', null);

        $mediaId = $uploadRes->json('data.id');

        // Update metadata
        $updateRes = $this->actingAs($admin)->putJson('/api/v1/admin/assets/' . $mediaId, [
            'category' => 'divider',
            'tags' => ['botanical', 'luxury'],
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.category', 'divider');

        // List global assets
        $listRes = $this->actingAs($admin)->getJson('/api/v1/admin/assets');
        $listRes->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_batch_upload_multiple_global_assets_simultaneously(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $file1 = UploadedFile::fake()->image('ornament_1.png', 400, 400);
        $file2 = UploadedFile::fake()->image('ornament_2.png', 400, 400);
        $file3 = UploadedFile::fake()->image('ornament_3.png', 400, 400);

        $uploadRes = $this->actingAs($admin)->postJson('/api/v1/admin/assets', [
            'files' => [$file1, $file2, $file3],
            'category' => 'ornament',
            'tags' => ['vintage', 'floral'],
        ]);

        $uploadRes->assertStatus(201)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.count', 3);

        $this->assertDatabaseHas('media', [
            'filename' => 'ornament_1.png',
            'category' => 'ornament',
            'is_system' => true,
        ]);
        $this->assertDatabaseHas('media', [
            'filename' => 'ornament_2.png',
            'category' => 'ornament',
            'is_system' => true,
        ]);
        $this->assertDatabaseHas('media', [
            'filename' => 'ornament_3.png',
            'category' => 'ornament',
            'is_system' => true,
        ]);
    }

    public function test_regular_user_cannot_access_or_manage_admin_global_assets(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/assets');
        $response->assertStatus(403);

        $postResponse = $this->actingAs($user)->postJson('/api/v1/admin/assets', []);
        $postResponse->assertStatus(403);
    }

    public function test_uploaded_image_is_automatically_converted_to_webp(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $file = UploadedFile::fake()->image('wedding_photo.jpg', 800, 600);

        $response = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/media', [
            'file' => $file,
            'category' => 'gallery',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.mimeType', 'image/webp')
            ->assertJsonPath('data.width', 800)
            ->assertJsonPath('data.height', 600);

        $media = Media::latest('id')->first();
        $this->assertEquals('image/webp', $media->mime_type);
        $this->assertStringEndsWith('.webp', $media->path);
        $this->assertTrue(Storage::disk('public')->exists($media->path));

        // Verify thumbnail variant is also WebP
        $this->assertNotNull($media->variants['thumb'] ?? null);
        $this->assertStringEndsWith('_thumb.webp', $media->variants['thumb']);
        $this->assertTrue(Storage::disk('public')->exists($media->variants['thumb']));
    }

    public function test_excessive_resolution_image_is_proportionally_downscaled_to_max_1920px(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        // Upload 4K image (3840x2160)
        $file = UploadedFile::fake()->image('huge_photo.jpg', 3840, 2160);

        $response = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/media', [
            'file' => $file,
            'category' => 'gallery',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.mimeType', 'image/webp')
            ->assertJsonPath('data.width', 1920)
            ->assertJsonPath('data.height', 1080);

        $media = Media::latest('id')->first();
        $this->assertEquals(1920, $media->width);
        $this->assertEquals(1080, $media->height);
    }

    public function test_reasonable_resolution_image_preserves_original_dimensions(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        // Upload normal 1200x800 image (<= 1920px)
        $file = UploadedFile::fake()->image('normal_photo.jpg', 1200, 800);

        $response = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/media', [
            'file' => $file,
            'category' => 'gallery',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.width', 1200)
            ->assertJsonPath('data.height', 800);

        $media = Media::latest('id')->first();
        $this->assertEquals(1200, $media->width);
        $this->assertEquals(800, $media->height);
    }

    public function test_wedding_storage_quota_blocks_upload_when_exceeding_30mb(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        // Seed 30MB of existing media
        Media::create([
            'user_id' => $user->id,
            'wedding_id' => $wedding->id,
            'type' => 'image',
            'filename' => 'existing_large.webp',
            'disk' => 'public',
            'path' => 'media/weddings/' . $wedding->id . '/image/existing.webp',
            'mime_type' => 'image/webp',
            'size' => 31457280, // Exactly 30MB
            'usage_count' => 0,
        ]);

        $newFile = UploadedFile::fake()->image('another.jpg', 400, 400);

        $response = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/media', [
            'file' => $newFile,
            'category' => 'gallery',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertStringContainsString('30 MB', $response->json('error.message'));
    }

    public function test_music_audio_upload_respects_30mb_wedding_quota(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        // Existing media uses 28MB
        Media::create([
            'user_id' => $user->id,
            'wedding_id' => $wedding->id,
            'type' => 'image',
            'filename' => 'existing.webp',
            'disk' => 'public',
            'path' => 'media/weddings/' . $wedding->id . '/image/existing.webp',
            'mime_type' => 'image/webp',
            'size' => 29360128, // ~28MB
            'usage_count' => 0,
        ]);

        // Upload a 5MB audio file (total would be 33MB > 30MB)
        $audioFile = UploadedFile::fake()->create('song.mp3', 5120, 'audio/mpeg'); // 5MB in KB

        $response = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/media', [
            'file' => $audioFile,
            'category' => 'music',
            'title' => 'My Song',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertStringContainsString('30 MB', $response->json('error.message'));
    }

    public function test_deleting_media_frees_up_storage_quota(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $filePath = 'media/weddings/' . $wedding->id . '/image/sample.webp';
        Storage::disk('public')->put($filePath, 'sample content');

        // Create media of 30MB
        $media = Media::create([
            'user_id' => $user->id,
            'wedding_id' => $wedding->id,
            'type' => 'image',
            'filename' => 'sample.webp',
            'disk' => 'public',
            'path' => $filePath,
            'mime_type' => 'image/webp',
            'size' => 31457280,
            'usage_count' => 0,
        ]);

        // Delete the 30MB media
        $deleteResponse = $this->actingAs($user)->deleteJson('/api/v1/weddings/' . $wedding->id . '/media/' . $media->id);
        $deleteResponse->assertStatus(200);

        // Upload should now succeed since quota is freed up
        $newFile = UploadedFile::fake()->image('fresh.jpg', 600, 600);
        $uploadResponse = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/media', [
            'file' => $newFile,
            'category' => 'gallery',
        ]);

        $uploadResponse->assertStatus(201);
    }
}
