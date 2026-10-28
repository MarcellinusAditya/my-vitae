<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use HasFactory;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const OPERATIONS = [
        'cv_extraction',
        'job_fit',
        'resume_generation',
        'cover_letter_generation',
    ];

    protected $fillable = [
        'user_id',
        'job_application_id',
        'provider',
        'model',
        'operation',
        'input_tokens',
        'output_tokens',
        'duration_ms',
        'status',
        'error_code',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }
}
