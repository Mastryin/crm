<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\User\Models\UserProxy;

class TrashLead extends Model
{
    use HasUuids;

    protected $table = 'trash_leads';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'original_lead_id',
        'lead_data',
        'person_data',
        'activities_data',
        'deleted_by',
        'deletion_reason',
        'deleted_at',
        'permanent_delete_at',
        'restored',
        'restored_at',
        'restored_by',
    ];

    protected $casts = [
        'lead_data' => 'array',
        'person_data' => 'array',
        'activities_data' => 'array',
        'deleted_at' => 'datetime',
        'permanent_delete_at' => 'datetime',
        'restored' => 'boolean',
        'restored_at' => 'datetime',
    ];

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'deleted_by');
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'restored_by');
    }

    public function scopeExpired($query)
    {
        return $query->where('permanent_delete_at', '<=', now())
            ->where('restored', false);
    }

    public function scopeRestorable($query)
    {
        return $query->where('permanent_delete_at', '>', now())
            ->where('restored', false);
    }

    public function restore(int $restoredBy): bool
    {
        $this->restored = true;
        $this->restored_at = now();
        $this->restored_by = $restoredBy;
        return $this->save();
    }

    public function getDaysUntilDeletion(): int
    {
        if ($this->restored) {
            return -1;
        }

        return max(0, now()->diffInDays($this->permanent_delete_at, false));
    }
}
