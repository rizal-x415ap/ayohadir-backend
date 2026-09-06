<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWeddingRequest extends FormRequest
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
            'bride_name' => ['required', 'string', 'max:255'],
            'groom_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:weddings,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'bride_parents' => ['nullable', 'string', 'max:255'],
            'groom_parents' => ['nullable', 'string', 'max:255'],
            'wedding_date' => ['nullable', 'date', 'after_or_equal:today'],
            'wedding_time' => ['nullable', 'date_format:H:i:s,H:i'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string'],
            'venue_map_url' => ['nullable', 'url', 'max:500'],
            'rsvp_enabled' => ['nullable', 'boolean'],
            'rsvp_deadline' => ['nullable', 'date'],
            'wishes_enabled' => ['nullable', 'boolean'],
            'applied_template_id' => ['nullable', 'exists:templates,id'],
        ];
    }
}
