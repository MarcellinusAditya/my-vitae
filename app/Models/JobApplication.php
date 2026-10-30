<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobApplication extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready_to_apply';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_INTERVIEW = 'interview';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_OFFER = 'offer';

    /** Application statuses in workflow order, with Indonesian labels. */
    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_READY => 'Siap dilamar',
        self::STATUS_APPLIED => 'Sudah dilamar',
        self::STATUS_INTERVIEW => 'Interview',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_OFFER => 'Offer',
    ];

    public const LANGUAGES = ['id', 'en'];

    protected $fillable = [
        'user_id',
        'career_profile_id',
        'company_name',
        'job_title',
        'job_location',
        'job_description',
        'job_qualifications',
        'language',
        'status',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function careerProfile(): BelongsTo
    {
        return $this->belongsTo(CareerProfile::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(JobRequirementAnalysis::class)->latest('id');
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class)->latest('id');
    }

    protected static function booted(): void
    {
        // Bulk deletes (e.g. $user->jobApplications()->delete()) bypass model
        // events, so export/revision files would be orphaned. Clean up in order.
        static::deleting(function (JobApplication $application): void {
            foreach ($application->generatedDocuments()->get() as $document) {
                $document->delete(); // removes exports (with files) + revisions
            }
        });
    }

    /**
     * Latest generation run of a given type.
     */
    public function latestDocument(string $type): ?GeneratedDocument
    {
        return $this->generatedDocuments()->where('type', $type)->first();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
