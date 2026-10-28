<?php

namespace App\Http\Requests;

use App\Models\GeneratedDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(GeneratedDocument::TYPES))],
            'language' => ['nullable', Rule::in(['id', 'en'])],
            'tone' => ['nullable', Rule::in(GeneratedDocument::TONES)],
            'recipient_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Tipe dokumen harus resume atau cover_letter.',
        ];
    }
}
