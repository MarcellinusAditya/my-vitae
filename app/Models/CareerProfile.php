<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CareerProfile extends Model
{
    use HasFactory;

    public const LANGUAGES = ['id', 'en'];

    public const WRITING_STYLES = ['professional', 'formal', 'warm'];

    public const RESUME_LENGTHS = ['concise', 'detailed'];

    public const DATE_FORMATS = ['month_year', 'numeric', 'year_only'];

    protected $fillable = [
        'user_id',
        'title',
        'summary',
        'default_language',
        'writing_style',
        'resume_length',
        'date_format',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): HasOne
    {
        return $this->hasOne(ProfileContact::class, 'career_profile_id');
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(ProfileExperience::class, 'career_profile_id')->orderBy('sort_order');
    }

    public function educations(): HasMany
    {
        return $this->hasMany(ProfileEducation::class, 'career_profile_id')->orderBy('sort_order');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(ProfileSkill::class, 'career_profile_id')->orderBy('sort_order');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(ProfileProject::class, 'career_profile_id')->orderBy('sort_order');
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(ProfileCertification::class, 'career_profile_id')->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProfileDocument::class, 'career_profile_id')->latest();
    }

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'career_profile_id');
    }
}
