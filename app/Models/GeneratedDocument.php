<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public const TEMPLATES = [
        'ats_plain' => 'ATS Standar',
        'ats_compact' => 'ATS Rapat',
    ];

    /** Soft limits used for overflow warnings (not hard errors). */
    public const WORD_LIMITS = [
        'resume' => ['concise' => 700, 'detailed' => 1200],
        'cover_letter' => ['default' => 450],
    ];

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
        'parent_version_id',
        'type',
        'title',
        'template_key',
        'language',
        'tone',
        'version',
        'generation_status',
        'error_code',
        'content_json',
        'section_order',
        'word_count',
        'last_saved_at',
        'model_name',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'section_order' => 'array',
            'version' => 'integer',
            'word_count' => 'integer',
            'last_saved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A DB-level cascade would delete export rows without firing their model
        // events, leaving files orphaned on disk. Delete through Eloquent instead.
        static::deleting(function (GeneratedDocument $document): void {
            $document->exports()->get()->each->delete();
            $document->revisions()->delete();
        });
    }

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class)->latest('id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(DocumentExport::class, 'generated_document_id')->latest('id');
    }

    /**
     * Section order for rendering: stored order, or the default for the type.
     *
     * @return list<string>
     */
    public function resolvedSectionOrder(): array
    {
        $stored = $this->section_order ?? [];

        if ($this->type === self::TYPE_COVER_LETTER) {
            return ['opening', 'body_paragraphs', 'closing'];
        }

        $allowed = array_keys(self::RESUME_SECTIONS);
        $stored = array_values(array_filter($stored, fn ($section) => in_array($section, $allowed, true)));

        // Append any section the stored order forgot (e.g. older documents).
        foreach ($allowed as $section) {
            if (! in_array($section, $stored, true)) {
                $stored[] = $section;
            }
        }

        return $stored;
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
