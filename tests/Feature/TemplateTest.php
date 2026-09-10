<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_active_templates(): void
    {
        $user = User::factory()->create();

        Template::create([
            'name' => 'Active Template',
            'slug' => 'active-template',
            'category' => 'botanical',
            'is_active' => true,
            'schema_version' => 1,
            'schema' => ['schemaVersion' => 1, 'sections' => []],
            'contract' => ['sections' => []],
        ]);

        Template::create([
            'name' => 'Inactive Template',
            'slug' => 'inactive-template',
            'category' => 'minimalist',
            'is_active' => false,
            'schema_version' => 1,
            'schema' => ['schemaVersion' => 1, 'sections' => []],
            'contract' => ['sections' => []],
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/templates');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'active-template');
    }

    public function test_admin_can_create_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/api/v1/templates', [
            'name' => 'Luxury Emerald',
            'slug' => 'luxury-emerald',
            'category' => 'luxury',
            'schema' => [
                'schemaVersion' => 1,
                'sections' => [],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.slug', 'luxury-emerald')
            ->assertJsonPath('data.isActive', false);

        $this->assertDatabaseHas('templates', [
            'slug' => 'luxury-emerald',
            'is_active' => false,
        ]);
    }

    public function test_non_admin_cannot_create_template(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->postJson('/api/v1/templates', [
            'name' => 'Unauthorized Template',
            'slug' => 'unauthorized-template',
            'category' => 'botanical',
            'schema' => ['schemaVersion' => 1, 'sections' => []],
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_duplicate_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $template = Template::create([
            'name' => 'Original Template',
            'slug' => 'orig-temp',
            'category' => 'botanical',
            'schema_version' => 1,
            'schema' => ['schemaVersion' => 1, 'sections' => []],
            'contract' => ['sections' => []],
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/templates/' . $template->id . '/duplicate');

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Original Template (Copy)')
            ->assertJsonPath('data.isActive', false);

        $this->assertDatabaseCount('templates', 2);
        $this->assertDatabaseHas('templates', [
            'slug' => $response->json('data.slug'),
            'is_active' => false,
        ]);
    }

    public function test_user_can_apply_template_to_their_wedding(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $template = Template::create([
            'name' => 'Starter Template',
            'slug' => 'starter-template',
            'category' => 'botanical',
            'schema_version' => 1,
            'schema' => [
                'schemaVersion' => 1,
                'sections' => [
                    ['id' => 'sec_1', 'name' => 'Sampul', 'height' => 500, 'elements' => []],
                ],
            ],
            'contract' => ['sections' => []],
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/weddings/' . $wedding->id . '/apply-template/' . $template->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.applied', true)
            ->assertJsonPath('data.templateId', $template->id);

        $this->assertDatabaseHas('designs', [
            'wedding_id' => $wedding->id,
            'template_id' => $template->id,
        ]);
    }

    public function test_admin_can_fetch_platform_overview(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'overview' => [
                        'totalTemplates',
                        'activeTemplates',
                        'totalAssets',
                        'totalWeddings',
                        'totalUsers',
                    ],
                    'assetCategories',
                    'recentTemplates',
                ],
            ]);
    }

    public function test_regular_user_cannot_fetch_admin_overview(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->getJson('/api/v1/admin/overview');

        $response->assertStatus(403);
    }

    public function test_public_user_can_preview_active_template(): void
    {
        $template = Template::create([
            'name' => 'Botanical Elegance',
            'slug' => 'botanical-elegance',
            'category' => 'botanical',
            'is_active' => true,
            'schema_version' => 1,
            'schema' => [
                'schemaVersion' => 1,
                'sections' => [
                    ['id' => 'sec_cover', 'name' => 'Sampul', 'type' => 'cover', 'elements' => []],
                ],
            ],
            'contract' => ['sections' => []],
        ]);

        $response = $this->getJson('/api/v1/public/templates/botanical-elegance/preview');

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'botanical-elegance')
            ->assertJsonPath('data.name', 'Botanical Elegance')
            ->assertJsonPath('data.schema.sections.0.name', 'Sampul');
    }

    public function test_guest_cannot_preview_inactive_template(): void
    {
        Template::create([
            'name' => 'Draft Inactive Template',
            'slug' => 'draft-inactive',
            'category' => 'modern',
            'is_active' => false,
            'schema_version' => 1,
            'schema' => ['schemaVersion' => 1, 'sections' => []],
            'contract' => ['sections' => []],
        ]);

        $response = $this->getJson('/api/v1/public/templates/draft-inactive/preview');

        $response->assertStatus(404);
    }

    public function test_admin_can_preview_inactive_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Template::create([
            'name' => 'Admin Inactive Template',
            'slug' => 'admin-inactive',
            'category' => 'classic',
            'is_active' => false,
            'schema_version' => 1,
            'schema' => ['schemaVersion' => 1, 'sections' => []],
            'contract' => ['sections' => []],
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/public/templates/admin-inactive/preview');

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'admin-inactive');
    }

    public function test_admin_can_update_template_desktop_cover_and_persists_intact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $template = Template::create([
            'name' => 'Custom Cover Template',
            'slug' => 'custom-cover-template',
            'category' => 'modern',
            'schema_version' => 1,
            'schema' => [
                'schemaVersion' => 1,
                'sections' => [],
                'desktopCover' => [
                    'enabled' => true,
                    'widthRatio' => 40,
                    'backgroundColor' => '#111827',
                    'elements' => [],
                ],
            ],
            'contract' => ['sections' => []],
        ]);

        $updatedSchema = [
            'schemaVersion' => 1,
            'metadata' => ['id' => 'tpl_updated', 'name' => 'Custom Cover Template'],
            'theme' => ['colors' => ['primary' => '#10B981', 'background' => '#0F172A']],
            'desktopCover' => [
                'enabled' => true,
                'widthRatio' => 45,
                'backgroundColor' => '#0B132B',
                'backgroundOpacity' => 90,
                'layout' => [
                    'enabled' => true,
                    'direction' => 'vertical',
                    'gap' => 24,
                    'padding' => ['top' => 40, 'right' => 20, 'bottom' => 40, 'left' => 20],
                ],
                'elements' => [
                    [
                        'id' => 'el_test_greeting',
                        'name' => 'Salam',
                        'type' => 'text',
                        'transform' => ['x' => 0, 'y' => 0, 'width' => 300, 'height' => 30, 'rotation' => 0, 'zIndex' => 1],
                        'style' => ['fontSize' => 14, 'color' => '#FFFFFF'],
                        'props' => ['text' => 'Pernikahan Impian'],
                    ],
                ],
            ],
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 700,
                    'elements' => [],
                ],
            ],
        ];

        $updateRes = $this->actingAs($admin)->putJson('/api/v1/templates/' . $template->id, [
            'schema' => $updatedSchema,
        ]);
        $updateRes->assertStatus(200);

        // Fetch back and verify desktopCover was NOT stripped
        $fresh = $template->fresh();
        $this->assertEquals(45, $fresh->schema['desktopCover']['widthRatio']);
        $this->assertEquals('#0B132B', $fresh->schema['desktopCover']['backgroundColor']);
        $this->assertEquals(90, $fresh->schema['desktopCover']['backgroundOpacity']);
        $this->assertEquals('el_test_greeting', $fresh->schema['desktopCover']['elements'][0]['id']);
        $this->assertEquals('Pernikahan Impian', $fresh->schema['desktopCover']['elements'][0]['props']['text']);
    }
}
