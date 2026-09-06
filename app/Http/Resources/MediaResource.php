<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Media
 */
class MediaResource extends JsonResource
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
            'weddingId' => $this->wedding_id,
            'type' => $this->type,
            'title' => $this->title,
            'artist' => $this->artist,
            'category' => $this->category,
            'tags' => $this->tags ?? [],
            'isSystem' => (bool) $this->is_system,
            'filename' => $this->filename,
            'url' => $this->url,
            'thumbnailUrl' => $this->thumbnail_url,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'durationSeconds' => $this->duration_seconds,
            'altText' => $this->alt_text,
            'usageCount' => $this->usage_count,
            'processingStatus' => $this->processing_status,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
