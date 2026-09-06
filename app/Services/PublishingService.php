<?php

namespace App\Services;

use App\Models\Design;
use App\Models\Wedding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PublishingService
{
    /**
     * Validate whether wedding draft is complete enough to publish.
     */
    public function validateForPublishing(Wedding $wedding): array
    {
        $errors = [];

        if (empty(trim($wedding->bride_name ?? ''))) {
            $errors['bride_name'] = ['Nama pengantin wanita wajib diisi sebelum publikasi.'];
        }

        if (empty(trim($wedding->groom_name ?? ''))) {
            $errors['groom_name'] = ['Nama pengantin pria wajib diisi sebelum publikasi.'];
        }

        if (empty($wedding->wedding_date)) {
            $errors['wedding_date'] = ['Tanggal pernikahan wajib diisi sebelum publikasi.'];
        }

        if (empty(trim($wedding->venue_name ?? ''))) {
            $errors['venue_name'] = ['Nama lokasi/gedung pernikahan wajib diisi sebelum publikasi.'];
        }

        if (empty(trim($wedding->slug ?? ''))) {
            $errors['slug'] = ['URL undangan (slug) wajib diatur sebelum publikasi.'];
        }

        return $errors;
    }

    /**
     * Publish wedding: resolve content binding, freeze snapshot, cache, and update status.
     */
    public function publish(Wedding $wedding): array
    {
        $errors = $this->validateForPublishing($wedding);
        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $now = now();

        // Load design and ensure canonical visual schema
        $design = $wedding->design ?? new Design(['wedding_id' => $wedding->id]);
        $schema = $design->schema;
        if (!$schema || empty($schema['sections'])) {
            $schema = $wedding->template?->schema;
        }

        // Build canonical frozen published snapshot
        $publishedSnapshot = [
            'snapshotVersion' => 1,
            'publishedAt' => $now->toIso8601String(),
            'schema' => $schema,
            'design' => [
                'schema' => $schema,
            ],
            'wedding' => [
                'id' => $wedding->id,
                'slug' => $wedding->slug,
                'status' => 'published',
                'brideName' => $wedding->bride_name,
                'groomName' => $wedding->groom_name,
                'brideParents' => $wedding->bride_parents,
                'groomParents' => $wedding->groom_parents,
                'weddingDate' => $wedding->wedding_date?->format('Y-m-d'),
                'weddingTime' => $wedding->wedding_time,
                'venueName' => $wedding->venue_name,
                'venueAddress' => $wedding->venue_address,
                'venueMapUrl' => $wedding->venue_map_url,
                'bridePhotoId' => $wedding->bride_photo_id,
                'groomPhotoId' => $wedding->groom_photo_id,
                'couplePhotoId' => $wedding->couple_photo_id,
                'rsvpEnabled' => (bool) $wedding->rsvp_enabled,
                'rsvpDeadline' => $wedding->rsvp_deadline?->format('Y-m-d'),
                'wishesEnabled' => (bool) $wedding->wishes_enabled,
                'customContent' => $wedding->custom_content ?? [],
                'sectionsConfig' => $wedding->sections_config ?? [
                    'sections' => [
                        ['id' => 'cover', 'name' => 'Sampul Undangan', 'visible' => true, 'order' => 1],
                        ['id' => 'couple', 'name' => 'Mempelai Pengantin', 'visible' => true, 'order' => 2],
                        ['id' => 'event', 'name' => 'Rangkaian Acara', 'visible' => true, 'order' => 3],
                        ['id' => 'story', 'name' => 'Cerita Cinta', 'visible' => true, 'order' => 4],
                        ['id' => 'gallery', 'name' => 'Galeri Foto', 'visible' => true, 'order' => 5],
                        ['id' => 'rsvp', 'name' => 'RSVP & Ucapan', 'visible' => true, 'order' => 6],
                        ['id' => 'gift', 'name' => 'Hadiah / Amplop Digital', 'visible' => true, 'order' => 7],
                    ],
                ],
            ],
        ];

        // Upsert design published_schema
        $design->published_schema = $publishedSnapshot;
        $design->published_at = $now;
        $design->save();

        // Update wedding record
        $wedding->status = 'published';
        $wedding->published_at = $now;
        $wedding->save();

        // Cache Invalidation and Refresh
        $cacheKey = "public:wedding:{$wedding->slug}";
        Cache::forget($cacheKey);
        Cache::put($cacheKey, $publishedSnapshot, now()->addDays(7));

        $publicUrl = url("/{$wedding->slug}");

        return [
            'status' => 'published',
            'publishedAt' => $now->toIso8601String(),
            'publicUrl' => $publicUrl,
            'snapshot' => $publishedSnapshot,
        ];
    }

    /**
     * Unpublish wedding: update status to unpublished and invalidate cache.
     */
    public function unpublish(Wedding $wedding): array
    {
        $wedding->status = 'unpublished';
        $wedding->save();

        // Invalidate public page cache
        $cacheKey = "public:wedding:{$wedding->slug}";
        Cache::forget($cacheKey);

        return [
            'status' => 'unpublished',
            'unpublishedAt' => now()->toIso8601String(),
        ];
    }
}
