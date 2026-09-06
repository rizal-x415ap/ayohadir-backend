<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitRsvpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public submission endpoint
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['nullable', 'string', 'exists:invitations,token'],
            'name' => ['required_without:token', 'nullable', 'string', 'min:2', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'attending' => ['required', 'boolean'],
            'attendee_count' => $this->boolean('attending')
                ? ['required', 'integer', 'min:1', 'max:20']
                : ['nullable', 'integer', 'min:0', 'max:20'],
            'wishes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required_without' => 'Mohon masukkan nama Anda.',
            'name.min' => 'Nama minimal terdiri dari 2 karakter.',
            'attending.required' => 'Mohon pilih konfirmasi kehadiran.',
            'attendee_count.required_if' => 'Mohon tentukan jumlah tamu yang hadir.',
            'attendee_count.min' => 'Jumlah tamu minimal 1 orang.',
            'attendee_count.max' => 'Jumlah tamu maksimal 20 orang.',
            'wishes.max' => 'Ucapan doa restu maksimal 1000 karakter.',
        ];
    }
}
