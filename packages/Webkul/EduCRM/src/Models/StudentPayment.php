<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Contact\Models\PersonProxy;

class StudentPayment extends Model
{
    use HasUuids;

    protected $table = 'student_payments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'lead_id',
        'person_id',
        'cohort_id',
        'payment_plan_id',
        'total_amount',
        'paid_amount',
        'discount_amount',
        'discount_reason',
        'currency',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    const STATUSES = [
        'pending' => 'Pending',
        'partial' => 'Partially Paid',
        'completed' => 'Completed',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass(), 'lead_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(PersonProxy::modelClass(), 'person_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentInstallment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePartial($query)
    {
        return $query->where('status', 'partial');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function getOutstandingAmount(): float
    {
        return max(0, $this->total_amount - $this->discount_amount - $this->paid_amount);
    }

    public function getEffectiveTotal(): float
    {
        return $this->total_amount - $this->discount_amount;
    }

    public function isFullyPaid(): bool
    {
        return $this->paid_amount >= $this->getEffectiveTotal();
    }

    public function updateStatus(): void
    {
        if ($this->isFullyPaid()) {
            $this->status = 'completed';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partial';
        } elseif ($this->hasOverdueInstallments()) {
            $this->status = 'overdue';
        } else {
            $this->status = 'pending';
        }

        $this->save();
    }

    public function hasOverdueInstallments(): bool
    {
        return $this->installments()
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->exists();
    }

    public function recordPayment(float $amount): void
    {
        $this->paid_amount += $amount;
        $this->updateStatus();
    }

    public function createInstallments(): void
    {
        if (!$this->paymentPlan) {
            return;
        }

        $schedule = $this->paymentPlan->generateInstallmentSchedule(now());

        foreach ($schedule as $item) {
            $this->installments()->create([
                'installment_number' => $item['installment_number'],
                'amount' => $item['amount'],
                'due_date' => $item['due_date'],
                'status' => 'pending',
            ]);
        }
    }
}
