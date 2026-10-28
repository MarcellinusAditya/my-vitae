<?php

namespace App\Http\Requests;

use App\Models\GeneratedDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutosaveDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'array'],
            'section_order' => ['nullable', 'array', 'max:10'],
            'section_order.*' => ['string', Rule::in(array_keys(GeneratedDocument::RESUME_SECTIONS))],
            'template_key' => ['nullable', Rule::in(array_keys(GeneratedDocument::TEMPLATES))],
        ];
    }
}
