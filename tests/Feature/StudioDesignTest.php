<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_studio_design(): void
    {
        $wedding = Wedding::factory()->create();

        $response = $this->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $response->assertStatus(401);
    }

    public function test_admin_or_owner_can_fetch_studio_design_schema(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/weddings/' . $wedding->id . '/design');

        $response->assertStatus(200)
            ->assertJsonPath('data.schemaVersion', 1)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'weddingId',
                    'schemaVersion',
                    'schema' => [
                        'schemaVersion',
                        'metadata',
                        'theme',
                        'sections',
                    ],
                ],
            ]);
    }

    public function test_user_cannot_fetch_other_users_design_schema_idor(): void
    {
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);
        $wedding2 = Wedding::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->getJson('/api/v1/weddings/' . $wedding2->id . '/design');
        $response->assertStatus(403);
    }

    public function test_admin_or_owner_can_update_design_schema(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $updatedSchema = [
            'schemaVersion' => 1,
            'metadata' => [
                'id' => 'design-123',
                'name' => 'Custom Design',
                'category' => 'modern',
            ],
            'theme' => [
                'colors' => [
                    'primary' => '#1ED760',
                ],
            ],
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Custom Section',
                    'height' => 800,
                    'elements' => [
                        [
                            'id' => 'el_test_1',
                            'type' => 'text',
                            'transform' => ['x' => 100, 'y' => 200, 'width' => 300, 'height' => 50, 'rotation' => 0, 'zIndex' => 1],
                            'style' => ['fontSize' => 18],
                            'props' => ['text' => 'Hello Studio Canvas'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $updatedSchema,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.saved', true);

        $this->assertDatabaseHas('designs', [
            'wedding_id' => $wedding->id,
            'schema_version' => 1,
        ]);
    }

    public function test_update_design_schema_validates_structure(): void
    {
        $user = User::factory()->create();
        $wedding = Wedding::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => 'invalid_string',
        ]);

        $response->assertStatus(422);
    }

    public function test_optimistic_locking_conflict_returns_409(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        // Initialize design
        $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');

        $schemaPayload = [
            'schemaVersion' => 1,
            'sections' => [],
        ];

        // First update with matching client_version (1) -> succeeds, version becomes 2
        $res1 = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'client_version' => 1,
            'schema' => $schemaPayload,
        ]);
        $res1->assertStatus(200);

        // Stale second update trying to save with client_version (1) -> returns 409 Conflict
        $res2 = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'client_version' => 1,
            'schema' => $schemaPayload,
        ]);
        $res2->assertStatus(409)
            ->assertJsonPath('error.code', 'VERSION_CONFLICT');
    }

    public function test_admin_can_save_template_with_12_widgets_and_variants(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $widgets = [
            ['id' => 'el_btn', 'type' => 'button', 'props' => ['variant' => 'luxury', 'label' => 'Buka Tautan', 'url' => 'https://ayohadir.com']],
            ['id' => 'el_loc', 'type' => 'location', 'props' => ['variant' => 'elegant', 'venueName' => 'Gedung Sasana', 'mapUrl' => 'https://maps.google.com']],
            ['id' => 'el_stream', 'type' => 'streaming', 'props' => ['variant' => 'modern', 'platform' => 'youtube', 'streamUrl' => 'https://youtube.com/live']],
            ['id' => 'el_gal', 'type' => 'gallery', 'props' => ['variant' => 'aesthetic', 'columns' => 3, 'images' => []]],
            ['id' => 'el_vid', 'type' => 'video', 'props' => ['variant' => 'classic', 'youtubeUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']],
            ['id' => 'el_count', 'type' => 'countdown', 'props' => ['variant' => 'elegant', 'targetDate' => '2026-11-15']],
            ['id' => 'el_rsvp', 'type' => 'rsvp', 'props' => ['variant' => 'modern', 'title' => 'Konfirmasi Kehadiran']],
            ['id' => 'el_greet', 'type' => 'greeting', 'props' => ['variant' => 'classic', 'quote' => 'Dan di antara tanda-tanda kebesaran-Nya...']],
            ['id' => 'el_gift', 'type' => 'gift', 'props' => ['variant' => 'luxury', 'showQris' => false, 'accounts' => [['bank' => 'BCA', 'accountNumber' => '1234567890', 'holderName' => 'Alya']]]],
            ['id' => 'el_copy', 'type' => 'copy_text', 'props' => ['variant' => 'modern', 'valueToCopy' => 'ALYA-RAKA-2026']],
            ['id' => 'el_acc', 'type' => 'account', 'props' => ['variant' => 'elegant', 'providerType' => 'bank', 'providerName' => 'BCA', 'accountNumber' => '1234567890', 'accountHolder' => 'Alya Putri']],
            ['id' => 'el_mus', 'type' => 'music', 'props' => ['variant' => 'luxury', 'audioUrl' => 'https://cdn.example.com/audio.mp3', 'autoplay' => true, 'loop' => true]],
        ];

        $schema = [
            'schemaVersion' => 1,
            'metadata' => [
                'id' => 'tpl_test_widgets',
                'name' => 'Template with All Widgets',
                'category' => 'luxury',
            ],
            'sections' => [
                [
                    'id' => 'sec_widgets',
                    'name' => 'Widget Showcase',
                    'height' => 1200,
                    'elements' => array_map(function ($w) {
                        return [
                            'id' => $w['id'],
                            'type' => $w['type'],
                            'transform' => ['x' => 100, 'y' => 100, 'width' => 400, 'height' => 100, 'rotation' => 0, 'zIndex' => 1],
                            'style' => ['borderRadius' => 8],
                            'props' => $w['props'],
                            'responsive' => [
                                'mobile' => ['width' => 320],
                            ],
                        ];
                    }, $widgets),
                ],
            ],
        ];

        $response = $this->actingAs($admin)->postJson('/api/v1/templates', [
            'name' => 'Template with All Widgets',
            'slug' => 'template-with-all-widgets',
            'category' => 'luxury',
            'schema' => $schema,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'button')
            ->assertJsonPath('data.schema.sections.0.elements.11.type', 'music');
    }

    public function test_admin_can_save_template_with_auto_layout_container_and_nested_grouped_children(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $schema = [
            'schemaVersion' => 1,
            'metadata' => [
                'id' => 'tpl_test_autolayout',
                'name' => 'Template with Auto Layout',
                'category' => 'modern',
            ],
            'sections' => [
                [
                    'id' => 'sec_autolayout',
                    'name' => 'Auto Layout Section',
                    'height' => 800,
                    'elements' => [
                        [
                            'id' => 'el_container_1',
                            'name' => 'Main Auto Layout Group',
                            'type' => 'container',
                            'transform' => ['x' => 40, 'y' => 40, 'width' => 310, 'height' => 400, 'rotation' => 0, 'zIndex' => 1],
                            'style' => ['backgroundColor' => '#FFFFFF', 'borderRadius' => 12],
                            'props' => [],
                            'layout' => [
                                'enabled' => true,
                                'direction' => 'vertical',
                                'gap' => 16,
                                'padding' => ['top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20],
                                'align' => 'center',
                                'distribution' => 'start',
                                'widthSizing' => 'hug',
                                'heightSizing' => 'hug',
                            ],
                            'children' => [
                                [
                                    'id' => 'el_child_text',
                                    'name' => 'Heading Text',
                                    'type' => 'text',
                                    'parentId' => 'el_container_1',
                                    'transform' => ['x' => 0, 'y' => 0, 'width' => 270, 'height' => 40, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => ['fontSize' => 18, 'fontWeight' => 'bold'],
                                    'props' => ['text' => 'Save The Date'],
                                ],
                                [
                                    'id' => 'el_child_nested_group',
                                    'name' => 'Nested Button Group',
                                    'type' => 'container',
                                    'parentId' => 'el_container_1',
                                    'transform' => ['x' => 0, 'y' => 56, 'width' => 270, 'height' => 50, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['backgroundColor' => 'transparent'],
                                    'props' => [],
                                    'layout' => [
                                        'enabled' => true,
                                        'direction' => 'horizontal',
                                        'gap' => 12,
                                        'padding' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0],
                                        'align' => 'center',
                                        'distribution' => 'center',
                                        'widthSizing' => 'fill',
                                        'heightSizing' => 'fixed',
                                    ],
                                    'children' => [
                                        [
                                            'id' => 'el_child_btn1',
                                            'name' => 'RSVP Button',
                                            'type' => 'button',
                                            'parentId' => 'el_child_nested_group',
                                            'transform' => ['x' => 0, 'y' => 0, 'width' => 120, 'height' => 40, 'rotation' => 0, 'zIndex' => 1],
                                            'style' => [],
                                            'props' => ['label' => 'RSVP Now'],
                                        ],
                                        [
                                            'id' => 'el_child_btn2',
                                            'name' => 'Maps Button',
                                            'type' => 'button',
                                            'parentId' => 'el_child_nested_group',
                                            'transform' => ['x' => 132, 'y' => 0, 'width' => 120, 'height' => 40, 'rotation' => 0, 'zIndex' => 2],
                                            'style' => [],
                                            'props' => ['label' => 'Google Maps'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($admin)->postJson('/api/v1/templates', [
            'name' => 'Template with Auto Layout & Nested Groups',
            'slug' => 'template-with-autolayout',
            'category' => 'modern',
            'schema' => $schema,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'container')
            ->assertJsonPath('data.schema.sections.0.elements.0.layout.enabled', true)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.0.type', 'text')
            ->assertJsonPath('data.schema.sections.0.elements.0.children.1.type', 'container')
            ->assertJsonPath('data.schema.sections.0.elements.0.children.1.children.0.type', 'button');
    }

    public function test_update_design_schema_rejects_duplicate_section_names(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $invalidSchema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_1',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 600,
                    'elements' => [],
                ],
                [
                    'id' => 'sec_2',
                    'name' => 'Sampul', // Duplicate name
                    'type' => 'cover',
                    'height' => 600,
                    'elements' => [],
                ],
            ],
        ];

        $response = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $invalidSchema,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'errors' => ['schema.sections.1.name'],
                    ],
                ],
            ]);
    }

    public function test_admin_cannot_create_template_with_duplicate_section_names(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $invalidSchema = [
            'schemaVersion' => 1,
            'sections' => [
                ['id' => 'sec_1', 'name' => 'Acara', 'type' => 'event', 'height' => 600, 'elements' => []],
                ['id' => 'sec_2', 'name' => 'Acara', 'type' => 'event', 'height' => 600, 'elements' => []],
            ],
        ];

        $response = $this->actingAs($admin)->postJson('/api/v1/templates', [
            'name' => 'Invalid Duplicate Template',
            'slug' => 'invalid-duplicate-template',
            'category' => 'modern',
            'schema' => $invalidSchema,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'errors' => ['schema.sections.1.name'],
                    ],
                ],
            ]);
    }

    public function test_auto_layout_positions_and_properties_persist_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        // Container with width 320, padding left/right 16 (availableWidth = 288), padding top 24, gap 20, align center
        // Child A: width 200, height 50 -> x = 16 + (288 - 200) / 2 = 60, y = 24
        // Child B: width 150, height 40 -> x = 16 + (288 - 150) / 2 = 85, y = 24 + 50 + 20 = 94
        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 600,
                    'elements' => [
                        [
                            'id' => 'el_container_1',
                            'name' => 'Hero Stack',
                            'type' => 'container',
                            'transform' => ['x' => 35, 'y' => 50, 'width' => 320, 'height' => 300, 'rotation' => 0, 'zIndex' => 1],
                            'style' => ['backgroundColor' => '#FFFFFF'],
                            'props' => [],
                            'layout' => [
                                'enabled' => true,
                                'direction' => 'vertical',
                                'gap' => 20,
                                'padding' => ['top' => 24, 'right' => 16, 'bottom' => 24, 'left' => 16],
                                'align' => 'center',
                                'distribution' => 'start',
                                'widthSizing' => 'fixed',
                                'heightSizing' => 'hug',
                            ],
                            'children' => [
                                [
                                    'id' => 'el_child_a',
                                    'name' => 'Title Text',
                                    'type' => 'text',
                                    'parentId' => 'el_container_1',
                                    'transform' => ['x' => 60, 'y' => 24, 'width' => 200, 'height' => 50, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => ['color' => '#171A18', 'fontSize' => 24],
                                    'props' => ['text' => 'The Wedding'],
                                ],
                                [
                                    'id' => 'el_child_b',
                                    'name' => 'Subtitle Text',
                                    'type' => 'text',
                                    'parentId' => 'el_container_1',
                                    'transform' => ['x' => 85, 'y' => 94, 'width' => 150, 'height' => 40, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['color' => '#52525B', 'fontSize' => 16],
                                    'props' => ['text' => 'Alya & Raka'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveResponse = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);

        $saveResponse->assertStatus(200);

        $fetchResponse = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchResponse->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.layout.enabled', true)
            ->assertJsonPath('data.schema.sections.0.elements.0.layout.gap', 20)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.0.transform.x', 60)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.0.transform.y', 24)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.1.transform.x', 85)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.1.transform.y', 94);
    }

    public function test_free_group_and_unpacked_elements_persist_global_transforms_and_hierarchy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        // 1. Group at (100, 200) with Child A at (20, 30) and Child B at (50, 80)
        $groupSchema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 600,
                    'elements' => [
                        [
                            'id' => 'el_group_1',
                            'name' => 'Hero Group',
                            'type' => 'container',
                            'transform' => ['x' => 100, 'y' => 200, 'width' => 400, 'height' => 300, 'rotation' => 0, 'zIndex' => 5],
                            'style' => [],
                            'props' => [],
                            'children' => [
                                [
                                    'id' => 'el_child_1',
                                    'name' => 'Text 1',
                                    'type' => 'text',
                                    'parentId' => 'el_group_1',
                                    'transform' => ['x' => 20, 'y' => 30, 'width' => 100, 'height' => 40, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => [],
                                    'props' => ['text' => 'Child 1'],
                                ],
                                [
                                    'id' => 'el_child_2',
                                    'name' => 'Button 1',
                                    'type' => 'button',
                                    'parentId' => 'el_group_1',
                                    'transform' => ['x' => 50, 'y' => 80, 'width' => 120, 'height' => 45, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => [],
                                    'props' => ['label' => 'Click Me'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveGroupRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $groupSchema,
        ]);
        $saveGroupRes->assertStatus(200);

        // 2. Ungrouped state: Children become root elements in section with global coordinates (120, 230) and (150, 280)
        $ungroupedSchema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 600,
                    'elements' => [
                        [
                            'id' => 'el_child_1',
                            'name' => 'Text 1',
                            'type' => 'text',
                            'transform' => ['x' => 120, 'y' => 230, 'width' => 100, 'height' => 40, 'rotation' => 0, 'zIndex' => 5],
                            'style' => [],
                            'props' => ['text' => 'Child 1'],
                        ],
                        [
                            'id' => 'el_child_2',
                            'name' => 'Button 1',
                            'type' => 'button',
                            'transform' => ['x' => 150, 'y' => 280, 'width' => 120, 'height' => 45, 'rotation' => 0, 'zIndex' => 6],
                            'style' => [],
                            'props' => ['label' => 'Click Me'],
                        ],
                    ],
                ],
            ],
        ];

        $saveUngroupRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $ungroupedSchema,
        ]);
        $saveUngroupRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonCount(2, 'data.schema.sections.0.elements')
            ->assertJsonPath('data.schema.sections.0.elements.0.transform.x', 120)
            ->assertJsonPath('data.schema.sections.0.elements.0.transform.y', 230)
            ->assertJsonPath('data.schema.sections.0.elements.1.transform.x', 150)
            ->assertJsonPath('data.schema.sections.0.elements.1.transform.y', 280);
    }

    public function test_rotated_group_ungroup_preserves_rotational_coordinates_and_transforms(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        // Rotated Group at (100, 100, 200, 200, rot: 90). Child at (50, 50, 100, 100)
        // Center: (200, 200), Unpacked: x=150, y=150, rotation=90
        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 600,
                    'elements' => [
                        [
                            'id' => 'el_child_1',
                            'name' => 'Rotated Text',
                            'type' => 'text',
                            'transform' => ['x' => 150, 'y' => 150, 'width' => 100, 'height' => 100, 'rotation' => 90, 'zIndex' => 2],
                            'style' => [],
                            'props' => ['text' => 'Rotated Text'],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.transform.x', 150)
            ->assertJsonPath('data.schema.sections.0.elements.0.transform.y', 150)
            ->assertJsonPath('data.schema.sections.0.elements.0.transform.rotation', 90);
    }

    public function test_auto_layout_disabled_and_ungrouped_materializes_exact_positions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        // Materialized & Ungrouped: Container (100, 150), gap: 40, padding: 20
        // Child 1 local (75, 20) -> Global (175, 170)
        // Child 2 local (100, 110) -> Global (200, 260)
        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_acara',
                    'name' => 'Acara',
                    'type' => 'event',
                    'height' => 600,
                    'elements' => [
                        [
                            'id' => 'el_child_1',
                            'name' => 'Event Title',
                            'type' => 'text',
                            'transform' => ['x' => 175, 'y' => 170, 'width' => 200, 'height' => 50, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [],
                            'props' => ['text' => 'Akad Nikah'],
                        ],
                        [
                            'id' => 'el_child_2',
                            'name' => 'Event Button',
                            'type' => 'button',
                            'transform' => ['x' => 200, 'y' => 260, 'width' => 150, 'height' => 40, 'rotation' => 0, 'zIndex' => 2],
                            'style' => [],
                            'props' => ['label' => 'Buka Lokasi'],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonCount(2, 'data.schema.sections.0.elements')
            ->assertJsonPath('data.schema.sections.0.elements.0.transform.x', 175)
            ->assertJsonPath('data.schema.sections.0.elements.0.transform.y', 170)
            ->assertJsonPath('data.schema.sections.0.elements.1.transform.x', 200)
            ->assertJsonPath('data.schema.sections.0.elements.1.transform.y', 260);
    }

    public function test_auto_layout_child_resize_recalculates_sibling_positions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        // Container with gap: 25, padding top: 30, vertical direction
        // Child A resized from height 100 -> 160
        // Child B sibling automatically shifts to Y = 30 + 160 + 25 = 215
        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 600,
                    'elements' => [
                        [
                            'id' => 'el_stack_1',
                            'name' => 'Vertical Stack',
                            'type' => 'container',
                            'transform' => ['x' => 20, 'y' => 50, 'width' => 350, 'height' => 450, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [],
                            'props' => [],
                            'layout' => [
                                'enabled' => true,
                                'direction' => 'vertical',
                                'gap' => 25,
                                'padding' => ['top' => 30, 'right' => 20, 'bottom' => 30, 'left' => 20],
                                'align' => 'start',
                                'distribution' => 'start',
                                'widthSizing' => 'fixed',
                                'heightSizing' => 'hug',
                            ],
                            'children' => [
                                [
                                    'id' => 'el_child_a',
                                    'name' => 'Resized Child A',
                                    'type' => 'text',
                                    'parentId' => 'el_stack_1',
                                    'transform' => ['x' => 20, 'y' => 30, 'width' => 250, 'height' => 160, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => [],
                                    'props' => ['text' => 'Resized Title'],
                                ],
                                [
                                    'id' => 'el_child_b',
                                    'name' => 'Sibling Child B',
                                    'type' => 'button',
                                    'parentId' => 'el_stack_1',
                                    'transform' => ['x' => 20, 'y' => 215, 'width' => 180, 'height' => 50, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => [],
                                    'props' => ['label' => 'Next Button'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.0.transform.height', 160)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.0.transform.y', 30)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.1.transform.y', 215);
    }

    public function test_auto_layout_horizontal_wrap_calculates_rows_and_positions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        // Container width 390, padding left/right 20 (availW: 350), gap: 10, wrap: 'wrap'
        // 5 children of width 100 each, height 40 each:
        // Row 1: Child 1 (x: 20, y: 20), Child 2 (x: 130, y: 20), Child 3 (x: 240, y: 20)
        // Row 2: Child 4 (x: 20, y: 70), Child 5 (x: 130, y: 70)
        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 600,
                    'elements' => [
                        [
                            'id' => 'el_wrap_container',
                            'name' => 'Tag Chips Wrap',
                            'type' => 'container',
                            'transform' => ['x' => 0, 'y' => 50, 'width' => 390, 'height' => 200, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [],
                            'props' => [],
                            'layout' => [
                                'enabled' => true,
                                'direction' => 'horizontal',
                                'wrap' => 'wrap',
                                'gap' => 10,
                                'padding' => ['top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20],
                                'align' => 'start',
                                'distribution' => 'start',
                                'widthSizing' => 'fixed',
                                'heightSizing' => 'hug',
                            ],
                            'children' => [
                                [
                                    'id' => 'el_chip_1',
                                    'name' => 'Chip 1',
                                    'type' => 'button',
                                    'parentId' => 'el_wrap_container',
                                    'transform' => ['x' => 20, 'y' => 20, 'width' => 100, 'height' => 40, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => [],
                                    'props' => ['label' => 'Chip 1'],
                                ],
                                [
                                    'id' => 'el_chip_2',
                                    'name' => 'Chip 2',
                                    'type' => 'button',
                                    'parentId' => 'el_wrap_container',
                                    'transform' => ['x' => 130, 'y' => 20, 'width' => 100, 'height' => 40, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => [],
                                    'props' => ['label' => 'Chip 2'],
                                ],
                                [
                                    'id' => 'el_chip_3',
                                    'name' => 'Chip 3',
                                    'type' => 'button',
                                    'parentId' => 'el_wrap_container',
                                    'transform' => ['x' => 240, 'y' => 20, 'width' => 100, 'height' => 40, 'rotation' => 0, 'zIndex' => 3],
                                    'style' => [],
                                    'props' => ['label' => 'Chip 3'],
                                ],
                                [
                                    'id' => 'el_chip_4',
                                    'name' => 'Chip 4',
                                    'type' => 'button',
                                    'parentId' => 'el_wrap_container',
                                    'transform' => ['x' => 20, 'y' => 70, 'width' => 100, 'height' => 40, 'rotation' => 0, 'zIndex' => 4],
                                    'style' => [],
                                    'props' => ['label' => 'Chip 4'],
                                ],
                                [
                                    'id' => 'el_chip_5',
                                    'name' => 'Chip 5',
                                    'type' => 'button',
                                    'parentId' => 'el_wrap_container',
                                    'transform' => ['x' => 130, 'y' => 70, 'width' => 100, 'height' => 40, 'rotation' => 0, 'zIndex' => 5],
                                    'style' => [],
                                    'props' => ['label' => 'Chip 5'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.layout.wrap', 'wrap')
            ->assertJsonPath('data.schema.sections.0.elements.0.children.0.transform.x', 20)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.0.transform.y', 20)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.1.transform.x', 130)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.1.transform.y', 20)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.2.transform.x', 240)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.2.transform.y', 20)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.3.transform.x', 20)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.3.transform.y', 70)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.4.transform.x', 130)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.4.transform.y', 70);
    }

    public function test_auto_layout_preserves_existing_group_spatial_positions_and_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        // Group with:
        // 1. Text Atas: y = 100, height = 40
        // 2. Square in middle: y = 220, height = 100
        // 3. Text Bawah: y = 380, height = 40
        // Initialized with inferred padding top: 100, gap: 60, align: center
        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 600,
                    'elements' => [
                        [
                            'id' => 'el_container_hero',
                            'name' => 'Hero Stack',
                            'type' => 'container',
                            'transform' => ['x' => 20, 'y' => 0, 'width' => 350, 'height' => 500, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [],
                            'props' => [],
                            'layout' => [
                                'enabled' => true,
                                'direction' => 'vertical',
                                'gap' => 60,
                                'padding' => ['top' => 100, 'right' => 20, 'bottom' => 40, 'left' => 20],
                                'align' => 'center',
                                'distribution' => 'start',
                                'widthSizing' => 'fixed',
                                'heightSizing' => 'hug',
                            ],
                            'children' => [
                                [
                                    'id' => 'el_text_top',
                                    'name' => 'Text Atas',
                                    'type' => 'text',
                                    'parentId' => 'el_container_hero',
                                    'transform' => ['x' => 75, 'y' => 100, 'width' => 200, 'height' => 40, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => [],
                                    'props' => ['text' => 'The Wedding Of'],
                                ],
                                [
                                    'id' => 'el_square_middle',
                                    'name' => 'Square Middle',
                                    'type' => 'image',
                                    'parentId' => 'el_container_hero',
                                    'transform' => ['x' => 55, 'y' => 200, 'width' => 240, 'height' => 100, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => [],
                                    'props' => ['url' => '/images/demo.jpg'],
                                ],
                                [
                                    'id' => 'el_text_bottom',
                                    'name' => 'Text Bawah',
                                    'type' => 'text',
                                    'parentId' => 'el_container_hero',
                                    'transform' => ['x' => 75, 'y' => 360, 'width' => 200, 'height' => 40, 'rotation' => 0, 'zIndex' => 3],
                                    'style' => [],
                                    'props' => ['text' => 'Alya & Raka'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.0.id', 'el_text_top')
            ->assertJsonPath('data.schema.sections.0.elements.0.children.0.transform.y', 100)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.1.id', 'el_square_middle')
            ->assertJsonPath('data.schema.sections.0.elements.0.children.1.transform.y', 200)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.2.id', 'el_text_bottom')
            ->assertJsonPath('data.schema.sections.0.elements.0.children.2.transform.y', 360);
    }

    public function test_section_height_mode_persists_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'metadata' => ['id' => 'tmpl_height_test', 'name' => 'Height Mode Test', 'category' => 'modern'],
            'theme' => ['colors' => ['primary' => '#1ED760']],
            'sections' => [
                [
                    'id' => 'sec_hero',
                    'name' => 'Hero Cover',
                    'type' => 'cover',
                    'height' => 844,
                    'heightMode' => 'full-screen',
                    'elements' => [
                        [
                            'id' => 'el_h1',
                            'type' => 'text',
                            'transform' => ['x' => 20, 'y' => 100, 'width' => 350, 'height' => 40, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [],
                            'props' => ['text' => 'The Wedding'],
                        ],
                    ],
                ],
                [
                    'id' => 'sec_details',
                    'name' => 'Event Details',
                    'type' => 'event',
                    'height' => 720,
                    'heightMode' => 'fit-content',
                    'minimumHeight' => 720,
                    'elements' => [
                        [
                            'id' => 'el_d1',
                            'type' => 'text',
                            'transform' => ['x' => 20, 'y' => 40, 'width' => 350, 'height' => 60, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [],
                            'props' => ['text' => 'Akad & Resepsi'],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.heightMode', 'full-screen')
            ->assertJsonPath('data.schema.sections.1.heightMode', 'fit-content')
            ->assertJsonPath('data.schema.sections.1.minimumHeight', 720);
    }

    public function test_template_schema_with_multiple_sections_and_dynamic_widgets_persists_intact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'metadata' => ['id' => 'tmpl_multisec_test', 'name' => 'Multi Section Test', 'category' => 'modern'],
            'theme' => ['colors' => ['primary' => '#1ED760']],
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Cover Section',
                    'type' => 'cover',
                    'height' => 844,
                    'heightMode' => 'full-screen',
                    'elements' => [
                        [
                            'id' => 'el_c_1',
                            'type' => 'text',
                            'transform' => ['x' => 20, 'y' => 100, 'width' => 350, 'height' => 40, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [],
                            'props' => ['text' => 'Alya & Raka'],
                        ],
                    ],
                ],
                [
                    'id' => 'sec_gallery',
                    'name' => 'Gallery Section',
                    'type' => 'gallery',
                    'height' => 1200,
                    'heightMode' => 'fit-content',
                    'minimumHeight' => 800,
                    'elements' => [
                        [
                            'id' => 'el_g_1',
                            'type' => 'gallery',
                            'transform' => ['x' => 20, 'y' => 40, 'width' => 350, 'height' => 600, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [],
                            'props' => [
                                'variant' => 'elegant',
                                'images' => [
                                    ['id' => 'g1', 'url' => 'https://img.jpg', 'caption' => 'Photo 1'],
                                    ['id' => 'g2', 'url' => 'https://img2.jpg', 'caption' => 'Photo 2'],
                                    ['id' => 'g3', 'url' => 'https://img3.jpg', 'caption' => 'Photo 3'],
                                    ['id' => 'g4', 'url' => 'https://img4.jpg', 'caption' => 'Photo 4'],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'sec_rsvp',
                    'name' => 'RSVP Section',
                    'type' => 'rsvp',
                    'height' => 700,
                    'heightMode' => 'fit-content',
                    'elements' => [
                        [
                            'id' => 'el_r_1',
                            'type' => 'rsvp',
                            'transform' => ['x' => 20, 'y' => 40, 'width' => 350, 'height' => 400, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [],
                            'props' => ['variant' => 'luxury'],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonCount(3, 'data.schema.sections')
            ->assertJsonPath('data.schema.sections.0.id', 'sec_cover')
            ->assertJsonPath('data.schema.sections.1.id', 'sec_gallery')
            ->assertJsonPath('data.schema.sections.2.id', 'sec_rsvp');
    }

    public function test_anchor_constraint_with_responsive_auto_layout_persists_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create(['user_id' => $admin->id]);

        $schema = [
            'schemaVersion' => 1,
            'metadata' => [
                'name' => 'Anchor Responsive Auto Layout Test',
                'description' => 'Testing anchor constraints with responsive auto layout',
                'author' => 'AyoHadir Admin',
            ],
            'theme' => [
                'colors' => [
                    'primary' => '#15803D',
                    'secondary' => '#F4FBF6',
                    'text' => '#171A18',
                    'background' => '#FFFFFF',
                ],
                'typography' => [
                    'headingFont' => 'Playfair Display',
                    'bodyFont' => 'Inter',
                ],
            ],
            'sections' => [
                [
                    'id' => 'sec_main',
                    'name' => 'Main Section',
                    'type' => 'custom',
                    'height' => 844,
                    'heightMode' => 'fit-content',
                    'elements' => [
                        [
                            'id' => 'el_container_tc',
                            'type' => 'container',
                            'transform' => [
                                'x' => 45,
                                'y' => 50,
                                'width' => 300,
                                'height' => 200,
                                'rotation' => 0,
                                'zIndex' => 1,
                            ],
                            'positionConstraint' => [
                                'mode' => 'absolute',
                                'horizontal' => 'center',
                                'vertical' => 'top',
                                'horizontalOffset' => 0,
                                'verticalOffset' => 50,
                            ],
                            'layout' => [
                                'enabled' => true,
                                'mode' => 'responsive',
                                'direction' => 'vertical',
                                'gap' => 16,
                                'padding' => ['top' => 16, 'right' => 16, 'bottom' => 16, 'left' => 16],
                                'align' => 'center',
                                'distribution' => 'start',
                                'wrap' => 'nowrap',
                                'widthSizing' => 'fixed',
                                'heightSizing' => 'hug',
                            ],
                            'children' => [
                                [
                                    'id' => 'child_btn_1',
                                    'type' => 'button',
                                    'transform' => ['x' => 16, 'y' => 16, 'width' => 268, 'height' => 44, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => [],
                                    'props' => ['label' => 'Button 1'],
                                ],
                                [
                                    'id' => 'child_btn_2',
                                    'type' => 'button',
                                    'transform' => ['x' => 16, 'y' => 76, 'width' => 268, 'height' => 44, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => [],
                                    'props' => ['label' => 'Button 2'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.id', 'el_container_tc')
            ->assertJsonPath('data.schema.sections.0.elements.0.positionConstraint.horizontal', 'center')
            ->assertJsonPath('data.schema.sections.0.elements.0.positionConstraint.vertical', 'top')
            ->assertJsonPath('data.schema.sections.0.elements.0.layout.mode', 'responsive')
            ->assertJsonPath('data.schema.sections.0.elements.0.layout.direction', 'vertical')
            ->assertJsonCount(2, 'data.schema.sections.0.elements.0.children');
    }

    public function test_live_streaming_in_auto_layout_persists_and_sizes_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_streaming_flow',
                    'name' => 'Siaran Langsung',
                    'type' => 'event',
                    'height' => 844,
                    'heightMode' => 'fit-content',
                    'elements' => [
                        [
                            'id' => 'container_flow',
                            'name' => 'Auto Layout Column',
                            'type' => 'container',
                            'transform' => ['x' => 20, 'y' => 40, 'width' => 350, 'height' => 600, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [],
                            'layout' => [
                                'enabled' => true,
                                'mode' => 'responsive',
                                'direction' => 'vertical',
                                'gap' => 24,
                                'padding' => ['top' => 20, 'right' => 20, 'bottom' => 20, 'left' => 20],
                                'align' => 'stretch',
                                'distribution' => 'start',
                                'wrap' => 'nowrap',
                                'widthSizing' => 'fill',
                                'heightSizing' => 'hug',
                            ],
                            'children' => [
                                [
                                    'id' => 'flow_text',
                                    'type' => 'text',
                                    'transform' => ['x' => 20, 'y' => 20, 'width' => 310, 'height' => 28, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => [],
                                    'props' => ['text' => 'Live Streaming Pernikahan'],
                                ],
                                [
                                    'id' => 'flow_streaming',
                                    'type' => 'streaming',
                                    'transform' => ['x' => 20, 'y' => 72, 'width' => 310, 'height' => 260, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => [],
                                    'props' => [
                                        'platform' => 'youtube',
                                        'streamUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                                        'title' => 'Akad Nikah Virtual',
                                        'description' => 'Saksikan siaran langsung akad nikah kami.',
                                        'buttonLabel' => 'Saksikan Siaran',
                                        'isLive' => true,
                                    ],
                                ],
                                [
                                    'id' => 'flow_button',
                                    'type' => 'button',
                                    'transform' => ['x' => 20, 'y' => 356, 'width' => 310, 'height' => 44, 'rotation' => 0, 'zIndex' => 3],
                                    'style' => [],
                                    'props' => ['label' => 'Buka Maps Lokasi'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.children.1.type', 'streaming')
            ->assertJsonPath('data.schema.sections.0.elements.0.children.1.props.platform', 'youtube')
            ->assertJsonPath('data.schema.sections.0.elements.0.children.2.type', 'button')
            ->assertJsonCount(3, 'data.schema.sections.0.elements.0.children');
    }

    public function test_anchor_cc_with_fill_and_hug_widgets_persists_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_anchor_test',
                    'name' => 'Anchor Responsive Test',
                    'type' => 'event',
                    'height' => 844,
                    'elements' => [
                        [
                            'id' => 'el_btn_cc',
                            'name' => 'Center Button',
                            'type' => 'button',
                            'transform' => ['x' => 20, 'y' => 100, 'width' => 350, 'height' => 44, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [],
                            'positionConstraint' => [
                                'mode' => 'absolute',
                                'horizontal' => 'center',
                                'vertical' => 'center',
                                'horizontalOffset' => 0,
                                'verticalOffset' => 0,
                            ],
                            'widthMode' => 'fill',
                            'props' => ['label' => 'Buka Undangan'],
                        ],
                        [
                            'id' => 'el_loc_cc',
                            'name' => 'Center Location',
                            'type' => 'location',
                            'transform' => ['x' => 20, 'y' => 200, 'width' => 350, 'height' => 240, 'rotation' => 0, 'zIndex' => 2],
                            'positionConstraint' => [
                                'mode' => 'absolute',
                                'horizontal' => 'center',
                                'vertical' => 'center',
                                'horizontalOffset' => 0,
                                'verticalOffset' => 0,
                            ],
                            'widthMode' => 'fill',
                            'props' => [
                                'venueName' => 'Grand Ballroom Hotel Mulia',
                                'address' => 'Jl. Asia Afrika Senayan, Jakarta Pusat',
                            ],
                        ],
                        [
                            'id' => 'el_stream_cc',
                            'name' => 'Center Streaming',
                            'type' => 'streaming',
                            'transform' => ['x' => 20, 'y' => 480, 'width' => 350, 'height' => 280, 'rotation' => 0, 'zIndex' => 3],
                            'positionConstraint' => [
                                'mode' => 'absolute',
                                'horizontal' => 'center',
                                'vertical' => 'center',
                                'horizontalOffset' => 0,
                                'verticalOffset' => 0,
                            ],
                            'widthMode' => 'fill',
                            'props' => [
                                'platform' => 'youtube',
                                'streamUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.positionConstraint.horizontal', 'center')
            ->assertJsonPath('data.schema.sections.0.elements.0.positionConstraint.vertical', 'center')
            ->assertJsonPath('data.schema.sections.0.elements.0.widthMode', 'fill')
            ->assertJsonPath('data.schema.sections.0.elements.1.positionConstraint.horizontal', 'center')
            ->assertJsonPath('data.schema.sections.0.elements.2.positionConstraint.horizontal', 'center');
    }

    public function test_gallery_widget_5_layouts_and_auto_layout_persists_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_gallery',
                    'name' => 'Gallery Section',
                    'type' => 'gallery',
                    'height' => 844,
                    'elements' => [
                        [
                            'id' => 'el_gallery_1',
                            'name' => 'Galeri Foto Modern',
                            'type' => 'gallery',
                            'transform' => ['x' => 20, 'y' => 50, 'width' => 350, 'height' => 340, 'rotation' => 0, 'zIndex' => 1],
                            'widthMode' => 'fill',
                            'heightMode' => 'hug',
                            'props' => [
                                'layoutStyle' => 'masonry',
                                'columns' => 2,
                                'gap' => 8,
                                'showCaption' => true,
                                'enableLightbox' => true,
                                'images' => [
                                    [
                                        'id' => 'g_1',
                                        'url' => 'https://images.unsplash.com/photo-1519741497674-611481863552',
                                        'caption' => 'Akad Nikah',
                                        'alt' => 'Momen Akad',
                                    ],
                                    [
                                        'id' => 'g_2',
                                        'url' => 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc',
                                        'caption' => 'Resepsi Bahagia',
                                        'alt' => 'Momen Resepsi',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'gallery')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.layoutStyle', 'masonry')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.images.0.caption', 'Akad Nikah')
            ->assertJsonCount(2, 'data.schema.sections.0.elements.0.props.images');
    }

    public function test_youtube_widget_canonical_and_auto_layout_persists_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_video',
                    'name' => 'Video Section',
                    'type' => 'custom',
                    'height' => 600,
                    'elements' => [
                        [
                            'id' => 'el_yt_1',
                            'name' => 'YouTube Wedding Film',
                            'type' => 'video',
                            'transform' => ['x' => 20, 'y' => 50, 'width' => 350, 'height' => 240, 'rotation' => 0, 'zIndex' => 1],
                            'widthMode' => 'fill',
                            'heightMode' => 'hug',
                            'props' => [
                                'youtubeUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                                'videoId' => 'dQw4w9WgXcQ',
                                'title' => 'Our Prewedding Highlights',
                                'description' => 'Momen kebersamaan tak terlupakan.',
                                'showTitle' => true,
                                'showDescription' => true,
                                'aspectRatio' => '16/9',
                                'showControls' => true,
                                'playIcon' => 'youtube',
                                'iconSize' => 48,
                                'playerRadius' => 12,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'video')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.videoId', 'dQw4w9WgXcQ')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.title', 'Our Prewedding Highlights')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.aspectRatio', '16/9')
            ->assertJsonPath('data.schema.sections.0.elements.0.widthMode', 'fill')
            ->assertJsonPath('data.schema.sections.0.elements.0.heightMode', 'hug');
    }

    public function test_countdown_widget_3_themes_and_auto_layout_persists_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_countdown',
                    'name' => 'Countdown Section',
                    'type' => 'custom',
                    'height' => 500,
                    'elements' => [
                        [
                            'id' => 'el_cd_1',
                            'name' => 'Wedding Countdown',
                            'type' => 'countdown',
                            'transform' => ['x' => 20, 'y' => 50, 'width' => 350, 'height' => 120, 'rotation' => 0, 'zIndex' => 1],
                            'widthMode' => 'fill',
                            'heightMode' => 'hug',
                            'props' => [
                                'theme' => 'luxury',
                                'targetDate' => '2026-11-15',
                                'targetTime' => '08:00',
                                'daysLabel' => 'DAYS',
                                'hoursLabel' => 'HOURS',
                                'minutesLabel' => 'MINS',
                                'secondsLabel' => 'SECS',
                                'endedMessage' => 'The Royal Wedding Has Arrived',
                                'showSaveDate' => true,
                                'saveDateLabel' => 'Simpan Tanggal',
                                'unitRadius' => 10,
                                'unitBg' => '#FFFFFF',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'countdown')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.theme', 'luxury')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.targetDate', '2026-11-15')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.daysLabel', 'DAYS')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.showSaveDate', true)
            ->assertJsonPath('data.schema.sections.0.elements.0.widthMode', 'fill')
            ->assertJsonPath('data.schema.sections.0.elements.0.heightMode', 'hug');
    }

    public function test_rsvp_widget_3_themes_and_auto_layout_persists_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_rsvp',
                    'name' => 'RSVP Section',
                    'type' => 'rsvp',
                    'height' => 500,
                    'elements' => [
                        [
                            'id' => 'el_rsvp_1',
                            'name' => 'Formulir RSVP',
                            'type' => 'rsvp',
                            'transform' => ['x' => 20, 'y' => 50, 'width' => 350, 'height' => 380, 'rotation' => 0, 'zIndex' => 1],
                            'widthMode' => 'fill',
                            'heightMode' => 'hug',
                            'props' => [
                                'theme' => 'elegant',
                                'title' => 'Konfirmasi Kehadiran',
                                'subtitle' => 'Mohon konfirmasi kehadiran Anda',
                                'nameLabel' => 'Nama Lengkap',
                                'attendanceLabel' => 'Konfirmasi Kehadiran',
                                'attendingText' => 'Hadir',
                                'declinedText' => 'Maaf, Tidak Hadir',
                                'guestCountLabel' => 'Jumlah Tamu',
                                'wishesLabel' => 'Ucapan & Doa',
                                'submitLabel' => 'Kirim Konfirmasi',
                                'showSubtitle' => true,
                                'showGuestCount' => true,
                                'showWishes' => true,
                                'inputRadius' => 8,
                                'btnBg' => '#18181B',
                                'btnColor' => '#FFFFFF',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'rsvp')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.theme', 'elegant')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.title', 'Konfirmasi Kehadiran')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.attendingText', 'Hadir')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.showWishes', true)
            ->assertJsonPath('data.schema.sections.0.elements.0.widthMode', 'fill')
            ->assertJsonPath('data.schema.sections.0.elements.0.heightMode', 'hug');
    }

    public function test_greeting_widget_3_themes_and_auto_layout_persists_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_wishes',
                    'name' => 'Wishes Section',
                    'type' => 'greeting',
                    'height' => 500,
                    'elements' => [
                        [
                            'id' => 'el_greet_1',
                            'name' => 'Kartu Ucapan',
                            'type' => 'greeting',
                            'transform' => ['x' => 20, 'y' => 50, 'width' => 350, 'height' => 380, 'rotation' => 0, 'zIndex' => 1],
                            'widthMode' => 'fill',
                            'heightMode' => 'hug',
                            'props' => [
                                'theme' => 'luxury',
                                'title' => 'Wishes & Prayers',
                                'subtitle' => 'Sampaikan doa restu terbaik untuk kedua mempelai',
                                'showForm' => true,
                                'showWishesList' => true,
                                'showTimestamp' => true,
                                'showGuestName' => true,
                                'submitLabel' => 'Kirim Doa',
                                'maxVisibleWishes' => 5,
                                'sortOrder' => 'latest',
                                'cardRadius' => 12,
                                'cardBg' => '#FFFFFF',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'greeting')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.theme', 'luxury')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.title', 'Wishes & Prayers')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.showForm', true)
            ->assertJsonPath('data.schema.sections.0.elements.0.props.showWishesList', true)
            ->assertJsonPath('data.schema.sections.0.elements.0.widthMode', 'fill')
            ->assertJsonPath('data.schema.sections.0.elements.0.heightMode', 'hug');
    }

    public function test_digital_gift_widget_3_themes_and_auto_layout_persists_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_gift',
                    'name' => 'Gift Section',
                    'type' => 'gift',
                    'height' => 550,
                    'elements' => [
                        [
                            'id' => 'el_gift_1',
                            'name' => 'Amplop Digital & Kado',
                            'type' => 'gift',
                            'transform' => ['x' => 20, 'y' => 50, 'width' => 350, 'height' => 400, 'rotation' => 0, 'zIndex' => 1],
                            'widthMode' => 'fill',
                            'heightMode' => 'hug',
                            'props' => [
                                'theme' => 'luxury',
                                'title' => 'WEDDING GIFT & REGISTRY',
                                'subtitle' => 'Pilihan tanda kasih pernikahan',
                                'showTitle' => true,
                                'showSubtitle' => true,
                                'copySuccessText' => 'TERSALIN',
                                'items' => [
                                    ['id' => 'it_1', 'type' => 'bank', 'title' => 'Bank BCA', 'value' => '1234567890', 'holderName' => 'Rizal Efendi', 'isVisible' => true],
                                    ['id' => 'it_2', 'type' => 'ewallet', 'title' => 'DANA', 'value' => '081234567890', 'holderName' => 'Rizal Efendi', 'isVisible' => true],
                                    ['id' => 'it_3', 'type' => 'qris', 'title' => 'QRIS', 'value' => 'Scan QRIS', 'qrUrl' => 'https://example.com/qr.png', 'holderName' => 'Rizal & Pasangan', 'isVisible' => true],
                                    ['id' => 'it_4', 'type' => 'address', 'title' => 'Kado Fisik', 'value' => 'Jl. Melati No. 45 Jakarta', 'holderName' => 'Penerima: Rizal', 'isVisible' => true],
                                ],
                                'itemRadius' => 12,
                                'btnBg' => '#292524',
                                'btnColor' => '#FAF7F2',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'gift')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.theme', 'luxury')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.title', 'WEDDING GIFT & REGISTRY')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.items.0.type', 'bank')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.items.1.type', 'ewallet')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.items.2.type', 'qris')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.items.3.type', 'address')
            ->assertJsonPath('data.schema.sections.0.elements.0.widthMode', 'fill')
            ->assertJsonPath('data.schema.sections.0.elements.0.heightMode', 'hug');
    }

    public function test_global_music_widget_3_shapes_persists_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 844,
                    'elements' => [
                        [
                            'id' => 'el_music_1',
                            'name' => 'Pemutar Musik Global',
                            'type' => 'music',
                            'transform' => ['x' => 318, 'y' => 772, 'width' => 48, 'height' => 48, 'rotation' => 0, 'zIndex' => 40],
                            'props' => [
                                'positionMode' => 'global-floating',
                                'shape' => 'pill',
                                'title' => 'Beautiful In White',
                                'artist' => 'Shane Filan',
                                'audioUrl' => 'https://cdn.pixabay.com/download/audio/2022/05/27/audio_1808fbf07a.mp3?filename=romantic-wedding-113095.mp3',
                                'autoplay' => true,
                                'loop' => true,
                                'volume' => 0.85,
                                'showTitle' => true,
                                'showArtist' => true,
                                'icon' => 'music',
                                'animation' => 'rotate',
                                'position' => [
                                    'horizontal' => 'right',
                                    'vertical' => 'bottom',
                                    'offsetX' => 24,
                                    'offsetY' => 24,
                                ],
                                'horizontalAlign' => 'right',
                                'verticalAlign' => 'bottom',
                                'horizontalOffset' => 24,
                                'verticalOffset' => 24,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'music')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.positionMode', 'global-floating')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.shape', 'pill')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.title', 'Beautiful In White')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.artist', 'Shane Filan')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.autoplay', true)
            ->assertJsonPath('data.schema.sections.0.elements.0.props.loop', true)
            ->assertJsonPath('data.schema.sections.0.elements.0.props.volume', 0.85)
            ->assertJsonPath('data.schema.sections.0.elements.0.props.icon', 'music')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.position.horizontal', 'right')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.position.vertical', 'bottom')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.animation', 'rotate');
    }

    public function test_responsive_music_widget_mobile_and_desktop_positions_and_shapes_persist_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 844,
                    'elements' => [
                        [
                            'id' => 'el_music_1',
                            'name' => 'Pemutar Musik Global',
                            'type' => 'music',
                            'transform' => ['x' => 318, 'y' => 772, 'width' => 48, 'height' => 48, 'rotation' => 0, 'zIndex' => 40],
                            'props' => [
                                'positionMode' => 'global-floating',
                                'audioUrl' => 'https://cdn.pixabay.com/audio/sample.mp3',
                                'title' => 'A Thousand Years',
                                'artist' => 'Christina Perri',
                                'autoplay' => true,
                                'loop' => true,
                                'volume' => 0.8,
                                'shape' => 'circle',
                                'horizontalAlign' => 'right',
                                'verticalAlign' => 'bottom',
                                'horizontalOffset' => 24,
                                'verticalOffset' => 24,
                                'mobile' => [
                                    'shape' => 'circle',
                                    'horizontalAlign' => 'right',
                                    'verticalAlign' => 'bottom',
                                    'horizontalOffset' => 24,
                                    'verticalOffset' => 24,
                                ],
                                'desktop' => [
                                    'shape' => 'pill',
                                    'horizontalAlign' => 'right',
                                    'verticalAlign' => 'top',
                                    'horizontalOffset' => 32,
                                    'verticalOffset' => 32,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'music')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.mobile.shape', 'circle')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.mobile.horizontalAlign', 'right')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.mobile.verticalAlign', 'bottom')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.desktop.shape', 'pill')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.desktop.horizontalAlign', 'right')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.desktop.verticalAlign', 'top')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.desktop.horizontalOffset', 32)
            ->assertJsonPath('data.schema.sections.0.elements.0.props.desktop.verticalOffset', 32);
    }

    public function test_desktop_cover_persists_as_dedicated_schema_property(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create(['user_id' => $admin->id]);

        $schema = [
            'schemaVersion' => 1,
            'metadata' => [
                'id' => 'tpl_desktop_cover_test',
                'name' => 'Template with Desktop Cover',
                'category' => 'luxury',
            ],
            'viewport' => ['baseWidth' => 390, 'contentWidth' => 390, 'baseUnit' => 8],
            'theme' => ['colors' => ['primary' => '#10B981', 'background' => '#0F172A']],
            'desktopCover' => [
                'enabled' => true,
                'widthRatio' => 40,
                'backgroundColor' => '#0F172A',
                'backgroundOpacity' => 100,
                'layout' => [
                    'enabled' => true,
                    'direction' => 'vertical',
                    'gap' => 20,
                    'padding' => ['top' => 48, 'right' => 32, 'bottom' => 48, 'left' => 32],
                    'align' => 'center',
                    'distribution' => 'center',
                    'widthSizing' => 'fill',
                    'heightSizing' => 'fill',
                ],
                'elements' => [
                    [
                        'id' => 'el_dc_greeting_1',
                        'name' => 'Salam Pembuka',
                        'type' => 'text',
                        'transform' => ['x' => 0, 'y' => 0, 'width' => 320, 'height' => 28, 'rotation' => 0, 'zIndex' => 1],
                        'style' => ['fontSize' => 13, 'fontWeight' => '600', 'color' => '#94A3B8', 'textAlign' => 'center'],
                        'props' => ['content' => 'The Wedding Celebration of'],
                        'bindingKey' => 'customContent.coverGreeting',
                    ],
                    [
                        'id' => 'el_dc_photo_1',
                        'name' => 'Foto Pasangan',
                        'type' => 'image',
                        'transform' => ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 200, 'rotation' => 0, 'zIndex' => 2],
                        'style' => ['borderRadius' => 100, 'objectFit' => 'cover'],
                        'props' => ['url' => 'https://example.com/photo.jpg', 'alt' => 'Foto Pasangan'],
                        'bindingKey' => 'couple.couplePhotoUrl',
                    ],
                    [
                        'id' => 'el_dc_names_1',
                        'name' => 'Nama Mempelai',
                        'type' => 'text',
                        'transform' => ['x' => 0, 'y' => 0, 'width' => 340, 'height' => 44, 'rotation' => 0, 'zIndex' => 3],
                        'style' => ['fontSize' => 24, 'fontWeight' => '700', 'color' => '#F8FAFC', 'textAlign' => 'center'],
                        'props' => ['content' => 'Alya & Raka'],
                        'bindingKey' => 'bride_name',
                    ],
                    [
                        'id' => 'el_dc_btn_1',
                        'name' => 'Tombol Buka Undangan',
                        'type' => 'button',
                        'transform' => ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 44, 'rotation' => 0, 'zIndex' => 4],
                        'style' => ['backgroundColor' => '#10B981', 'color' => '#0F172A', 'borderRadius' => 22],
                        'props' => ['label' => 'Buka Undangan', 'actionType' => 'open-invitation'],
                    ],
                ],
            ],
            'sections' => [
                [
                    'id' => 'sec_mempelai_1',
                    'name' => 'Mempelai',
                    'type' => 'couple',
                    'height' => 844,
                    'heightMode' => 'fit-content',
                    'elements' => [],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.desktopCover.enabled', true)
            ->assertJsonPath('data.schema.desktopCover.widthRatio', 40)
            ->assertJsonPath('data.schema.desktopCover.backgroundColor', '#0F172A')
            ->assertJsonPath('data.schema.desktopCover.elements.0.id', 'el_dc_greeting_1')
            ->assertJsonPath('data.schema.desktopCover.elements.1.id', 'el_dc_photo_1')
            ->assertJsonPath('data.schema.desktopCover.elements.2.id', 'el_dc_names_1')
            ->assertJsonPath('data.schema.desktopCover.elements.3.props.actionType', 'open-invitation')
            ->assertJsonCount(4, 'data.schema.desktopCover.elements')
            ->assertJsonCount(1, 'data.schema.sections');
    }

    public function test_love_story_widget_5_themes_and_dynamic_items_persist_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'metadata' => [
                'id' => 'wedding_story_' . $wedding->id,
                'name' => 'Undangan dengan Kisah Cinta',
            ],
            'sections' => [
                [
                    'id' => 'sec_love_story',
                    'name' => 'Cerita Kami',
                    'type' => 'story',
                    'height' => 880,
                    'heightMode' => 'fit-content',
                    'elements' => [
                        [
                            'id' => 'el_story_editorial',
                            'name' => 'Kisah Cinta Editorial',
                            'type' => 'story',
                            'transform' => ['x' => 20, 'y' => 20, 'width' => 350, 'height' => 840, 'rotation' => 0, 'zIndex' => 1],
                            'style' => ['backgroundColor' => 'transparent'],
                            'props' => [
                                'theme' => 'editorial-timeline',
                                'variant' => 'editorial-timeline',
                                'containerGap' => 36,
                                'timelineColor' => '#10B981',
                                'dotColor' => '#10B981',
                                'items' => [
                                    [
                                        'id' => 'item_1',
                                        'title' => 'Pertama Bertemu',
                                        'description' => 'Kami bertemu pertama kali di kafe kecil.',
                                        'date' => '12 Juni 2020',
                                        'location' => 'Jakarta',
                                        'label' => 'Awal Cerita',
                                        'imageUrl' => 'https://images.unsplash.com/photo-1519741497674-611481863552',
                                        'hidden' => false,
                                    ],
                                    [
                                        'id' => 'item_2',
                                        'title' => 'Mulai Bersama',
                                        'description' => 'Kami memulai perjalanan berdua.',
                                        'date' => '15 Agustus 2022',
                                        'location' => 'Bandung',
                                        'label' => 'Komitmen',
                                        'imageUrl' => 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc',
                                        'hidden' => false,
                                    ],
                                    [
                                        'id' => 'item_3',
                                        'title' => 'Menuju Pelaminan',
                                        'description' => 'Kini kami siap melangkah ke jenjang pernikahan.',
                                        'date' => '15 November 2026',
                                        'location' => 'Bali',
                                        'label' => 'Hari Bahagia',
                                        'imageUrl' => 'https://images.unsplash.com/photo-1583939003579-730e3918a45a',
                                        'hidden' => false,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'story')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.theme', 'editorial-timeline')
            ->assertJsonCount(3, 'data.schema.sections.0.elements.0.props.items')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.items.0.title', 'Pertama Bertemu')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.items.1.date', '15 Agustus 2022')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.items.2.location', 'Bali');
    }

    public function test_love_story_tablet_adaptive_properties_persist_intact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'metadata' => [
                'id' => 'wedding_story_tablet_' . $wedding->id,
                'name' => 'Undangan dengan Story Tablet Adaptive',
            ],
            'sections' => [
                [
                    'id' => 'sec_story_adaptive',
                    'name' => 'Story Adaptive Section',
                    'type' => 'story',
                    'height' => 700,
                    'heightMode' => 'fit-content',
                    'elements' => [
                        [
                            'id' => 'el_story_alternating',
                            'name' => 'Kisah Alternating',
                            'type' => 'story',
                            'transform' => ['x' => 20, 'y' => 20, 'width' => 768, 'height' => 640, 'rotation' => 0, 'zIndex' => 1],
                            'style' => ['backgroundColor' => 'transparent'],
                            'props' => [
                                'theme' => 'alternating-journey',
                                'variant' => 'alternating-journey',
                                'layoutMode' => 'auto',
                                'containerGap' => 24,
                                'mobileGap' => 16,
                                'tabletGap' => 24,
                                'desktopGap' => 32,
                                'items' => [
                                    [
                                        'id' => 'alt_1',
                                        'title' => 'Pertemuan di Kampus',
                                        'description' => 'Momen berkesan di perpustakaan kampus.',
                                        'date' => '2019',
                                        'location' => 'Yogyakarta',
                                        'label' => 'Masa Kuliah',
                                        'imageUrl' => 'https://images.unsplash.com/photo-1519741497674-611481863552',
                                        'hidden' => false,
                                    ],
                                    [
                                        'id' => 'alt_2',
                                        'title' => 'Wisuda Bersama',
                                        'description' => 'Merayakan kelulusan bersama keluarga.',
                                        'date' => '2022',
                                        'location' => 'Yogyakarta',
                                        'label' => 'Kelulusan',
                                        'imageUrl' => 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc',
                                        'hidden' => false,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'story')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.theme', 'alternating-journey')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.layoutMode', 'auto')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.tabletGap', 24)
            ->assertJsonPath('data.schema.sections.0.elements.0.props.desktopGap', 32)
            ->assertJsonCount(2, 'data.schema.sections.0.elements.0.props.items');
    }

    public function test_svg_asset_color_customization_persists_accurately(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_ornaments',
                    'name' => 'Sampul & Ornamen',
                    'type' => 'cover',
                    'height' => 844,
                    'elements' => [
                        [
                            'id' => 'el_svg_ornament_1',
                            'name' => 'Gold Floral Corner',
                            'type' => 'ornament',
                            'transform' => ['x' => 0, 'y' => 0, 'width' => 120, 'height' => 120, 'rotation' => 0, 'zIndex' => 2],
                            'style' => ['objectFit' => 'contain'],
                            'props' => [
                                'url' => '/storage/assets/floral-corner.svg',
                                'assetType' => 'svg',
                                'name' => 'floral-corner.svg',
                            ],
                            'svg' => [
                                'colorMode' => 'custom',
                                'color' => '#1ED760',
                                'opacity' => 0.85,
                                'strokeColor' => '#C5A880',
                                'strokeWidth' => 2,
                                'customColors' => [
                                    '#000000' => '#FFFFFF',
                                    '#1ED760' => '#10B981',
                                ],
                            ],
                        ],
                        [
                            'id' => 'el_svg_monochrome_2',
                            'name' => 'Monochrome Divider',
                            'type' => 'image',
                            'transform' => ['x' => 20, 'y' => 200, 'width' => 350, 'height' => 30, 'rotation' => 0, 'zIndex' => 3],
                            'style' => ['objectFit' => 'contain'],
                            'props' => [
                                'url' => '/storage/assets/divider.svg',
                                'assetType' => 'vector',
                            ],
                            'svg' => [
                                'colorMode' => 'monochrome',
                                'color' => '#C5A880',
                                'opacity' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.svg.colorMode', 'custom')
            ->assertJsonPath('data.schema.sections.0.elements.0.svg.color', '#1ED760')
            ->assertJsonPath('data.schema.sections.0.elements.0.svg.opacity', 0.85)
            ->assertJsonPath('data.schema.sections.0.elements.0.svg.strokeColor', '#C5A880')
            ->assertJsonPath('data.schema.sections.0.elements.0.svg.strokeWidth', 2)
            ->assertJsonPath('data.schema.sections.0.elements.0.svg.customColors.#000000', '#FFFFFF')
            ->assertJsonPath('data.schema.sections.0.elements.1.svg.colorMode', 'monochrome')
            ->assertJsonPath('data.schema.sections.0.elements.1.svg.color', '#C5A880');
    }

    public function test_global_color_palette_system_persists_intact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'theme' => [
                'paletteId' => 'emerald-elegance',
                'colors' => [
                    'background' => '#F7F8F4',
                    'primary' => '#1B4332',
                    'secondary' => '#52796F',
                    'accent' => '#D4AF37',
                    'text' => '#1F2933',
                ],
            ],
            'sections' => [
                [
                    'id' => 'sec_main',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 844,
                    'backgroundColor' => 'token:background',
                    'elements' => [
                        [
                            'id' => 'el_heading_1',
                            'name' => 'Bride Groom Heading',
                            'type' => 'text',
                            'transform' => ['x' => 20, 'y' => 100, 'width' => 350, 'height' => 50, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [
                                'color' => 'token:primary',
                                'fontSize' => 28,
                            ],
                            'props' => ['text' => 'Alya & Raka'],
                        ],
                        [
                            'id' => 'el_btn_1',
                            'name' => 'Action Button',
                            'type' => 'button',
                            'transform' => ['x' => 95, 'y' => 300, 'width' => 200, 'height' => 48, 'rotation' => 0, 'zIndex' => 2],
                            'style' => [
                                'backgroundColor' => 'token:accent',
                                'color' => 'token:primary',
                            ],
                            'props' => ['label' => 'Buka Undangan'],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.theme.paletteId', 'emerald-elegance')
            ->assertJsonPath('data.schema.theme.colors.primary', '#1B4332')
            ->assertJsonPath('data.schema.theme.colors.accent', '#D4AF37')
            ->assertJsonPath('data.schema.sections.0.backgroundColor', 'token:background')
            ->assertJsonPath('data.schema.sections.0.elements.0.style.color', 'token:primary')
            ->assertJsonPath('data.schema.sections.0.elements.1.style.backgroundColor', 'token:accent');
    }

    public function test_studio_design_supports_image_frame_element_across_all_layouts_and_auto_layout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'metadata' => [
                'id' => 'frame-design-test',
                'name' => 'Image Frame Design',
            ],
            'theme' => [
                'paletteId' => 'emerald-elegance',
                'colors' => [
                    'primary' => '#1B4332',
                    'accent' => '#D4AF37',
                ],
            ],
            'sections' => [
                [
                    'id' => 'sec_couple_frames',
                    'name' => 'Mempelai Frames Section',
                    'height' => 900,
                    'elements' => [
                        [
                            'id' => 'el_frame_arch',
                            'type' => 'imageFrame',
                            'name' => 'Arch Cathedral',
                            'transform' => ['x' => 20, 'y' => 50, 'width' => 240, 'height' => 320, 'rotation' => 0, 'zIndex' => 1],
                            'props' => [
                                'layout' => 'arch',
                                'url' => 'https://example.com/groom.jpg',
                                'alt' => 'Foto Pengantin Pria',
                                'objectFit' => 'cover',
                                'focalPoint' => ['x' => 50, 'y' => 45],
                                'borderStyle' => 'solid',
                                'borderColor' => 'token:primary',
                                'borderWidth' => 2,
                                'borderRadius' => 8,
                            ],
                        ],
                        [
                            'id' => 'el_frame_polaroid',
                            'type' => 'imageFrame',
                            'name' => 'Polaroid Card',
                            'transform' => ['x' => 20, 'y' => 400, 'width' => 240, 'height' => 310, 'rotation' => 0, 'zIndex' => 2],
                            'props' => [
                                'layout' => 'polaroid',
                                'url' => 'https://example.com/bride.jpg',
                                'alt' => 'Foto Pengantin Wanita',
                                'showCaption' => true,
                                'caption' => 'The Beautiful Bride',
                                'captionFontFamily' => 'Playfair Display, serif',
                                'captionFontSize' => 14,
                                'captionColor' => '#4F5752',
                            ],
                        ],
                        [
                            'id' => 'el_frame_botanical',
                            'type' => 'imageFrame',
                            'name' => 'Botanical Floral',
                            'transform' => ['x' => 20, 'y' => 740, 'width' => 240, 'height' => 300, 'rotation' => 0, 'zIndex' => 3],
                            'props' => [
                                'layout' => 'botanical',
                                'url' => 'https://example.com/couple.jpg',
                                'ornamentColor' => 'token:accent',
                                'borderStyle' => 'solid',
                                'borderColor' => 'token:primary',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.type', 'imageFrame')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.layout', 'arch')
            ->assertJsonPath('data.schema.sections.0.elements.0.props.focalPoint.y', 45)
            ->assertJsonPath('data.schema.sections.0.elements.1.type', 'imageFrame')
            ->assertJsonPath('data.schema.sections.0.elements.1.props.layout', 'polaroid')
            ->assertJsonPath('data.schema.sections.0.elements.1.props.caption', 'The Beautiful Bride')
            ->assertJsonPath('data.schema.sections.0.elements.2.type', 'imageFrame')
            ->assertJsonPath('data.schema.sections.0.elements.2.props.layout', 'botanical')
            ->assertJsonPath('data.schema.sections.0.elements.2.props.ornamentColor', 'token:accent');
    }

    public function test_looping_ambient_ornament_animation_persists_intact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'sections' => [
                [
                    'id' => 'sec_ornaments',
                    'name' => 'Dekorasi Bunga',
                    'type' => 'general',
                    'height' => 800,
                    'elements' => [
                        [
                            'id' => 'el_flower_left',
                            'name' => 'Flower Left',
                            'type' => 'ornament',
                            'transform' => ['x' => 20, 'y' => 40, 'width' => 120, 'height' => 120, 'rotation' => 0, 'zIndex' => 2],
                            'style' => [],
                            'props' => ['url' => '/assets/flower-left.svg'],
                            'animation' => [
                                'enabled' => true,
                                'mode' => 'ambient-loop',
                                'preset' => 'ambient-float-sway',
                                'duration' => 6000,
                                'delay' => 0,
                                'easing' => 'ease-in-out',
                                'iteration' => 'infinite',
                                'ambient' => [
                                    'preset' => 'float-sway',
                                    'intensity' => 1.0,
                                    'phase' => 0,
                                    'direction' => 'alternate',
                                    'iterationCount' => 'infinite',
                                ],
                            ],
                        ],
                        [
                            'id' => 'el_flower_right',
                            'name' => 'Flower Right',
                            'type' => 'ornament',
                            'transform' => ['x' => 250, 'y' => 40, 'width' => 120, 'height' => 120, 'rotation' => 0, 'zIndex' => 2],
                            'style' => [],
                            'props' => ['url' => '/assets/flower-right.svg'],
                            'animation' => [
                                'enabled' => true,
                                'mode' => 'ambient-loop',
                                'preset' => 'ambient-soft-sway',
                                'duration' => 7000,
                                'delay' => 0,
                                'easing' => 'ease-in-out',
                                'iteration' => 'infinite',
                                'ambient' => [
                                    'preset' => 'soft-sway',
                                    'intensity' => 0.8,
                                    'phase' => 50,
                                    'direction' => 'alternate',
                                    'iterationCount' => 'infinite',
                                ],
                            ],
                        ],
                        [
                            'id' => 'el_leaf_bottom',
                            'name' => 'Leaf Bottom',
                            'type' => 'ornament',
                            'transform' => ['x' => 135, 'y' => 600, 'width' => 100, 'height' => 80, 'rotation' => 0, 'zIndex' => 2],
                            'style' => [],
                            'props' => ['url' => '/assets/leaf.svg'],
                            'animation' => [
                                'enabled' => true,
                                'mode' => 'ambient-loop',
                                'preset' => 'ambient-gentle-float',
                                'duration' => 5000,
                                'delay' => 0,
                                'easing' => 'ease-in-out',
                                'iteration' => 'infinite',
                                'ambient' => [
                                    'preset' => 'gentle-float',
                                    'intensity' => 0.5,
                                    'phase' => 25,
                                    'direction' => 'alternate',
                                    'iterationCount' => 'infinite',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.sections.0.elements.0.animation.mode', 'ambient-loop')
            ->assertJsonPath('data.schema.sections.0.elements.0.animation.ambient.preset', 'float-sway')
            ->assertJsonPath('data.schema.sections.0.elements.0.animation.ambient.phase', 0)
            ->assertJsonPath('data.schema.sections.0.elements.1.animation.mode', 'ambient-loop')
            ->assertJsonPath('data.schema.sections.0.elements.1.animation.ambient.preset', 'soft-sway')
            ->assertJsonPath('data.schema.sections.0.elements.1.animation.ambient.phase', 50)
            ->assertJsonPath('data.schema.sections.0.elements.2.animation.mode', 'ambient-loop')
            ->assertJsonPath('data.schema.sections.0.elements.2.animation.ambient.preset', 'gentle-float')
            ->assertJsonPath('data.schema.sections.0.elements.2.animation.ambient.phase', 25);
    }

    public function test_global_typography_system_persists_intact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $wedding = Wedding::factory()->create();

        $schema = [
            'schemaVersion' => 1,
            'theme' => [
                'paletteId' => 'royal-emerald',
                'typography' => [
                    'heading' => 'Playfair Display, serif',
                    'body' => 'Inter, sans-serif',
                    'accent' => 'Alex Brush, cursive',
                ],
            ],
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Sampul',
                    'type' => 'cover',
                    'height' => 600,
                    'elements' => [
                        [
                            'id' => 'el_text_1',
                            'name' => 'Wedding Title',
                            'type' => 'text',
                            'transform' => ['x' => 50, 'y' => 100, 'width' => 290, 'height' => 60, 'rotation' => 0, 'zIndex' => 1],
                            'style' => [
                                'fontFamily' => 'Playfair Display, serif',
                                'fontSize' => 32,
                            ],
                            'props' => ['text' => 'The Wedding of Romeo & Juliet'],
                        ],
                    ],
                ],
            ],
        ];

        $saveRes = $this->actingAs($admin)->putJson('/api/v1/weddings/' . $wedding->id . '/design', [
            'schema' => $schema,
        ]);
        $saveRes->assertStatus(200);

        $fetchRes = $this->actingAs($admin)->getJson('/api/v1/weddings/' . $wedding->id . '/design');
        $fetchRes->assertStatus(200)
            ->assertJsonPath('data.schema.theme.typography.heading', 'Playfair Display, serif')
            ->assertJsonPath('data.schema.theme.typography.body', 'Inter, sans-serif')
            ->assertJsonPath('data.schema.theme.typography.accent', 'Alex Brush, cursive')
            ->assertJsonPath('data.schema.sections.0.elements.0.style.fontFamily', 'Playfair Display, serif');
    }
}






