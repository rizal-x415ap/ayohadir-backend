<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->is('api/v1/admin/*') || $this->is('*/admin/assets*')) {
            return $this->user()?->isAdmin() ?? false;
        }

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
            'file' => [
                'required_without:files',
                'nullable',
                'file',
                'max:20480', // 20MB max limit
                'mimes:jpeg,jpg,png,webp,svg,gif,mp3,wav,mp4,mpga,m4a,ogg,bin',
                'extensions:jpeg,jpg,png,webp,svg,gif,mp3,wav,ogg,m4a,mp4',
            ],
            'files' => [
                'required_without:file',
                'nullable',
                'array',
            ],
            'files.*' => [
                'file',
                'max:20480',
                'mimes:jpeg,jpg,png,webp,svg,gif,mp3,wav,mp4,mpga,m4a,ogg,bin',
                'extensions:jpeg,jpg,png,webp,svg,gif,mp3,wav,ogg,m4a,mp4',
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:30'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'Ukuran file melebihi batas maksimal yang diizinkan (20MB).',
            'file.mimes' => 'Format file tidak didukung. Format yang diizinkan: JPG, PNG, WEBP, SVG, GIF, MP3, WAV, OGG, M4A, MP4.',
            'file.extensions' => 'Ekstensi file tidak didukung. Format yang diizinkan: JPG, PNG, WEBP, SVG, GIF, MP3, WAV, OGG, M4A, MP4.',
            'file.uploaded' => 'Gagal mengunggah file. Ukuran file kemungkinan melebihi batas "upload_max_filesize" pada konfigurasi PHP di cPanel hosting.',
            'files.*.max' => 'Ukuran salah satu file melebihi batas maksimal yang diizinkan (20MB).',
            'files.*.uploaded' => 'Gagal mengunggah salah satu file. Kemungkinan melebihi batas upload server cPanel.',
        ];
    }
}
