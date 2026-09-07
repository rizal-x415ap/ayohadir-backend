<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Rsvp
 */
class RsvpResource extends JsonResource
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
            'weddingId' => $this->wedding_id,
            'guestId' => $this->guest_id,
            'guest' => [
                'id' => $this->guest->id,
                'name' => $this->guest->name,
                'phone' => $this->guest->phone,
                'group' => $this->guest->group?->name,
            ],
            'attending' => (bool) $this->attending,
            'attendeeCount' => $this->attendee_count,
            'wishes' => $this->wishes,
            'isApproved' => (bool) $this->is_approved,
            'is_approved' => (bool) $this->is_approved,
            'respondedAt' => $this->responded_at->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
