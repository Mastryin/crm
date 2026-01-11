<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualificationRule extends Model
{
    use HasUuids;

    protected $table = 'qualification_rules';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'entity_type',
        'field_name',
        'operator',
        'value',
        'action',
        'score_value',
        'priority',
        'is_active',
        'program_id',
    ];

    protected $casts = [
        'score_value' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    const OPERATORS = [
        'equals' => '=',
        'not_equals' => '!=',
        'contains' => 'LIKE',
        'not_contains' => 'NOT LIKE',
        'greater_than' => '>',
        'less_than' => '<',
        'greater_equal' => '>=',
        'less_equal' => '<=',
        'in' => 'IN',
        'not_in' => 'NOT IN',
        'is_empty' => 'IS NULL',
        'is_not_empty' => 'IS NOT NULL',
    ];

    const ACTIONS = [
        'qualify' => 'Mark as Qualified',
        'disqualify' => 'Mark as Disqualified',
        'flag' => 'Flag for Review',
        'score_add' => 'Add to Score',
        'score_subtract' => 'Subtract from Score',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    public function evaluate($data): array
    {
        $fieldValue = $this->getFieldValue($data);
        $matched = $this->checkCondition($fieldValue);

        return [
            'matched' => $matched,
            'rule_id' => $this->id,
            'rule_name' => $this->name,
            'action' => $this->action,
            'score_value' => $matched ? $this->score_value : 0,
        ];
    }

    protected function getFieldValue($data)
    {
        if (is_array($data)) {
            return $data[$this->field_name] ?? null;
        }

        if (is_object($data)) {
            return $data->{$this->field_name} ?? null;
        }

        return null;
    }

    protected function checkCondition($fieldValue): bool
    {
        $ruleValue = $this->value;

        switch ($this->operator) {
            case 'equals':
                return $fieldValue == $ruleValue;

            case 'not_equals':
                return $fieldValue != $ruleValue;

            case 'contains':
                return stripos((string) $fieldValue, $ruleValue) !== false;

            case 'not_contains':
                return stripos((string) $fieldValue, $ruleValue) === false;

            case 'greater_than':
                return (float) $fieldValue > (float) $ruleValue;

            case 'less_than':
                return (float) $fieldValue < (float) $ruleValue;

            case 'greater_equal':
                return (float) $fieldValue >= (float) $ruleValue;

            case 'less_equal':
                return (float) $fieldValue <= (float) $ruleValue;

            case 'in':
                $values = array_map('trim', explode(',', $ruleValue));
                return in_array($fieldValue, $values);

            case 'not_in':
                $values = array_map('trim', explode(',', $ruleValue));
                return !in_array($fieldValue, $values);

            case 'is_empty':
                return empty($fieldValue);

            case 'is_not_empty':
                return !empty($fieldValue);

            default:
                return false;
        }
    }
}
