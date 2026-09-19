<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Media;
use App\Models\Template;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicOgController extends Controller
{
    /**
     * Recursively scan element arrays (at any nesting level: group, container, card, frame, section)
     * for couple photo binding or label.
     */
    private function findCouplePhotoInElements(array $elements): ?string
    {
        foreach ($elements as $el) {
            $bKey = (string) ($el['bindingKey'] ?? $el['props']['bindingKey'] ?? '');
            $bKeyLower = strtolower($bKey);

            if (
                $bKey === 'couple.couplePhotoUrl' ||
                $bKey === 'couple.coverPhoto' ||
                $bKey === 'customContent.coverPhotoUrl' ||
                $bKey === 'customContent.desktopCoverBackgroundPhotoUrl' ||
                str_contains($bKeyLower, 'berdua') ||
                str_contains($bKeyLower, 'foto berdua')
            ) {
                $url = $el['props']['url'] ?? $el['props']['src'] ?? $el['url'] ?? $el['src'] ?? null;
                if (!empty($url)) {
                    return $url;
                }
                $bg = $el['props']['backgroundImage'] ?? $el['props']['backgroundUrl'] ?? $el['backgroundUrl'] ?? null;
                if (!empty($bg)) {
                    return $bg;
                }
            }

            // Check element label or name
            $label = strtolower((string) ($el['label'] ?? $el['name'] ?? ''));
            if (str_contains($label, 'berdua') || str_contains($label, 'foto pasangan')) {
                $url = $el['props']['url'] ?? $el['props']['src'] ?? $el['url'] ?? $el['src'] ?? null;
                if (!empty($url)) {
                    return $url;
                }
                $bg = $el['props']['backgroundImage'] ?? $el['props']['backgroundUrl'] ?? $el['backgroundUrl'] ?? null;
                if (!empty($bg)) {
                    return $bg;
                }
            }

            // Recursively search children / nested elements
            if (!empty($el['elements']) && is_array($el['elements'])) {
                $found = $this->findCouplePhotoInElements($el['elements']);
                if ($found) return $found;
            }
            if (!empty($el['children']) && is_array($el['children'])) {
                $found = $this->findCouplePhotoInElements($el['children']);
                if ($found) return $found;
            }
            if (!empty($el['props']['elements']) && is_array($el['props']['elements'])) {
                $found = $this->findCouplePhotoInElements($el['props']['elements']);
                if ($found) return $found;
            }
        }
        return null;
    }

    /**
     * Check if a URL is a generic placeholder or Unsplash dummy image.
     */
    private function isPlaceholderUrl(?string $url): bool
    {
        if (empty($url) || !is_string($url)) {
            return true;
        }
        $lower = strtolower(trim($url));
        if ($lower === '' || $lower === 'null' || $lower === 'undefined') {
            return true;
        }
        return str_contains($lower, 'photo-1519741497674-611481863552') ||
               str_contains($lower, 'photo-1534528741775-53994a69daeb') ||
               str_contains($lower, 'photo-1507003211169-0a1dd7228f2d') ||
               str_contains($lower, 'photo-1511285560929-80b456fea0bc') ||
               str_contains($lower, 'placeholder.com') ||
               str_contains($lower, 'via.placeholder');
    }

    /**
     * Helper to extract the marked couple photo from schema, custom content, or media.
     */
    private function extractCouplePhoto(?array $schema, ?array $customContent, ?string $fallbackCover = null, ?Wedding $wedding = null): ?string
    {
        // 1. Direct custom content bindings (ignoring dummy placeholder URLs)
        $candidates = [
            $customContent['couple.couplePhotoUrl'] ?? null,
            $customContent['couple']['couplePhotoUrl'] ?? null,
            $customContent['couplePhotoUrl'] ?? null,
            $customContent['couple.coverPhoto'] ?? null,
            $customContent['couple']['coverPhoto'] ?? null,
            $customContent['coverPhoto'] ?? null,
            $customContent['customContent.coverPhotoUrl'] ?? null,
            $customContent['coverPhotoUrl'] ?? null,
            $customContent['customContent.desktopCoverBackgroundPhotoUrl'] ?? null,
        ];
        foreach ($candidates as $cand) {
            if (!empty($cand) && is_string($cand) && !$this->isPlaceholderUrl($cand)) {
                return $this->normalizeImageUrl($cand);
            }
        }

        // 2. Wedding couple_photo_id relationship
        if ($wedding && $wedding->couple_photo_id) {
            $media = Media::find($wedding->couple_photo_id);
            if ($media && $media->url) {
                return $this->normalizeImageUrl($media->url);
            }
        }

        // 3. Scan schema for element with bindingKey == 'couple.couplePhotoUrl' or labeled 'Foto Berdua'
        if ($schema) {
            // Check desktop cover settings
            $coverBinding = (string) ($schema['desktopCover']['bindingKey'] ?? '');
            if ($coverBinding === 'couple.couplePhotoUrl' || str_contains(strtolower($coverBinding), 'berdua')) {
                $bg = $schema['desktopCover']['backgroundImageUrl'] ?? $schema['desktopCover']['backgroundUrl'] ?? null;
                if (!empty($bg)) {
                    return $this->normalizeImageUrl($bg);
                }
            }

            // Check desktop cover elements
            if (!empty($schema['desktopCover']['elements']) && is_array($schema['desktopCover']['elements'])) {
                $found = $this->findCouplePhotoInElements($schema['desktopCover']['elements']);
                if ($found) {
                    return $this->normalizeImageUrl($found);
                }
            }

            // Check all sections recursively
            if (!empty($schema['sections']) && is_array($schema['sections'])) {
                foreach ($schema['sections'] as $section) {
                    $secBinding = (string) ($section['bindingKey'] ?? '');
                    if ($secBinding === 'couple.couplePhotoUrl' || str_contains(strtolower($secBinding), 'berdua')) {
                        $bg = $section['backgroundImageUrl'] ?? $section['backgroundUrl'] ?? null;
                        if (!empty($bg)) {
                            return $this->normalizeImageUrl($bg);
                        }
                    }

                    if (!empty($section['elements']) && is_array($section['elements'])) {
                        $found = $this->findCouplePhotoInElements($section['elements']);
                        if ($found) {
                            return $this->normalizeImageUrl($found);
                        }
                    }
                }
            }
        }

        // 4. Secondary media check: bride or groom photo if couple photo not set
        if ($wedding) {
            if ($wedding->bride_photo_id) {
                $media = Media::find($wedding->bride_photo_id);
                if ($media && $media->url) {
                    return $this->normalizeImageUrl($media->url);
                }
            }
            if ($wedding->groom_photo_id) {
                $media = Media::find($wedding->groom_photo_id);
                if ($media && $media->url) {
                    return $this->normalizeImageUrl($media->url);
                }
            }
        }

        // 5. Fallback cover or template thumbnail
        if (!empty($fallbackCover)) {
            return $this->normalizeImageUrl($fallbackCover);
        }

        return null;
    }

    /**
     * Ensure any image URL is complete, absolute (https://...) and accessible to social crawlers.
     */
    private function normalizeImageUrl(?string $url): ?string
    {
        if (empty($url) || !is_string($url)) return null;
        $trimmed = trim($url);
        if (empty($trimmed)) return null;

        if (preg_match('#^https?://#i', $trimmed)) {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '//')) {
            return 'https:' . $trimmed;
        }

        $backendUrl = rtrim(config('app.url', env('APP_URL', 'https://api.ayohadir.id')), '/');
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://ayohadir.id')), '/');

        if (str_starts_with($trimmed, '/storage/') || str_starts_with($trimmed, 'storage/')) {
            return $backendUrl . '/' . ltrim($trimmed, '/');
        }

        if (str_starts_with($trimmed, 'uploads/') || str_starts_with($trimmed, '/uploads/')) {
            return $backendUrl . '/storage/' . ltrim($trimmed, '/');
        }

        if (str_starts_with($trimmed, '/images/') || str_starts_with($trimmed, 'images/')) {
            return $frontendUrl . '/' . ltrim($trimmed, '/');
        }

        return $frontendUrl . '/' . ltrim($trimmed, '/');
    }

    /**
     * Resolve metadata structure for a wedding invitation.
     */
    public function resolveWeddingOgData(Request $request, string $slug): ?array
    {
        $wedding = Wedding::where('slug', $slug)
            ->with(['design', 'template'])
            ->first();

        if (!$wedding) {
            return null;
        }

        // Resolve guest name from ?to= or ?guest=
        $guestParam = trim((string) ($request->query('to') ?? $request->query('guest') ?? ''));
        $guestName = null;

        if (!empty($guestParam)) {
            // Check if it's a token
            $invitation = Invitation::where('token', $guestParam)
                ->where('wedding_id', $wedding->id)
                ->with('guest')
                ->first();

            if ($invitation && $invitation->guest) {
                $guestName = $invitation->guest->name;
            } else {
                $guestName = $guestParam;
            }
        }

        $bride = trim((string) ($wedding->bride_name ?? ''));
        $groom = trim((string) ($wedding->groom_name ?? ''));

        if ($bride && $groom) {
            $couple = "{$bride} & {$groom}";
        } elseif ($bride || $groom) {
            $couple = $bride ?: $groom;
        } else {
            $couple = $wedding->title ?: 'Mempelai';
        }

        // Build Title
        if (!empty($guestName)) {
            $title = "Undangan Pernikahan untuk {$guestName} — {$couple}";
        } else {
            $title = "The Wedding of {$couple} — Ayo Hadir";
        }

        // Build Description
        $dateStr = $wedding->wedding_date ? $wedding->wedding_date->translatedFormat('l, d F Y') : null;
        $venue = $wedding->venue_name ? " di {$wedding->venue_name}" : '';

        if (!empty($guestName)) {
            $description = "Kepada Yth. Bapak/Ibu/Saudara/i {$guestName}, tanpa mengurangi rasa hormat, kami mengundang Anda untuk menghadiri momen bahagia pernikahan {$couple}" . ($dateStr ? " pada {$dateStr}{$venue}." : '.') . " Buka undangan digital di sini.";
        } else {
            $description = "Tanpa mengurangi rasa hormat, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri momen bahagia pernikahan {$couple}" . ($dateStr ? " pada {$dateStr}{$venue}." : '.') . " Buka undangan digital di sini.";
        }

        // Resolve couple photo marked in schema/customContent
        $rawSnapshot = $wedding->design?->published_schema ?? [];
        $schema = !empty($rawSnapshot['schema']) 
            ? $rawSnapshot['schema'] 
            : ($wedding->design?->schema ?? $wedding->template?->schema);
        $customContent = $wedding->custom_content ?? [];
        $fallback = $wedding->cover_image_url ?? $wedding->template?->thumbnail ?? null;

        $ogImage = $this->extractCouplePhoto($schema, $customContent, $fallback, $wedding);
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://ayohadir.id')), '/');

        if (empty($ogImage)) {
            $ogImage = "{$frontendUrl}/images/og-ayohadir.png";
        }

        // Resolve primary theme color
        $primaryColor = $schema['theme']['colors']['primary'] ?? '#03AC0E';

        $canonicalUrl = "{$frontendUrl}/{$slug}" . (!empty($guestName) ? '?to=' . urlencode($guestName) : '');

        return [
            'type' => 'wedding',
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'image' => $ogImage,
            'url' => $canonicalUrl,
            'siteName' => 'Ayo Hadir',
            'themeColor' => $primaryColor,
            'guestName' => $guestName,
            'couple' => [
                'bride' => $bride,
                'groom' => $groom,
                'title' => $couple,
            ],
            'weddingDate' => $wedding->wedding_date?->format('Y-m-d'),
            'venueName' => $wedding->venue_name,
        ];
    }

    /**
     * Resolve metadata structure for a template.
     */
    public function resolveTemplateOgData(Request $request, string $slug): ?array
    {
        $template = Template::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return null;
        }

        $title = "Template Undangan: {$template->name} — Ayo Hadir";
        $description = $template->description ?: "Pratinjau tema undangan digital eksklusif {$template->name} di Ayo Hadir. Desain elegan, responsif, dan siap digunakan.";

        // Resolve couple photo marked in template schema
        $ogImage = $this->extractCouplePhoto($template->schema, [], $template->thumbnail);
        $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://ayohadir.id')), '/');

        if (empty($ogImage)) {
            $ogImage = "{$frontendUrl}/images/og-ayohadir.png";
        }

        $primaryColor = $template->schema['theme']['colors']['primary'] ?? '#03AC0E';
        $canonicalUrl = "{$frontendUrl}/templates/{$slug}";

        return [
            'type' => 'template',
            'slug' => $slug,
            'name' => $template->name,
            'title' => $title,
            'description' => $description,
            'image' => $ogImage,
            'url' => $canonicalUrl,
            'siteName' => 'Ayo Hadir',
            'themeColor' => $primaryColor,
        ];
    }

    /**
     * Resolve Open Graph metadata for a public wedding invitation (API JSON or Web View).
     */
    public function weddingOg(Request $request, string $slug): JsonResponse|Response|\Illuminate\Http\RedirectResponse
    {
        $data = $this->resolveWeddingOgData($request, $slug);

        if (!$data) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Undangan tidak ditemukan.',
                    ],
                ], 404);
            }
            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://ayohadir.id')), '/');
            return redirect("{$frontendUrl}/{$slug}");
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $data,
            ]);
        }

        return response()->view('og-meta', [
            'title' => $data['title'],
            'description' => $data['description'],
            'image' => $data['image'],
            'url' => $data['url'],
            'frontendUrl' => $data['url'],
            'themeColor' => $data['themeColor'],
        ]);
    }

    /**
     * Resolve Open Graph metadata for a public template (API JSON or Web View).
     */
    public function templateOg(Request $request, string $slug): JsonResponse|Response|\Illuminate\Http\RedirectResponse
    {
        $data = $this->resolveTemplateOgData($request, $slug);

        if (!$data) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Template tidak ditemukan.',
                    ],
                ], 404);
            }
            $frontendUrl = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'https://ayohadir.id')), '/');
            return redirect("{$frontendUrl}/templates/{$slug}");
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $data,
            ]);
        }

        return response()->view('og-meta', [
            'title' => $data['title'],
            'description' => $data['description'],
            'image' => $data['image'],
            'url' => $data['url'],
            'frontendUrl' => $data['url'],
            'themeColor' => $data['themeColor'],
        ]);
    }
}
