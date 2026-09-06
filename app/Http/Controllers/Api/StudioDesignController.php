<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDesignSchemaRequest;
use App\Models\Design;
use App\Models\Wedding;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioDesignController extends Controller
{
    use AuthorizesRequests;

    /**
     * Fetch the raw editable Design Schema for Studio.
     */
    public function show(Request $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('view', $wedding);

        if (!$request->user()->isAdmin() && $wedding->user_id !== $request->user()->id) {
            abort(403, 'Akses studio hanya untuk administrator dan pemilik.');
        }

        $design = $wedding->design;

        if (!$design) {
            $design = Design::create([
                'wedding_id' => $wedding->id,
                'schema_version' => 1,
                'version' => 1,
                'schema' => $this->getInitialDesignSchema($wedding),
            ]);
        } elseif (empty($design->schema)) {
            $design->schema = $this->getInitialDesignSchema($wedding);
            $design->save();
        }

        return response()->json([
            'data' => [
                'id' => $design->id,
                'weddingId' => $wedding->id,
                'templateId' => $design->template_id ?? $wedding->applied_template_id,
                'schemaVersion' => $design->schema_version,
                'version' => $design->version,
                'schema' => $design->schema,
                'updatedAt' => $design->updated_at->toIso8601String(),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update the draft Design Schema (Autosave / Manual Save from Studio).
     */
    public function update(UpdateDesignSchemaRequest $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        if (!$request->user()->isAdmin() && $wedding->user_id !== $request->user()->id) {
            abort(403, 'Akses studio hanya untuk administrator dan pemilik.');
        }

        $design = $wedding->design;

        // Optimistic locking conflict check
        if ($design && $request->filled('client_version')) {
            $clientVersion = $request->integer('client_version');
            if ($design->version !== $clientVersion) {
                return response()->json([
                    'error' => [
                        'code' => 'VERSION_CONFLICT',
                        'message' => 'Desain telah dimodifikasi oleh sesi lain. Silakan muat ulang template.',
                        'serverVersion' => $design->version,
                        'clientVersion' => $clientVersion,
                    ],
                ], 409);
            }
        }

        if (!$design) {
            $design = new Design(['wedding_id' => $wedding->id]);
        }

        $design->schema = $request->input('schema');
        $design->schema_version = $request->input('schema.schemaVersion', 1);
        $design->version = ($design->version ?? 0) + 1;
        $design->save();

        return response()->json([
            'data' => [
                'id' => $design->id,
                'version' => $design->version,
                'saved' => true,
                'updatedAt' => $design->updated_at->toIso8601String(),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Generate canonical starting DesignDocument.
     */
    private function getInitialDesignSchema(Wedding $wedding): array
    {
        return [
            'schemaVersion' => 1,
            'metadata' => [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'name' => 'Botanical Serenity - ' . ($wedding->bride_name ?: 'Default Template'),
                'category' => 'botanical',
                'createdAt' => now()->toIso8601String(),
                'updatedAt' => now()->toIso8601String(),
            ],
            'viewport' => [
                'baseWidth' => 1200,
                'contentWidth' => 800,
                'baseUnit' => 8,
            ],
            'theme' => [
                'colors' => [
                    'primary' => '#1ED760',
                    'primaryHover' => '#15803D',
                    'accent' => '#8B9D83',
                    'surfaceBackground' => '#FAFAF9',
                    'surfaceCard' => '#FFFFFF',
                    'surfaceMuted' => '#F4F4F5',
                    'textPrimary' => '#171A18',
                    'textSecondary' => '#52525B',
                    'textMuted' => '#71717A',
                    'borderSubtle' => '#E4E4E7',
                    'borderActive' => '#1ED760',
                ],
                'typography' => [
                    'fontFamilyPrimary' => 'Inter, sans-serif',
                    'fontFamilyHeading' => 'Playfair Display, serif',
                    'baseFontSize' => 16,
                    'lineHeightBase' => 1.5,
                    'lineHeightHeading' => 1.2,
                ],
                'radii' => [
                    'none' => 0,
                    'sm' => 6,
                    'base' => 12,
                    'lg' => 16,
                    'full' => 9999,
                ],
            ],
            'sections' => [
                [
                    'id' => 'sec_cover',
                    'name' => 'Sampul Undangan',
                    'type' => 'cover',
                    'height' => 600,
                    'backgroundColor' => '#FAFAF9',
                    'elements' => [
                        [
                            'id' => 'el_greeting',
                            'type' => 'text',
                            'name' => 'Teks Sapaan',
                            'transform' => ['x' => 250, 'y' => 80, 'width' => 300, 'height' => 30, 'rotation' => 0, 'zIndex' => 1],
                            'style' => ['fontSize' => 14, 'fontWeight' => '600', 'color' => '#1ED760', 'textAlign' => 'center', 'letterSpacing' => '0.1em'],
                            'props' => ['text' => 'THE WEDDING OF', 'bindingKey' => 'customContent.coverGreeting'],
                        ],
                        [
                            'id' => 'el_names',
                            'type' => 'text',
                            'name' => 'Nama Mempelai',
                            'transform' => ['x' => 150, 'y' => 120, 'width' => 500, 'height' => 60, 'rotation' => 0, 'zIndex' => 2],
                            'style' => ['fontSize' => 36, 'fontFamily' => 'Playfair Display, serif', 'fontWeight' => '700', 'color' => '#171A18', 'textAlign' => 'center'],
                            'props' => ['text' => ($wedding->bride_name ?: 'Rina') . ' & ' . ($wedding->groom_name ?: 'Budi'), 'bindingKey' => 'couple.title'],
                        ],
                        [
                            'id' => 'el_btn_open',
                            'type' => 'button',
                            'name' => 'Tombol Buka',
                            'transform' => ['x' => 320, 'y' => 480, 'width' => 160, 'height' => 44, 'rotation' => 0, 'zIndex' => 3],
                            'style' => ['backgroundColor' => '#1ED760', 'color' => '#FFFFFF', 'borderRadius' => 9999, 'fontSize' => 14, 'fontWeight' => '600'],
                            'props' => ['label' => 'Buka Undangan'],
                        ],
                    ],
                ],
                [
                    'id' => 'sec_couple',
                    'name' => 'Mempelai Pengantin',
                    'type' => 'couple',
                    'height' => 500,
                    'backgroundColor' => '#FFFFFF',
                    'elements' => [
                        [
                            'id' => 'el_couple_heading',
                            'type' => 'text',
                            'name' => 'Judul Mempelai',
                            'transform' => ['x' => 250, 'y' => 40, 'width' => 300, 'height' => 40, 'rotation' => 0, 'zIndex' => 1],
                            'style' => ['fontSize' => 24, 'fontFamily' => 'Playfair Display, serif', 'fontWeight' => '700', 'color' => '#171A18', 'textAlign' => 'center'],
                            'props' => ['text' => 'Pasangan Mempelai'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
