<?php

namespace Webkul\EduCRM\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasUuids;

    protected $table = 'notification_templates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'code',
        'channel',
        'subject',
        'content',
        'variables',
        'trigger_event',
        'trigger_status',
        'is_active',
        'provider_template_id',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeByTrigger($query, string $event, string $status = null)
    {
        $query = $query->where('trigger_event', $event);

        if ($status) {
            $query->where('trigger_status', $status);
        }

        return $query;
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function render(array $data = []): array
    {
        $subject = $this->subject;
        $content = $this->content;

        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $subject = str_replace($placeholder, $value, $subject);
            $content = str_replace($placeholder, $value, $content);
        }

        return [
            'subject' => $subject,
            'content' => $content,
        ];
    }

    public function getRequiredVariables(): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $this->content . ' ' . $this->subject, $matches);
        return array_unique($matches[1] ?? []);
    }

    public static function findByCode(string $code): ?self
    {
        return self::active()->byCode($code)->first();
    }

    public static function findForTrigger(string $event, string $status, string $channel): ?self
    {
        return self::active()
            ->byChannel($channel)
            ->byTrigger($event, $status)
            ->first();
    }
}
