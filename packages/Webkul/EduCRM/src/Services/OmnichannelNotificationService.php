<?php

namespace Webkul\EduCRM\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Webkul\EduCRM\Models\NotificationLog;
use Webkul\EduCRM\Models\NotificationTemplate;
use Webkul\Lead\Models\Lead;
use Webkul\Contact\Models\Person;

class OmnichannelNotificationService
{
    protected array $aisensyConfig;
    protected array $enchargeConfig;

    public function __construct()
    {
        $this->aisensyConfig = config('educrm.integrations.aisensy', []);
        $this->enchargeConfig = config('educrm.integrations.encharge', []);
    }

    public function send(string $channel, string $recipient, string $templateCode, array $variables = [], array $context = []): NotificationLog
    {
        $template = NotificationTemplate::findByCode($templateCode);

        if (!$template) {
            throw new \Exception("Notification template '{$templateCode}' not found");
        }

        $log = $this->createLog($channel, $recipient, $template, $variables, $context);

        try {
            $rendered = $template->render($variables);

            switch ($channel) {
                case 'email':
                    $this->sendEmail($recipient, $rendered['subject'], $rendered['content'], $log);
                    break;

                case 'whatsapp':
                    $this->sendWhatsApp($recipient, $template, $variables, $log);
                    break;

                case 'sms':
                    $this->sendSMS($recipient, $rendered['content'], $log);
                    break;

                default:
                    throw new \Exception("Unsupported notification channel: {$channel}");
            }
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            Log::error("Notification failed: {$e->getMessage()}", [
                'channel' => $channel,
                'recipient' => $recipient,
                'template' => $templateCode,
            ]);
        }

        return $log;
    }

    public function sendToLead(Lead $lead, string $templateCode, array $additionalVariables = []): array
    {
        $logs = [];

        $person = $lead->person;
        if (!$person) {
            return $logs;
        }

        $variables = $this->buildLeadVariables($lead, $person);
        $variables = array_merge($variables, $additionalVariables);

        $template = NotificationTemplate::findByCode($templateCode);
        if (!$template) {
            return $logs;
        }

        $context = [
            'lead_id' => $lead->id,
            'person_id' => $person->id,
        ];

        switch ($template->channel) {
            case 'email':
                $email = $person->emails[0]['value'] ?? null;
                if ($email) {
                    $logs['email'] = $this->send('email', $email, $templateCode, $variables, $context);
                }
                break;

            case 'whatsapp':
                $phone = $person->contact_numbers[0]['value'] ?? null;
                if ($phone) {
                    $phone = $this->normalizePhone($phone);
                    $logs['whatsapp'] = $this->send('whatsapp', $phone, $templateCode, $variables, $context);
                }
                break;
        }

        return $logs;
    }

    public function sendOnStatusChange(Lead $lead, string $fromStage, string $toStage): array
    {
        $logs = [];

        $templates = NotificationTemplate::active()
            ->where('trigger_event', 'lead.stage_change')
            ->where('trigger_status', $toStage)
            ->get();

        foreach ($templates as $template) {
            $result = $this->sendToLead($lead, $template->code, [
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
            ]);
            $logs = array_merge($logs, $result);
        }

        return $logs;
    }

    protected function sendEmail(string $to, string $subject, string $content, NotificationLog $log): void
    {
        if (!empty($this->enchargeConfig['api_key'])) {
            $this->sendViaEncharge($to, $subject, $content, $log);
        } else {
            $this->sendViaSMTP($to, $subject, $content, $log);
        }
    }

    protected function sendViaSMTP(string $to, string $subject, string $content, NotificationLog $log): void
    {
        Mail::raw($content, function ($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });

        $log->markAsSent();
    }

