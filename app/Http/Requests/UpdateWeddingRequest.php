<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWeddingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var \App\Models\Wedding|null $wedding */
        $wedding = $this->route('wedding');
        $weddingId = $wedding instanceof \App\Models\Wedding ? $wedding->id : $wedding;

        return [
            'bride_name' => ['sometimes', 'required', 'string', 'max:255'],
            'groom_name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('weddings', 'slug')->ignore($weddingId),
            ],
            'bride_parents' => ['nullable', 'string', 'max:255'],
            'groom_parents' => ['nullable', 'string', 'max:255'],
            'wedding_date' => ['nullable', 'date'],
            'wedding_time' => ['nullable', 'date_format:H:i:s,H:i'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string'],
            'venue_map_url' => ['nullable', 'url', 'max:500'],
            'custom_content' => ['nullable', 'array'],
            'sections_config' => ['nullable', 'array'],
            'rsvp_enabled' => ['nullable', 'boolean'],
            'rsvp_deadline' => ['nullable', 'date'],
            'rsvp_notification_enabled' => ['nullable', 'boolean'],
            'rsvp_notification_email' => ['nullable', 'email', 'max:255'],
            'wishes_enabled' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'published', 'unpublished'])],
        ];
    }
}
