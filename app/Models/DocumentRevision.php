<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRevision extends Model
{
    use HasFactory;

    public const SOURCE_AUTO = 'auto';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_RESTORE = 'restore';

    public const SOURCE_GENERATED = 'generated';

    public const SOURCE_LABELS = [
        self::SOURCE_GENERATED => 'Hasil AI',
        self::SOURCE_AUTO => 'Autosave',
        self::SOURCE_MANUAL => 'Edit manual',
        self::SOURCE_RESTORE => 'Restore',
    ];

    protected $fillable = [
        'generated_document_id',
        'content_json',
        'section_order',
        'word_count',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'section_order' => 'array',
            'word_count' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source] ?? $this->source;
    }
}
