<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Contact\Models\PersonProxy;
use Webkul\User\Models\UserProxy;

class NotificationLog extends Model
{
    use HasUuids;

    protected $table = 'notification_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'lead_id',
        'person_id',
        'user_id',
        'channel',
        'template_name',
        'template_id',
        'recipient',
        'subject',
        'content',
        'variables',
        'provider',
        'provider_message_id',
        'provider_response',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'error_message',
        'retry_count',
    ];

    protected $casts = [
        'variables' => 'array',
        'provider_response' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    const CHANNELS = [
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'push' => 'Push Notification',
        'in_app' => 'In-App',
    ];

    const STATUSES = [
        'pending' => 'Pending',
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'read' => 'Read',
        'failed' => 'Failed',
        'bounced' => 'Bounced',
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

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeForLead($query, int $leadId)
    {
        return $query->where('lead_id', $leadId);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeNeedsRetry($query, int $maxRetries = 3)
    {
        return $query->where('status', 'failed')
            ->where('retry_count', '<', $maxRetries);
    }

    public function markAsSent(string $messageId = null): void
    {
        $this->status = 'sent';
        $this->sent_at = now();
        $this->provider_message_id = $messageId;
        $this->save();
    }

    public function markAsDelivered(): void
    {
        $this->status = 'delivered';
        $this->delivered_at = now();
        $this->save();
    }

    public function markAsRead(): void
    {
        $this->status = 'read';
        $this->read_at = now();
        $this->save();
    }

    public function markAsFailed(string $error, array $response = []): void
    {
        $this->status = 'failed';
        $this->error_message = $error;
        $this->provider_response = $response;
        $this->save();
    }

    public function incrementRetry(): void
    {
        $this->increment('retry_count');
        $this->status = 'pending';
        $this->save();
    }

    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->status === 'failed' && $this->retry_count < $maxRetries;
    }
}
