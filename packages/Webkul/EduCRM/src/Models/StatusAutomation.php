<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StatusAutomation extends Model
{
    use HasUuids;

    protected $table = 'status_automations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'entity_type',
        'from_status',
        'to_status',
        'pipeline_id',
        'from_stage_id',
        'to_stage_id',
        'conditions',
        'actions',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeForStatusChange($query, ?string $fromStatus, string $toStatus)
    {
        return $query->where('to_status', $toStatus)
            ->where(function ($q) use ($fromStatus) {
                $q->whereNull('from_status');
                if ($fromStatus) {
                    $q->orWhere('from_status', $fromStatus);
                }
            });
    }
}
