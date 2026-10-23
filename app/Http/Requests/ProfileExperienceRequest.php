<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'organization' => ['required', 'string', 'max:255'],
            'job_title' => ['required', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_unless:is_current,1'],
            'is_current' => ['boolean'],
            'description' => ['nullable', 'string', 'max:4000'],
            'achievements' => ['nullable', 'array', 'max:20'],
            'achievements.*' => ['string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.required_unless' => 'Isi tanggal selesai atau centang "masih bekerja di sini".',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ];
    }
}
