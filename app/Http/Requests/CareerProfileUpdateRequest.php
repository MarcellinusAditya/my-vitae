<?php

namespace App\Http\Requests;

use App\Models\CareerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CareerProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:4000'],
            'default_language' => ['required', Rule::in(CareerProfile::LANGUAGES)],
            'writing_style' => ['required', Rule::in(CareerProfile::WRITING_STYLES)],
            'resume_length' => ['required', Rule::in(CareerProfile::RESUME_LENGTHS)],
            'date_format' => ['required', Rule::in(CareerProfile::DATE_FORMATS)],
        ];
    }
}
