<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentPlan extends Model
{
    use HasUuids;

    protected $table = 'payment_plans';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'program_id',
        'total_amount',
        'currency',
        'installment_count',
        'installment_interval_days',
        'is_active',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'installment_count' => 'integer',
        'installment_interval_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function studentPayments(): HasMany
    {
        return $this->hasMany(StudentPayment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForProgram($query, string $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function getInstallmentAmount(): float
    {
        if ($this->installment_count <= 0) {
            return $this->total_amount;
        }

        return round($this->total_amount / $this->installment_count, 2);
    }

    public function generateInstallmentSchedule(\DateTime $startDate): array
    {
        $schedule = [];
        $installmentAmount = $this->getInstallmentAmount();

        for ($i = 1; $i <= $this->installment_count; $i++) {
            $dueDate = clone $startDate;
            $dueDate->modify('+' . (($i - 1) * $this->installment_interval_days) . ' days');

            $schedule[] = [
                'installment_number' => $i,
                'amount' => $installmentAmount,
                'due_date' => $dueDate->format('Y-m-d'),
            ];
        }

        return $schedule;
    }
}
