<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportProfileDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'sections' => ['required', 'array'],
            'sections.contact' => ['sometimes', 'array'],
            'sections.summary' => ['sometimes', 'array'],
            'sections.experiences' => ['sometimes', 'array', 'max:50'],
            'sections.educations' => ['sometimes', 'array', 'max:50'],
            'sections.skills' => ['sometimes', 'array', 'max:50'],
            'sections.projects' => ['sometimes', 'array', 'max:50'],
            'sections.certifications' => ['sometimes', 'array', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'sections.required' => 'Pilih minimal satu bagian untuk diimpor.',
        ];
    }
}
