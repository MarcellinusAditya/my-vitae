<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DocumentExport extends Model
{
    use HasFactory;

    public const FORMAT_PDF = 'pdf';

    public const FORMAT_DOCX = 'docx';

    public const FORMATS = [
        self::FORMAT_PDF => 'PDF',
        self::FORMAT_DOCX => 'DOCX',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const ERROR_MESSAGES = [
        'render_failed' => 'Gagal membuat file. Coba lagi.',
        'document_not_ready' => 'Dokumen belum siap diekspor. Selesaikan generate dulu.',
        'unsupported_format' => 'Format ekspor tidak didukung.',
    ];

    protected $fillable = [
        'generated_document_id',
        'format',
        'filename',
        'storage_path',
        'file_size',
        'status',
        'error_code',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (DocumentExport $export): void {
            if ($export->storage_path !== null && $export->storage_path !== '') {
                Storage::disk('local')->delete($export->storage_path);
            }
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function formatLabel(): string
    {
        return self::FORMATS[$this->format] ?? strtoupper($this->format);
    }

    public function errorMessage(): ?string
    {
        if ($this->error_code === null) {
            return null;
        }

        return self::ERROR_MESSAGES[$this->error_code] ?? self::ERROR_MESSAGES['render_failed'];
    }
}
