<?php

namespace App\Http\Requests;

use App\Models\Wedding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $wedding = $this->route('wedding');
        return $this->user()?->can('update', $wedding) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $weddingId = $this->route('wedding') instanceof Wedding
            ? $this->route('wedding')->id
            : $this->route('wedding');

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'max_attendees' => ['nullable', 'integer', 'min:1', 'max:20'],
            'guest_group_id' => [
                'nullable',
                Rule::exists('guest_groups', 'id')->where(function ($query) use ($weddingId) {
                    return $query->where('wedding_id', $weddingId);
                }),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }
}
