<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderDocumentSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'section_order' => ['required', 'array', 'min:1', 'max:10'],
            'section_order.*' => ['string', Rule::in([
                'summary', 'skills', 'experiences', 'projects', 'education', 'certifications', 'languages',
            ])],
        ];
    }
}
