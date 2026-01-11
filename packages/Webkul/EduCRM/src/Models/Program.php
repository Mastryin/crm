<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasUuids;

    protected $table = 'programs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'duration_weeks',
        'price',
        'currency',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }

    public function activeCohorts(): HasMany
    {
        return $this->hasMany(Cohort::class)->where('status', 'active');
    }

    public function upcomingCohorts(): HasMany
    {
        return $this->hasMany(Cohort::class)
            ->where('status', 'upcoming')
            ->where('application_deadline', '>', now());
    }

    public function paymentPlans(): HasMany
    {
        return $this->hasMany(PaymentPlan::class);
    }

    public function qualificationRules(): HasMany
    {
        return $this->hasMany(QualificationRule::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
