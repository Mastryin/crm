<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentInstallment extends Model
{
    use HasUuids;

    protected $table = 'payment_installments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'student_payment_id',
        'installment_number',
        'amount',
        'due_date',
        'paid_date',
        'status',
        'reminder_count',
        'last_reminder_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'datetime',
        'reminder_count' => 'integer',
        'last_reminder_at' => 'datetime',
    ];

    const STATUSES = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'waived' => 'Waived',
    ];

    public function studentPayment(): BelongsTo
    {
        return $this->belongsTo(StudentPayment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'installment_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<', now()->toDateString());
    }

    public function scopeUpcoming($query, int $days = 7)
    {
        return $query->where('status', 'pending')
            ->whereBetween('due_date', [
                now()->toDateString(),
                now()->addDays($days)->toDateString(),
            ]);
    }

    public function scopeNeedsReminder($query, int $maxReminders = 3, int $reminderIntervalHours = 24)
    {
        return $query->where('status', 'pending')
            ->where('reminder_count', '<', $maxReminders)
            ->where(function ($q) use ($reminderIntervalHours) {
                $q->whereNull('last_reminder_at')
                    ->orWhere('last_reminder_at', '<', now()->subHours($reminderIntervalHours));
            });
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date < now()->toDateString();
    }

    public function isDueSoon(int $days = 3): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $dueDate = $this->due_date;
        $warningDate = now()->addDays($days);

        return $dueDate <= $warningDate && $dueDate >= now()->toDateString();
    }

    public function markAsPaid(\DateTime $paidDate = null): void
    {
        $this->status = 'paid';
        $this->paid_date = $paidDate ?? now();
        $this->save();

        $this->studentPayment->recordPayment($this->amount);
    }

    public function markAsWaived(string $reason = null): void
    {
        $this->status = 'waived';
        $this->notes = $reason ?? $this->notes;
        $this->save();
    }

    public function recordReminderSent(): void
    {
        $this->increment('reminder_count');
        $this->last_reminder_at = now();
        $this->save();
    }
}
