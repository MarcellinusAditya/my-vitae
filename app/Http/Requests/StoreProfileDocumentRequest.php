<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreProfileDocumentRequest extends FormRequest
{
    /** 10 MB — MVP limit from PROJECT_PLAN.md §4.3. */
    public const MAX_KILOBYTES = 10240;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'document' => [
                'required',
                File::types(['pdf', 'docx'])->max(self::MAX_KILOBYTES),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Pilih file CV yang ingin diimpor.',
            'document.max' => 'Ukuran file maksimal 10 MB.',
            'document.mimes' => 'Format file harus PDF atau DOCX.',
        ];
    }
}