    protected function sendViaEncharge(string $to, string $subject, string $content, NotificationLog $log): void
    {
        $response = Http::withHeaders([
            'X-Encharge-Token' => $this->enchargeConfig['api_key'],
            'Content-Type' => 'application/json',
        ])->post($this->enchargeConfig['base_url'] . '/emails', [
            'to' => $to,
            'subject' => $subject,
            'html' => $content,
        ]);

        if ($response->successful()) {
            $log->provider = 'encharge';
            $log->provider_response = $response->json();
            $log->markAsSent($response->json()['id'] ?? null);
        } else {
            throw new \Exception('Encharge API error: ' . $response->body());
        }
    }

    protected function sendWhatsApp(string $phone, NotificationTemplate $template, array $variables, NotificationLog $log): void
    {
        if (empty($this->aisensyConfig['api_key'])) {
            throw new \Exception('Aisensy API key not configured');
        }

        $phone = $this->normalizePhone($phone);

        $payload = [
            'apiKey' => $this->aisensyConfig['api_key'],
            'campaignName' => $template->provider_template_id ?? $template->code,
            'destination' => $phone,
            'userName' => $variables['name'] ?? 'User',
            'templateParams' => array_values($variables),
        ];

        $response = Http::post($this->aisensyConfig['base_url'], $payload);

        if ($response->successful()) {
            $log->provider = 'aisensy';
            $log->provider_response = $response->json();
            $log->markAsSent($response->json()['data']['messageId'] ?? null);
        } else {
            throw new \Exception('Aisensy API error: ' . $response->body());
        }
    }

    protected function sendSMS(string $phone, string $content, NotificationLog $log): void
    {
        Log::info("SMS would be sent to {$phone}: {$content}");
        $log->markAsSent();
    }

    protected function createLog(string $channel, string $recipient, NotificationTemplate $template, array $variables, array $context): NotificationLog
    {
        $rendered = $template->render($variables);

        return NotificationLog::create([
            'lead_id' => $context['lead_id'] ?? null,
            'person_id' => $context['person_id'] ?? null,
            'user_id' => $context['user_id'] ?? null,
            'channel' => $channel,
            'template_name' => $template->name,
            'template_id' => $template->id,
            'recipient' => $recipient,
            'subject' => $rendered['subject'],
            'content' => $rendered['content'],
            'variables' => $variables,
            'status' => 'pending',
            'created_at' => now(),
        ]);
    }

    protected function buildLeadVariables(Lead $lead, Person $person): array
    {
        return [
            'name' => $person->name,
            'first_name' => explode(' ', $person->name)[0],
            'email' => $person->emails[0]['value'] ?? '',
            'phone' => $person->contact_numbers[0]['value'] ?? '',
            'lead_title' => $lead->title,
            'lead_value' => number_format($lead->lead_value ?? 0, 2),
            'stage' => $lead->stage?->name ?? '',
            'pipeline' => $lead->pipeline?->name ?? '',
            'assigned_to' => $lead->user?->name ?? '',
            'source' => $lead->source?->name ?? '',
            'type' => $lead->type?->name ?? '',
            'company_name' => $person->organization?->name ?? '',
        ];
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        return $phone;
    }

    public function retryFailed(int $maxRetries = 3): int
    {
        $logs = NotificationLog::needsRetry($maxRetries)->get();
        $retried = 0;

        foreach ($logs as $log) {
            try {
                $log->incrementRetry();
                $this->resend($log);
                $retried++;
            } catch (\Exception $e) {
                Log::error("Retry failed for notification {$log->id}: {$e->getMessage()}");
            }
        }

        return $retried;
    }

    protected function resend(NotificationLog $log): void
    {
        $template = NotificationTemplate::find($log->template_id);
        if (!$template) {
            $log->markAsFailed('Template not found');
            return;
        }

        $rendered = $template->render($log->variables ?? []);

        switch ($log->channel) {
            case 'email':
                $this->sendEmail($log->recipient, $rendered['subject'], $rendered['content'], $log);
                break;

            case 'whatsapp':
                $this->sendWhatsApp($log->recipient, $template, $log->variables ?? [], $log);
                break;

            case 'sms':
                $this->sendSMS($log->recipient, $rendered['content'], $log);
                break;
        }
    }
}
