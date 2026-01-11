<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Lead\Models\LeadProxy;
use Webkul\User\Models\UserProxy;

class LeadMergeLog extends Model
{
    use HasUuids;

    protected $table = 'lead_merge_log';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'primary_lead_id',
        'merged_lead_id',
        'merge_reason',
        'merge_data',
        'fields_updated',
        'merged_by',
    ];

    protected $casts = [
        'merge_data' => 'array',
        'fields_updated' => 'array',
        'created_at' => 'datetime',
    ];

    public function primaryLead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass(), 'primary_lead_id');
    }

    public function mergedLead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass(), 'merged_lead_id');
    }

    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'merged_by');
    }
}
