<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Lead\Models\LeadProxy;
use Webkul\User\Models\UserProxy;

class LeadQualification extends Model
{
    use HasUuids;

    protected $table = 'lead_qualifications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'lead_id',
        'is_qualified',
        'qualification_score',
        'disqualification_reasons',
        'matched_rules',
        'qualified_at',
        'reviewed_by',
        'reviewed_at',
        'cohort_id',
        'program_id',
    ];

    protected $casts = [
        'is_qualified' => 'boolean',
        'qualification_score' => 'integer',
        'disqualification_reasons' => 'array',
        'matched_rules' => 'array',
        'qualified_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass(), 'lead_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'reviewed_by');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function scopeQualified($query)
    {
        return $query->where('is_qualified', true);
    }

    public function scopeDisqualified($query)
    {
        return $query->where('is_qualified', false);
    }

    public function scopeNeedsReview($query)
    {
        return $query->whereNull('reviewed_at');
    }

    public function addDisqualificationReason(string $reason): void
    {
        $reasons = $this->disqualification_reasons ?? [];
        $reasons[] = $reason;
        $this->disqualification_reasons = array_unique($reasons);
    }

    public function addMatchedRule(array $ruleResult): void
    {
        $rules = $this->matched_rules ?? [];
        $rules[] = $ruleResult;
        $this->matched_rules = $rules;
    }

    public function markAsQualified(): void
    {
        $this->is_qualified = true;
        $this->qualified_at = now();
        $this->save();
    }

    public function markAsDisqualified(array $reasons = []): void
    {
        $this->is_qualified = false;
        $this->disqualification_reasons = array_merge(
            $this->disqualification_reasons ?? [],
            $reasons
        );
        $this->save();
    }

    public function markAsReviewed(int $userId): void
    {
        $this->reviewed_by = $userId;
        $this->reviewed_at = now();
        $this->save();
    }
}
