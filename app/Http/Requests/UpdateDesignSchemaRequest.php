<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDesignSchemaRequest extends FormRequest
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
            'client_version' => ['nullable', 'integer'],
            'schema' => ['required', 'array'],
            'schema.schemaVersion' => ['required', 'integer'],
            'schema.sections' => ['present', 'array'],
            'schema.theme' => ['nullable', 'array'],
        ];
    }

    /**
     * Configure the validator instance to ensure unique section names per schema contract.
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
