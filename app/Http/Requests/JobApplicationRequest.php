<?php

namespace App\Http\Requests;

use App\Models\JobApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        if ($application instanceof JobApplication) {
            return $application->user_id === $this->user()?->id;
        }

        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'job_title' => ['required', 'string', 'max:255'],
            'job_location' => ['nullable', 'string', 'max:255'],
            'job_description' => ['required', 'string', 'min:50', 'max:20000'],
            'job_qualifications' => ['nullable', 'string', 'max:20000'],
            'language' => ['required', Rule::in(JobApplication::LANGUAGES)],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Nama perusahaan wajib diisi.',
            'job_title.required' => 'Posisi yang dilamar wajib diisi.',
            'job_description.required' => 'Deskripsi lowongan (JD) wajib ditempel.',
            'job_description.min' => 'Deskripsi lowongan terlalu pendek — tempel JD lengkapnya (minimal 50 karakter).',
            'language.in' => 'Bahasa dokumen harus id atau en.',
        ];
    }
}
