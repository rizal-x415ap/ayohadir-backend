<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $templateId = $this->route('template') instanceof \App\Models\Template
            ? $this->route('template')->id
            : $this->route('template');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('templates', 'slug')->ignore($templateId), 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:500'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'price' => ['nullable', 'integer', 'min:0'],
            'original_price' => ['nullable', 'integer', 'min:0'],
            'tier' => ['nullable', 'string', 'in:regular,premium,exclusive'],
            'is_active' => ['boolean'],
            'order' => ['integer', 'min:0'],
            'schema' => ['sometimes', 'required', 'array'],
            'schema.schemaVersion' => ['sometimes', 'required', 'integer'],
            'schema.sections' => ['sometimes', 'present', 'array'],
            'schema.desktopCover' => ['nullable', 'array'],
            'contract' => ['nullable', 'array'],
        ];
    }

    /**
     * Configure the validator instance to ensure unique section names.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sections = $this->input('schema.sections');
            if (is_array($sections)) {
                $names = [];
                foreach ($sections as $index => $section) {
                    $name = is_array($section) ? ($section['name'] ?? null) : null;
                    if ($name) {
                        $lowerName = mb_strtolower(trim($name));
                        if (in_array($lowerName, $names, true)) {
                            $validator->errors()->add(
                                "schema.sections.{$index}.name",
                                "Nama seksi '{$name}' sudah digunakan. Setiap seksi harus memiliki nama yang unik."
                            );
                        }
                        $names[] = $lowerName;
                    }
                }
            }
        });
    }
}
