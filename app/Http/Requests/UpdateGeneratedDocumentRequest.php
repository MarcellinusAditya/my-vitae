<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneratedDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.summary' => ['nullable', 'string', 'max:2000'],
            'content.recipient_line' => ['nullable', 'string', 'max:255'],
            'content.opening' => ['nullable', 'string', 'max:2000'],
            'content.closing' => ['nullable', 'string', 'max:2000'],
            'content.signature' => ['nullable', 'string', 'max:255'],
            'content.skills' => ['nullable', 'array', 'max:60'],
            'content.experiences' => ['nullable', 'array', 'max:20'],
            'content.experiences.*.organization' => ['nullable', 'string', 'max:255'],
            'content.experiences.*.role' => ['nullable', 'string', 'max:255'],
            'content.experiences.*.bullets' => ['nullable', 'array', 'max:8'],
            'content.experiences.*.bullets.*' => ['string', 'max:500'],
            'content.projects' => ['nullable', 'array', 'max:20'],
            'content.education' => ['nullable', 'array', 'max:20'],
            'content.certifications' => ['nullable', 'array', 'max:20'],
            'content.languages' => ['nullable', 'array', 'max:12'],
            'content.body_paragraphs' => ['nullable', 'array', 'max:8'],
            'content.body_paragraphs.*.text' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Tidak ada konten yang dikirim.',
        ];
    }
}
