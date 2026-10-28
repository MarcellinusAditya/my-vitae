<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedDocument extends Model
{
    use HasFactory;

    public const TYPE_RESUME = 'resume';

    public const TYPE_COVER_LETTER = 'cover_letter';

    public const TYPES = [
        self::TYPE_RESUME => 'CV',
        self::TYPE_COVER_LETTER => 'Cover Letter',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const TONES = ['professional', 'formal', 'warm'];

    public const TONE_LABELS = [
        'professional' => 'Profesional',
        'formal' => 'Formal',
        'warm' => 'Hangat-profesional',
    ];

    public const ERROR_MESSAGES = [
        'provider_unavailable' => 'Layanan AI tidak tersedia atau belum dikonfigurasi. Coba lagi nanti.',
        'invalid_output' => 'Hasil generasi AI tidak dapat diproses. Coba generate ulang.',
        'empty_profile' => 'Profil karier masih kosong. Lengkapi profil sebelum membuat dokumen.',
        'analysis_required' => 'Jalankan analisis kecocokan terlebih dahulu agar dokumen selaras dengan lowongan.',
        'generation_failed' => 'Terjadi kesalahan saat membuat dokumen. Coba lagi.',
    ];

    /** Resume sections in display order. */
    public const RESUME_SECTIONS = [
        'summary' => 'Ringkasan',
        'skills' => 'Skill',
        'experiences' => 'Pengalaman',
        'projects' => 'Proyek',
        'education' => 'Pendidikan',
        'certifications' => 'Sertifikasi',
        'languages' => 'Bahasa',
    ];

    protected $fillable = [
        'job_application_id',
        'job_requirement_analysis_id',
        'type',
        'title',
        'template_key',
        'language',
        'tone',
        'version',
        'generation_status',
        'error_code',
        'content_json',
        'model_name',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'version' => 'integer',
        ];
    }

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(JobRequirementAnalysis::class, 'job_requirement_analysis_id');
    }

    public function isPending(): bool
    {
        return $this->generation_status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->generation_status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->generation_status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->generation_status === self::STATUS_FAILED;
    }

    public function isFinished(): bool
    {
        return $this->isCompleted() || $this->isFailed();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function errorMessage(): ?string
    {
        if ($this->error_code === null) {
            return null;
        }

        return self::ERROR_MESSAGES[$this->error_code] ?? self::ERROR_MESSAGES['generation_failed'];
    }

    public function markFailed(string $errorCode): void
    {
        $this->update([
            'generation_status' => self::STATUS_FAILED,
            'error_code' => $errorCode,
        ]);
    }

    /**
     * Claims the model made that are not backed by profile data.
     *
     * @return list<string>
     */
    public function unverifiedClaimList(): array
    {
        $claims = $this->content_json['unverified_claims'] ?? [];

        return is_array($claims) ? array_values(array_filter($claims, 'is_string')) : [];
    }
}
