<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Contact\Models\PersonProxy;
use Webkul\User\Models\UserProxy;

class ScheduledCall extends Model
{
    use HasUuids;

    protected $table = 'scheduled_calls';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'lead_id',
        'person_id',
        'user_id',
        'call_type',
        'scheduled_at',
        'duration_minutes',
        'meeting_link',
        'trafft_booking_id',
        'trafft_service_id',
        'status',
        'notes',
        'outcome',
        'outcome_details',
        'reminder_sent',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'outcome_details' => 'array',
        'reminder_sent' => 'boolean',
    ];

    const CALL_TYPES = [
        'sales' => 'Sales Call',
        'interview' => 'Interview',
        'followup' => 'Follow-up',
        'support' => 'Support',
        'demo' => 'Demo',
    ];

    const STATUSES = [
        'scheduled' => 'Scheduled',
        'confirmed' => 'Confirmed',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show',
        'rescheduled' => 'Rescheduled',
    ];

    const OUTCOMES = [
        'interested' => 'Interested',
        'not_interested' => 'Not Interested',
        'callback_requested' => 'Callback Requested',
        'enrolled' => 'Enrolled',
        'need_more_info' => 'Needs More Information',
        'no_answer' => 'No Answer',
        'wrong_number' => 'Wrong Number',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass(), 'lead_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(PersonProxy::modelClass(), 'person_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'user_id');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeUpcoming($query, int $hours = 24)
    {
        return $query->whereIn('status', ['scheduled', 'confirmed'])
            ->whereBetween('scheduled_at', [now(), now()->addHours($hours)]);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForLead($query, int $leadId)
    {
        return $query->where('lead_id', $leadId);
    }

    public function scopeNeedsReminder($query, int $hoursBeforeCall = 1)
    {
        return $query->whereIn('status', ['scheduled', 'confirmed'])
            ->where('reminder_sent', false)
            ->whereBetween('scheduled_at', [
                now(),
                now()->addHours($hoursBeforeCall),
            ]);
    }

    public function isPast(): bool
    {
        return $this->scheduled_at < now();
    }

    public function isUpcoming(): bool
    {
        return $this->scheduled_at > now() && in_array($this->status, ['scheduled', 'confirmed']);
    }

    public function markAsCompleted(string $outcome, array $details = []): void
    {
        $this->status = 'completed';
        $this->outcome = $outcome;
        $this->outcome_details = $details;
        $this->save();
    }

    public function markAsCancelled(string $reason = null): void
    {
        $this->status = 'cancelled';
        $this->notes = $reason ? ($this->notes . "\nCancelled: " . $reason) : $this->notes;
        $this->save();
    }

    public function markAsNoShow(): void
    {
        $this->status = 'no_show';
        $this->save();
    }

    public function markReminderSent(): void
    {
        $this->reminder_sent = true;
        $this->save();
    }

    public function reschedule(\DateTime $newDateTime): void
    {
        $this->status = 'rescheduled';
        $this->save();

        self::create([
            'lead_id' => $this->lead_id,
            'person_id' => $this->person_id,
            'user_id' => $this->user_id,
            'call_type' => $this->call_type,
            'scheduled_at' => $newDateTime,
            'duration_minutes' => $this->duration_minutes,
            'notes' => 'Rescheduled from ' . $this->scheduled_at->format('Y-m-d H:i'),
            'status' => 'scheduled',
        ]);
    }
}
