<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\User\Models\UserProxy;

class UserAssignment extends Model
{
    use HasUuids;

    protected $table = 'user_assignments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'current_load',
        'max_load',
        'daily_limit',
        'today_assigned',
        'last_assigned_at',
        'is_available',
        'assignment_weight',
        'specializations',
    ];

    protected $casts = [
        'current_load' => 'integer',
        'max_load' => 'integer',
        'daily_limit' => 'integer',
        'today_assigned' => 'integer',
        'last_assigned_at' => 'datetime',
        'is_available' => 'boolean',
        'assignment_weight' => 'integer',
        'specializations' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'user_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)
            ->whereRaw('current_load < max_load')
            ->whereRaw('today_assigned < daily_limit');
    }

    public function scopeWithCapacity($query)
    {
        return $query->whereRaw('current_load < max_load');
    }

    public function scopeOrderedByLoad($query)
    {
        return $query->orderBy('current_load', 'asc')
            ->orderBy('last_assigned_at', 'asc');
    }

    public function scopeWithSpecialization($query, string $specialization)
    {
        return $query->whereJsonContains('specializations', $specialization);
    }

    public function canAcceptLead(): bool
    {
        return $this->is_available
            && $this->current_load < $this->max_load
            && $this->today_assigned < $this->daily_limit;
    }

    public function hasCapacity(): bool
    {
        return $this->current_load < $this->max_load;
    }

    public function incrementLoad(): void
    {
        $this->increment('current_load');
        $this->increment('today_assigned');
        $this->last_assigned_at = now();
        $this->save();
    }

    public function decrementLoad(): void
    {
        if ($this->current_load > 0) {
            $this->decrement('current_load');
        }
    }

    public function resetDailyCounter(): void
    {
        $this->today_assigned = 0;
        $this->save();
    }

    public function hasSpecialization(string $specialization): bool
    {
        return in_array($specialization, $this->specializations ?? []);
    }

    public function addSpecialization(string $specialization): void
    {
        $specs = $this->specializations ?? [];
        if (!in_array($specialization, $specs)) {
            $specs[] = $specialization;
            $this->specializations = $specs;
            $this->save();
        }
    }

    public function removeSpecialization(string $specialization): void
    {
        $specs = $this->specializations ?? [];
        $this->specializations = array_values(array_diff($specs, [$specialization]));
        $this->save();
    }

    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'current_load' => 0,
                'max_load' => config('educrm.assignment.default_max_load', 50),
                'daily_limit' => config('educrm.assignment.default_daily_limit', 10),
                'today_assigned' => 0,
                'is_available' => true,
                'assignment_weight' => 1,
                'specializations' => [],
            ]
        );
    }
}
