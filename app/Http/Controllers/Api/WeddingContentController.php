<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWeddingContentRequest;
use App\Models\Wedding;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class WeddingContentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get the structured content representation for the User Invitation Editor.
     */
    public function show(Wedding $wedding): JsonResponse
    {
        $this->authorize('view', $wedding);

        // Default template-defined sections if not yet customized
        $defaultSections = [
            ['id' => 'cover', 'name' => 'Sampul Undangan', 'visible' => true, 'order' => 1],
            ['id' => 'couple', 'name' => 'Mempelai Pengantin', 'visible' => true, 'order' => 2],
            ['id' => 'event', 'name' => 'Rangkaian Acara', 'visible' => true, 'order' => 3],
            ['id' => 'story', 'name' => 'Cerita Cinta', 'visible' => true, 'order' => 4],
            ['id' => 'gallery', 'name' => 'Galeri Foto', 'visible' => true, 'order' => 5],
            ['id' => 'rsvp', 'name' => 'RSVP & Ucapan', 'visible' => true, 'order' => 6],
            ['id' => 'gift', 'name' => 'Hadiah / Amplop Digital', 'visible' => true, 'order' => 7],
        ];

        $sectionsConfig = $wedding->sections_config ?? ['sections' => $defaultSections];

        $content = [
            'schemaVersion' => 1,
            'weddingId' => $wedding->id,
            'slug' => $wedding->slug,
            'status' => $wedding->status,
            'couple' => [
                'bride' => [
                    'fullName' => $wedding->bride_name,
                    'shortName' => explode(' ', $wedding->bride_name)[0] ?? $wedding->bride_name,
                    'parents' => $wedding->bride_parents,
                    'photoId' => $wedding->bride_photo_id,
                ],
                'groom' => [
                    'fullName' => $wedding->groom_name,
                    'shortName' => explode(' ', $wedding->groom_name)[0] ?? $wedding->groom_name,
                    'parents' => $wedding->groom_parents,
                    'photoId' => $wedding->groom_photo_id,
                ],
                'couplePhotoId' => $wedding->couple_photo_id,
            ],
            'schedule' => [
                'date' => $wedding->wedding_date?->format('Y-m-d'),
                'time' => $wedding->wedding_time,
                'venueName' => $wedding->venue_name,
                'venueAddress' => $wedding->venue_address,
                'venueMapUrl' => $wedding->venue_map_url,
            ],
            'rsvp' => [
                'enabled' => (bool) $wedding->rsvp_enabled,
                'deadline' => $wedding->rsvp_deadline?->format('Y-m-d'),
                'wishesEnabled' => (bool) $wedding->wishes_enabled,
            ],
            'customContent' => $wedding->custom_content ?? [
                'coverGreeting' => 'The Wedding of',
                'coverQuote' => 'Dan di antara tanda-tanda kebesaran-Nya ialah Dia menciptakan pasangan-pasangan untukmu dari jenismu sendiri...',
                'quoteSource' => 'QS. Ar-Rum: 21',
                'storyTitle' => 'Kisah Cinta Kami',
                'storyBody' => 'Pertemuan pertama kami berawal pada tahun 2022...',
                'closingThankYou' => 'Merupakan suatu kehormatan dan kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir dan memberikan doa restu.',
            ],
            'sectionsConfig' => $sectionsConfig,
        ];

        return response()->json([
            'data' => $content,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update the wedding content via User Invitation Editor autosave / manual save.
     */
    public function update(UpdateWeddingContentRequest $request, Wedding $wedding): JsonResponse
    {
        $this->authorize('update', $wedding);

        $wedding->update($request->validated());

        // If wedding is already published, automatically sync published snapshot and refresh public cache
        if ($wedding->status === 'published') {
            $wedding->refresh();
            app(\App\Services\PublishingService::class)->syncPublishedSnapshot($wedding);
        }

        return response()->json([
            'data' => [
                'saved' => true,
                'updatedAt' => $wedding->updated_at->toIso8601String(),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
