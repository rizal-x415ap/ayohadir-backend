<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Design;
use App\Models\Template;
use App\Models\Wedding;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    use AuthorizesRequests;

    /**
     * List templates (Public / User: is_active=true; Admin: all with filter).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Template::query();

        if (!$request->user()?->isAdmin()) {
            $query->where('is_active', true);
        }

        // Support filtering templates specifically designated for landing page showcase
        if ($request->boolean('landing_only')) {
            $landingIdsRaw = \App\Models\AppSetting::get('landing_template_ids', null);
            if ($landingIdsRaw) {
                $landingIds = json_decode($landingIdsRaw, true);
                if (is_array($landingIds) && count($landingIds) > 0) {
                    $query->whereIn('id', $landingIds);
                }
            }
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        $templates = $query->orderBy('order')->orderBy('id')->paginate($request->integer('per_page', 24));

        return TemplateResource::collection($templates);
    }

    /**
     * Store new master template (Admin only).
     */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['contract'] = $data['contract'] ?? [
            'sections' => [
                ['sectionId' => 'sec_cover', 'name' => 'Sampul', 'isRequired' => true, 'isReorderable' => false, 'isRemovable' => false, 'defaultVisible' => true],
                ['sectionId' => 'sec_couple', 'name' => 'Mempelai', 'isRequired' => true, 'isReorderable' => true, 'isRemovable' => false, 'defaultVisible' => true],
                ['sectionId' => 'sec_event', 'name' => 'Acara', 'isRequired' => true, 'isReorderable' => true, 'isRemovable' => false, 'defaultVisible' => true],
                ['sectionId' => 'sec_story', 'name' => 'Cerita Cinta', 'isRequired' => false, 'isReorderable' => true, 'isRemovable' => true, 'defaultVisible' => true],
                ['sectionId' => 'sec_gallery', 'name' => 'Galeri Foto', 'isRequired' => false, 'isReorderable' => true, 'isRemovable' => true, 'defaultVisible' => true],
                ['sectionId' => 'sec_rsvp', 'name' => 'RSVP & Ucapan', 'isRequired' => false, 'isReorderable' => true, 'isRemovable' => true, 'defaultVisible' => true],
            ],
            'editableFields' => [
                'bride_name', 'groom_name', 'bride_parents', 'groom_parents', 'wedding_date', 'venue_name', 'venue_address'
            ],
        ];

        $template = Template::create($data);

        return (new TemplateResource($template))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display a specific template.
     */
    public function show(Template $template): TemplateResource
    {
        return new TemplateResource($template);
    }

    /**
     * Update template (Admin only).
     */
    public function update(UpdateTemplateRequest $request, Template $template): TemplateResource
    {
        $data = $request->validated();
        if ($request->has('schema')) {
            $data['schema'] = $request->input('schema');
        }
        $template->update($data);

        return new TemplateResource($template);
    }

    /**
     * Delete template (Admin only).
     */
    public function destroy(Request $request, Template $template): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            abort(403, 'Akses khusus administrator.');
        }

        $template->delete();

        return response()->json([
            'data' => [
                'deleted' => true,
                'message' => 'Template berhasil dihapus.',
            ],
        ]);
    }

    /**
     * Duplicate template (Admin only).
     */
    public function duplicate(Request $request, Template $template): JsonResponse
    {
        if (!$request->user()?->isAdmin()) {
            abort(403, 'Akses khusus administrator.');
        }

        $cloned = $template->replicate();
        $cloned->name = $template->name . ' (Copy)';
        $cloned->slug = $template->slug . '-copy-' . time();
        $cloned->save();

        return (new TemplateResource($cloned))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Apply master template to wedding design.
     */
    public function applyToWedding(Request $request, Wedding $wedding, Template $template): JsonResponse
    {
        $this->authorize('update', $wedding);

        $oldTemplateId = $wedding->applied_template_id ?: $wedding->design?->template_id;
        $isDifferentTemplate = ((int) $oldTemplateId !== (int) $template->id);

        $wedding->applied_template_id = $template->id;

        // Automatically revert status to draft if template is changed
        if ($isDifferentTemplate && $wedding->status === 'published') {
            $wedding->status = 'draft';
            Cache::forget("public_invitation:{$wedding->slug}");
        }

        // Check if the newly applied template is unlocked for this wedding
        $wedding->is_premium_unlocked = $wedding->isTemplateUnlocked($template, $request->user());
        $wedding->save();

        $design = $wedding->design ?? new Design(['wedding_id' => $wedding->id]);
        $design->template_id = $template->id;
        $design->schema_version = $template->schema_version;
        $design->schema = $template->schema;
        $design->version = ($design->version ?? 0) + 1;
        $design->save();

        return response()->json([
            'data' => [
                'id' => $design->id,
                'weddingId' => $wedding->id,
                'templateId' => $template->id,
                'version' => $design->version,
                'schemaVersion' => $design->schema_version,
                'applied' => true,
                'status' => $wedding->status,
                'isPremiumUnlocked' => (bool) $wedding->is_premium_unlocked,
                'updatedAt' => $design->updated_at->toIso8601String(),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Preview template with sample content publicly.
     */
    public function preview(Request $request, string $slugOrId): JsonResponse
    {
        $cleanSlug = str_replace('tpl_', '', $slugOrId);
        $template = Template::where('slug', $slugOrId)
            ->orWhere('id', is_numeric($slugOrId) ? (int)$slugOrId : 0)
            ->orWhere('slug', $cleanSlug)
            ->orWhere('slug', 'LIKE', '%' . $cleanSlug . '%')
            ->first();

        // Fallback to first active template if not found by exact string
        if (!$template) {
            $template = Template::where('is_active', true)->first();
        }

        if (!$template) {
            abort(404, 'Template tidak ditemukan atau belum aktif.');
        }

        // If inactive, only allow authenticated admin
        if (!$template->is_active && !$request->user('sanctum')?->isAdmin()) {
            abort(404, 'Template tidak ditemukan atau belum aktif.');
        }

        return response()->json([
            'data' => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'category' => $template->category,
                'description' => $template->description,
                'thumbnail' => $template->thumbnail_url,
                'isActive' => $template->is_active,
                'schemaVersion' => $template->schema_version,
                'schema' => $template->schema,
                'contract' => $template->contract,
                'contact' => [
                    'whatsapp_number' => \App\Models\AppSetting::get('admin_whatsapp_number', env('ADMIN_WHATSAPP_NUMBER', '081234567890')),
                    'whatsapp_message' => \App\Models\AppSetting::get('admin_whatsapp_message', "Halo Admin Ayo Hadir, saya tertarik dibuatkan undangan pernikahan menggunakan tema *{template_name}*. Mohon info langkah selanjutnya. Terima kasih!"),
                ],
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
