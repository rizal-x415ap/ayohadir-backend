<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWeddingContentRequest extends FormRequest
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
        return [
            // Core Couple Fields
            'bride_name' => ['sometimes', 'required', 'string', 'max:255'],
            'groom_name' => ['sometimes', 'required', 'string', 'max:255'],
            'bride_parents' => ['nullable', 'string', 'max:255'],
            'groom_parents' => ['nullable', 'string', 'max:255'],
            'wedding_date' => ['nullable', 'date'],
            'wedding_time' => ['nullable', 'date_format:H:i:s,H:i'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string'],
            'venue_map_url' => ['nullable', 'url', 'max:500'],
            'bride_photo_id' => ['nullable', 'integer'],
            'groom_photo_id' => ['nullable', 'integer'],
            'couple_photo_id' => ['nullable', 'integer'],

            // Flexible Template Content & Section Configurations
            'custom_content' => ['nullable', 'array'],
            'sections_config' => ['nullable', 'array'],

            // Feature Toggles
            'rsvp_enabled' => ['nullable', 'boolean'],
            'rsvp_deadline' => ['nullable', 'date'],
            'wishes_enabled' => ['nullable', 'boolean'],
        ];
    }
}
