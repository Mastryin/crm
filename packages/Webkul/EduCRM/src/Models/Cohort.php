<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cohort extends Model
{
    use HasUuids;

    protected $table = 'cohorts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'program_id',
        'name',
        'start_date',
        'end_date',
        'application_deadline',
        'capacity',
        'enrolled_count',
        'status',
        'metadata',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'application_deadline' => 'datetime',
        'metadata' => 'array',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function leadExtensions(): HasMany
    {
        return $this->hasMany(LeadExtension::class);
    }

    public function studentPayments(): HasMany
    {
        return $this->hasMany(StudentPayment::class);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAcceptingApplications($query)
    {
        return $query->where('status', 'upcoming')
            ->where('application_deadline', '>', now())
            ->whereRaw('enrolled_count < capacity');
    }

    public function hasCapacity(): bool
    {
        return $this->enrolled_count < $this->capacity;
    }

    public function isAcceptingApplications(): bool
    {
        return $this->status === 'upcoming'
            && $this->application_deadline > now()
            && $this->hasCapacity();
    }

    public function incrementEnrollment(): void
    {
        $this->increment('enrolled_count');
    }

    public function decrementEnrollment(): void
    {
        if ($this->enrolled_count > 0) {
            $this->decrement('enrolled_count');
        }
    }
}
