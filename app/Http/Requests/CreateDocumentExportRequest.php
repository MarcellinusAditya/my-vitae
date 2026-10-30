<?php

namespace App\Http\Requests;

use App\Models\DocumentExport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDocumentExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', Rule::in(array_keys(DocumentExport::FORMATS))],
        ];
    }

    public function messages(): array
    {
        return [
            'format.in' => 'Format harus pdf atau docx.',
        ];
    }
}
