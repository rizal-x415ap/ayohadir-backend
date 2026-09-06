<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:templates,slug', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:500'],
            'category' => ['required', 'string', 'max:100'],
            'price' => ['nullable', 'integer', 'min:0'],
            'original_price' => ['nullable', 'integer', 'min:0'],
            'tier' => ['nullable', 'string', 'in:regular,premium,exclusive'],
            'is_active' => ['boolean'],
            'order' => ['integer', 'min:0'],
            'schema' => ['required', 'array'],
            'schema.schemaVersion' => ['required', 'integer'],
            'schema.sections' => ['present', 'array'],
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
