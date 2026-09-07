<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Wedding
 */
class WeddingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'appliedTemplateId' => $this->applied_template_id,
            'slug' => $this->slug,
            'brideName' => $this->bride_name,
            'groomName' => $this->groom_name,
            'brideParents' => $this->bride_parents,
            'groomParents' => $this->groom_parents,
            'weddingDate' => $this->wedding_date?->format('Y-m-d'),
            'weddingTime' => $this->wedding_time,
            'venueName' => $this->venue_name,
            'venueAddress' => $this->venue_address,
            'venueMapUrl' => $this->venue_map_url,
            'bridePhotoId' => $this->bride_photo_id,
            'groomPhotoId' => $this->groom_photo_id,
            'couplePhotoId' => $this->couple_photo_id,
            'customContent' => $this->custom_content,
            'sectionsConfig' => $this->sections_config,
            'rsvpEnabled' => (bool) $this->rsvp_enabled,
            'rsvpDeadline' => $this->rsvp_deadline?->format('Y-m-d'),
            'rsvpNotificationEnabled' => (bool) $this->rsvp_notification_enabled,
            'rsvpNotificationEmail' => $this->rsvp_notification_email,
            'rsvp_notification_enabled' => (bool) $this->rsvp_notification_enabled,
            'rsvp_notification_email' => $this->rsvp_notification_email,
            'wishesEnabled' => (bool) $this->wishes_enabled,
            'wishesModerationEnabled' => (bool) $this->wishes_moderation_enabled,
            'wishes_moderation_enabled' => (bool) $this->wishes_moderation_enabled,
            'status' => $this->status,
            'publishedAt' => $this->published_at?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
            'deletedAt' => $this->deleted_at?->toIso8601String(),
            'daysRemaining' => $this->deleted_at
                ? max(0, (int) ceil(now()->diffInHours($this->deleted_at->copy()->addDays(7), false) / 24))
                : null,
            'canRestore' => $this->deleted_at
                ? $this->deleted_at->copy()->addDays(7)->isFuture()
                : false,
        ];
    }
}
