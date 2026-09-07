<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicOgController extends Controller
{
    /**
     * Helper to extract the marked couple photo from schema or custom content.
     */
    private function extractCouplePhoto(?array $schema, ?array $customContent, ?string $fallbackCover = null): ?string
    {
        // 1. Direct custom content bindings
        if (!empty($customContent['couple.couplePhotoUrl'])) {
            return $customContent['couple.couplePhotoUrl'];
        }
        if (!empty($customContent['couple']['couplePhotoUrl'])) {
            return $customContent['couple']['couplePhotoUrl'];
        }
        if (!empty($customContent['customContent.coverPhotoUrl'])) {
            return $customContent['customContent.coverPhotoUrl'];
        }
        if (!empty($customContent['coverPhotoUrl'])) {
            return $customContent['coverPhotoUrl'];
        }

        // 2. Scan schema for element with bindingKey == 'couple.couplePhotoUrl'
        if ($schema) {
            // Check desktop cover
            if (!empty($schema['desktopCover']['elements']) && is_array($schema['desktopCover']['elements'])) {
                foreach ($schema['desktopCover']['elements'] as $el) {
                    if (($el['bindingKey'] ?? '') === 'couple.couplePhotoUrl') {
                        $url = $el['props']['url'] ?? $el['props']['src'] ?? $el['url'] ?? $el['src'] ?? null;
                        if (!empty($url)) {
                            return $url;
                        }
                    }
                }
            }

            // Check all sections
            if (!empty($schema['sections']) && is_array($schema['sections'])) {
                foreach ($schema['sections'] as $section) {
                    if (!empty($section['elements']) && is_array($section['elements'])) {
                        foreach ($section['elements'] as $el) {
                            if (($el['bindingKey'] ?? '') === 'couple.couplePhotoUrl') {
                                $url = $el['props']['url'] ?? $el['props']['src'] ?? $el['url'] ?? $el['src'] ?? null;
                                if (!empty($url)) {
                                    return $url;
                                }
                            }
                        }
                    }
                }
            }
        }

        // 3. Fallback cover or image
        return $fallbackCover;
    }

    /**
     * Resolve Open Graph metadata for a public wedding invitation.
     */
    public function weddingOg(Request $request, string $slug): JsonResponse
    {
        $wedding = Wedding::where('slug', $slug)
            ->where('status', 'published')
            ->with(['design', 'template'])
            ->first();

        if (!$wedding) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Undangan tidak ditemukan atau belum dipublikasikan.',
                ],
            ], 404);
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
                // Treated directly as guest name
                $guestName = $guestParam;
            }
        }

        $bride = $wedding->bride_name ?? 'Mempelai Wanita';
        $groom = $wedding->groom_name ?? 'Mempelai Pria';
        $couple = trim("{$bride} & {$groom}");

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
            if ($dateStr) {
                $description = "Kepada Yth. {$guestName}, tanpa mengurangi rasa hormat, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri hari bahagia kami pada {$dateStr}{$venue}.";
            } else {
                $description = "Kepada Yth. {$guestName}, tanpa mengurangi rasa hormat, perkenankan kami mengundang Anda untuk menghadiri acara pernikahan kami.";
            }
        } else {
            if ($dateStr) {
                $description = "Tanpa mengurangi rasa hormat, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri pernikahan {$couple} pada {$dateStr}{$venue}. Buka undangan digital di sini.";
            } else {
                $description = "Tanpa mengurangi rasa hormat, kami mengundang Bapak/Ibu/Saudara/i untuk menghadiri pernikahan kami. Buka undangan digital di sini.";
            }
        }

        // Resolve couple photo
        $schema = $wedding->design?->published_schema['schema'] ?? $wedding->design?->schema ?? $wedding->template?->schema;
        $customContent = $wedding->custom_content ?? [];
        $fallback = $wedding->cover_image_url ?? $wedding->template?->thumbnail ?? null;

        $ogImage = $this->extractCouplePhoto($schema, $customContent, $fallback);
        $frontendUrl = rtrim(env('FRONTEND_URL', 'https://ayohadir.id'), '/');

        if (empty($ogImage)) {
            $ogImage = "{$frontendUrl}/images/og-ayohadir.png";
        }

        // Resolve primary theme color
        $primaryColor = $schema['theme']['colors']['primary'] ?? '#03AC0E';

        $canonicalUrl = "{$frontendUrl}/{$slug}" . (!empty($guestName) ? '?to=' . urlencode($guestName) : '');

        return response()->json([
            'data' => [
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
            ],
        ]);
    }

    /**
     * Resolve Open Graph metadata for a public template.
     */
    public function templateOg(Request $request, string $slug): JsonResponse
    {
        $template = Template::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Template tidak ditemukan.',
                ],
            ], 404);
        }

        $title = "Template Undangan: {$template->name} — Ayo Hadir";
        $description = $template->description ?: "Pratinjau tema undangan digital eksklusif {$template->name} di Ayo Hadir. Desain elegan, responsif, dan siap digunakan.";

        // Resolve couple photo marked in template schema
        $ogImage = $this->extractCouplePhoto($template->schema, [], $template->thumbnail);
        $frontendUrl = rtrim(env('FRONTEND_URL', 'https://ayohadir.id'), '/');

        if (empty($ogImage)) {
            $ogImage = "{$frontendUrl}/images/og-ayohadir.png";
        }

        $primaryColor = $template->schema['theme']['colors']['primary'] ?? '#03AC0E';
        $canonicalUrl = "{$frontendUrl}/templates/{$slug}";

        return response()->json([
            'data' => [
                'type' => 'template',
                'slug' => $slug,
                'name' => $template->name,
                'title' => $title,
                'description' => $description,
                'image' => $ogImage,
                'url' => $canonicalUrl,
                'siteName' => 'Ayo Hadir',
                'themeColor' => $primaryColor,
            ],
        ]);
    }
}
