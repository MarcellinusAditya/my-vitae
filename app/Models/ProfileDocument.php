<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProfileDocument extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** Friendly, actionable messages for each failure code. Raw provider output is never shown. */
    public const ERROR_MESSAGES = [
        'unsupported_type' => 'Format file tidak didukung. Unggah PDF atau DOCX.',
        'no_text' => 'Tidak ada teks yang bisa dibaca dari file ini. Jika ini hasil scan, unggah versi yang berisi teks atau DOCX.',
        'provider_unavailable' => 'Layanan AI tidak tersedia atau belum dikonfigurasi. Coba lagi nanti.',
        'invalid_output' => 'Hasil pembacaan AI tidak dapat diproses. Coba unggah ulang file.',
        'extraction_failed' => 'Terjadi kesalahan saat membaca file. Coba lagi.',
        'file_missing' => 'File sumber tidak ditemukan di penyimpanan.',
    ];

    /** Draft section keys in display order, with Indonesian labels. */
    public const DRAFT_SECTIONS = [
        'contact' => 'Kontak',
        'summary' => 'Ringkasan',
        'experiences' => 'Pengalaman',
        'educations' => 'Pendidikan',
        'skills' => 'Skill',
        'projects' => 'Proyek',
        'certifications' => 'Sertifikasi',
    ];

    protected $fillable = [
        'user_id',
        'career_profile_id',
        'original_filename',
        'storage_path',
        'mime_type',
        'file_size',
        'extraction_status',
        'extraction_error_code',
        'draft',
        'model_name',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'draft' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Remove the source file whenever the record is deleted through Eloquent.
        static::deleting(function (ProfileDocument $document): void {
            if ($document->storage_path !== null && $document->storage_path !== '') {
                Storage::disk('local')->delete($document->storage_path);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function careerProfile(): BelongsTo
    {
        return $this->belongsTo(CareerProfile::class);
    }

    public function isPending(): bool
    {
        return $this->extraction_status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->extraction_status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->extraction_status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->extraction_status === self::STATUS_FAILED;
    }

    public function isFinished(): bool
    {
        return $this->isCompleted() || $this->isFailed();
    }

    public function hasDraft(): bool
    {
        return $this->isCompleted() && is_array($this->draft) && ($this->draft['sections'] ?? []) !== [];
    }

    public function errorMessage(): ?string
    {
        if ($this->extraction_error_code === null) {
            return null;
        }

        return self::ERROR_MESSAGES[$this->extraction_error_code] ?? self::ERROR_MESSAGES['extraction_failed'];
    }

    public function markFailed(string $errorCode): void
    {
        $this->update([
            'extraction_status' => self::STATUS_FAILED,
            'extraction_error_code' => $errorCode,
        ]);
    }
}
