<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\User\Models\UserProxy;

class PaymentTransaction extends Model
{
    use HasUuids;

    protected $table = 'payment_transactions';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'student_payment_id',
        'installment_id',
        'amount',
        'currency',
        'payment_method',
        'transaction_id',
        'gateway_response',
        'status',
        'processed_at',
        'processed_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    const PAYMENT_METHODS = [
        'bank_transfer' => 'Bank Transfer',
        'upi' => 'UPI',
        'card' => 'Credit/Debit Card',
        'cash' => 'Cash',
        'cheque' => 'Cheque',
        'other' => 'Other',
    ];

    const STATUSES = [
        'pending' => 'Pending',
        'success' => 'Success',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ];

    public function studentPayment(): BelongsTo
    {
        return $this->belongsTo(StudentPayment::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(PaymentInstallment::class, 'installment_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'processed_by');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function markAsSuccess(int $processedBy = null): void
    {
        $this->status = 'success';
        $this->processed_at = now();
        $this->processed_by = $processedBy;
        $this->save();

        if ($this->installment) {
            $this->installment->markAsPaid($this->processed_at);
        } else {
            $this->studentPayment->recordPayment($this->amount);
        }
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->status = 'failed';
        $this->notes = $reason ?? $this->notes;
        $this->save();
    }

    public function markAsRefunded(): void
    {
        $this->status = 'refunded';
        $this->save();

        $this->studentPayment->paid_amount -= $this->amount;
        $this->studentPayment->updateStatus();
    }
}
