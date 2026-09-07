<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Guest
 */
class GuestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rsvp = $this->invitation?->rsvp;
        $rsvpStatus = $rsvp ? ($rsvp->attending ? 'confirmed' : 'declined') : 'pending';

        return [
            'id' => $this->id,
            'weddingId' => $this->wedding_id,
            'guestGroupId' => $this->guest_group_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'maxAttendees' => $this->max_attendees,
            'notes' => $this->notes,
            'group' => $this->whenLoaded('group', function () {
                return $this->group ? [
                    'id' => $this->group->id,
                    'name' => $this->group->name,
                ] : null;
            }),
            'invitationToken' => $this->invitation?->token,
            'invitationUrl' => $this->wedding ? url("/{$this->wedding->slug}?to=" . urlencode($this->name)) : null,
            'openedAt' => $this->invitation?->opened_at?->toIso8601String(),
            'openCount' => $this->invitation?->open_count ?? 0,
            'whatsappSentAt' => $this->invitation?->whatsapp_sent_at?->toIso8601String(),
            'rsvpStatus' => $rsvpStatus,
            'rsvp' => $rsvp ? [
                'attending' => (bool) $rsvp->attending,
                'attendeeCount' => $rsvp->attendee_count,
                'wishes' => $rsvp->wishes,
                'respondedAt' => $rsvp->responded_at->toIso8601String(),
            ] : null,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
