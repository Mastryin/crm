<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NegativeKeyword extends Model
{
    use HasUuids;

    protected $table = 'negative_keywords';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'keyword',
        'field_name',
        'match_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    const MATCH_TYPES = [
        'exact' => 'Exact Match',
        'contains' => 'Contains',
        'starts_with' => 'Starts With',
        'ends_with' => 'Ends With',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForField($query, string $fieldName)
    {
        return $query->where('field_name', $fieldName);
    }

    public function matches(string $value): bool
    {
        $value = strtolower(trim($value));
        $keyword = strtolower(trim($this->keyword));

        switch ($this->match_type) {
            case 'exact':
                return $value === $keyword;

            case 'contains':
                return str_contains($value, $keyword);

            case 'starts_with':
                return str_starts_with($value, $keyword);

            case 'ends_with':
                return str_ends_with($value, $keyword);

            default:
                return false;
        }
    }

    public static function checkText(string $text, string $fieldName = 'experience'): array
    {
        $matches = [];
        $keywords = static::active()->forField($fieldName)->get();

        foreach ($keywords as $keyword) {
            if ($keyword->matches($text)) {
                $matches[] = [
                    'keyword' => $keyword->keyword,
                    'match_type' => $keyword->match_type,
                ];
            }
        }

        return $matches;
    }
}
