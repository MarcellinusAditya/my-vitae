<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileCertification extends Model
{
    use HasFactory;

    protected $table = 'profile_certifications';

    protected $fillable = [
        'career_profile_id',
        'name',
        'issuer',
        'issue_date',
        'expiry_date',
        'credential_url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
            'sort_order' => 'integer',
        ];
    }

    public function careerProfile(): BelongsTo
    {
        return $this->belongsTo(CareerProfile::class);
    }
}
