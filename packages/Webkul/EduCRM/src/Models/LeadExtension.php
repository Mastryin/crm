<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Lead\Models\LeadProxy;

class LeadExtension extends Model
{
    use HasUuids;

    protected $table = 'lead_extensions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'lead_id',
        'source_channel',
        'source_campaign',
        'source_medium',
        'source_form_id',
        'original_source_data',
        'cohort_id',
        'program_id',
        'qualification_status',
        'qualification_score',
        'phone_normalized',
        'email_normalized',
        'is_duplicate',
        'duplicate_of_lead_id',
        'merge_history',
        'experience_years',
        'job_role',
        'company_name',
        'education_level',
        'linkedin_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'referrer_url',
        'landing_page',
        'device_type',
        'browser',
        'ip_address',
        'country',
        'city',
        'form_submitted_at',
        'first_response_at',
    ];

    protected $casts = [
        'original_source_data' => 'array',
        'merge_history' => 'array',
        'is_duplicate' => 'boolean',
        'experience_years' => 'decimal:1',
        'form_submitted_at' => 'datetime',
        'first_response_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass(), 'lead_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass(), 'duplicate_of_lead_id');
    }

    public function scopeQualified($query)
    {
        return $query->where('qualification_status', 'qualified');
    }

    public function scopeDisqualified($query)
    {
        return $query->where('qualification_status', 'disqualified');
    }

    public function scopePendingQualification($query)
    {
        return $query->where('qualification_status', 'pending');
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source_channel', $source);
    }

    public function scopeNotDuplicate($query)
    {
        return $query->where('is_duplicate', false);
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        return $phone;
    }

    public static function normalizeEmail(?string $email): ?string
    {
        if (empty($email)) {
            return null;
        }

        return strtolower(trim($email));
    }
}
