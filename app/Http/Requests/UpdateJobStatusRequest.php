<?php

namespace App\Http\Requests;

use App\Models\JobApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(array_keys(JobApplication::STATUSES))],
        ];
    }
}
