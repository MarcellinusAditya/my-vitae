<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobRequirementAnalysis extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const MATCH_MATCHED = 'matched';

    public const MATCH_PARTIAL = 'partial';

    public const MATCH_NOT_EVIDENCED = 'not_evidenced';

    public const MATCHES = [self::MATCH_MATCHED, self::MATCH_PARTIAL, self::MATCH_NOT_EVIDENCED];

    public const ERROR_MESSAGES = [
        'provider_unavailable' => 'Layanan AI tidak tersedia atau belum dikonfigurasi. Coba lagi nanti.',
        'invalid_output' => 'Hasil analisis AI tidak dapat diproses. Coba analisis ulang.',
        'empty_profile' => 'Profil karier masih kosong. Lengkapi profil sebelum menganalisis lowongan.',
        'analysis_failed' => 'Terjadi kesalahan saat menganalisis lowongan. Coba lagi.',
    ];

    protected $fillable = [
        'job_application_id',
        'status',
        'error_code',
        'analysis_json',
        'profile_snapshot',
        'job_snapshot',
        'model_name',
    ];

    protected function casts(): array
    {
        return [
            'analysis_json' => 'array',
            'profile_snapshot' => 'array',
            'job_snapshot' => 'array',
        ];
    }

    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isFinished(): bool
    {
        return $this->isCompleted() || $this->isFailed();
    }

    public function errorMessage(): ?string
    {
        if ($this->error_code === null) {
            return null;
        }

        return self::ERROR_MESSAGES[$this->error_code] ?? self::ERROR_MESSAGES['analysis_failed'];
    }

    public function markFailed(string $errorCode): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_code' => $errorCode,
        ]);
    }

    /**
     * Requirement counts by match status, for list badges and summaries.
     *
     * @return array{matched: int, partial: int, not_evidenced: int, total: int}
     */
    public function counts(): array
    {
        $requirements = $this->analysis_json['requirements'] ?? [];

        $counts = [
            self::MATCH_MATCHED => 0,
            self::MATCH_PARTIAL => 0,
            self::MATCH_NOT_EVIDENCED => 0,
        ];

        foreach ($requirements as $requirement) {
            $status = $requirement['match_status'] ?? self::MATCH_NOT_EVIDENCED;

            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return [
            'matched' => $counts[self::MATCH_MATCHED],
            'partial' => $counts[self::MATCH_PARTIAL],
            'not_evidenced' => $counts[self::MATCH_NOT_EVIDENCED],
            'total' => count($requirements),
        ];
    }
}
