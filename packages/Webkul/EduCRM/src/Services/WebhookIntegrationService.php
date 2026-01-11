<?php

namespace Webkul\EduCRM\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Models\Lead;
use Webkul\EduCRM\Models\LeadExtension;
use Webkul\EduCRM\Models\NotificationLog;

class WebhookIntegrationService
{
    protected ?string $pabblyWebhookUrl;
    protected ?string $trafftApiKey;
    protected ?string $trafftBaseUrl;

    public function __construct()
    {
        $this->pabblyWebhookUrl = config('educrm.integrations.pabbly.webhook_url');
        $this->trafftApiKey = config('educrm.integrations.trafft.api_key');
        $this->trafftBaseUrl = config('educrm.integrations.trafft.base_url');
    }

    public function triggerPabblyWebhook(string $event, array $data): array
    {
        if (!$this->pabblyWebhookUrl) {
            Log::warning('Pabbly webhook URL not configured');
            return ['success' => false, 'error' => 'Webhook URL not configured'];
        }

        try {
            $payload = [
                'event' => $event,
                'timestamp' => now()->toIso8601String(),
                'data' => $data,
            ];

            $response = Http::timeout(30)
                ->post($this->pabblyWebhookUrl, $payload);

            $result = [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => $response->json(),
            ];

            Log::info("Pabbly webhook triggered: {$event}", $result);

            return $result;
        } catch (\Exception $e) {
            Log::error("Pabbly webhook failed: {$e->getMessage()}", [
                'event' => $event,
                'data' => $data,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendLeadToPabbly(Lead $lead, string $event = 'lead.created'): array
    {
        $extension = LeadExtension::where('lead_id', $lead->id)->first();

        $data = [
            'lead_id' => $lead->id,
            'title' => $lead->title,
            'description' => $lead->description,
            'lead_value' => $lead->lead_value,
            'status' => $lead->status,
            'stage' => $lead->stage?->name,
            'stage_code' => $lead->stage?->code,
            'pipeline' => $lead->pipeline?->name,
            'source' => $lead->source?->name,
            'type' => $lead->type?->name,
            'assigned_to' => $lead->user?->name,
            'assigned_to_email' => $lead->user?->email,
            'created_at' => $lead->created_at?->toIso8601String(),
            'updated_at' => $lead->updated_at?->toIso8601String(),
        ];

        if ($lead->person) {
            $data['person'] = [
                'id' => $lead->person->id,
                'name' => $lead->person->name,
                'email' => $lead->person->emails[0]['value'] ?? null,
                'phone' => $lead->person->contact_numbers[0]['value'] ?? null,
                'organization' => $lead->person->organization?->name,
            ];
        }

        if ($extension) {
            $data['extension'] = [
                'source_channel' => $extension->source_channel,
                'qualification_status' => $extension->qualification_status,
                'qualification_score' => $extension->qualification_score,
                'experience_years' => $extension->experience_years,
                'job_role' => $extension->job_role,
                'company_name' => $extension->company_name,
                'education_level' => $extension->education_level,
                'program_id' => $extension->program_id,
                'cohort_id' => $extension->cohort_id,
                'utm_source' => $extension->utm_source,
                'utm_medium' => $extension->utm_medium,
                'utm_campaign' => $extension->utm_campaign,
            ];
        }

        return $this->triggerPabblyWebhook($event, $data);
    }

    public function syncCallToTrafft(array $callData): array
    {
        if (!$this->trafftApiKey || !$this->trafftBaseUrl) {
            Log::warning('Trafft API not configured');
            return ['success' => false, 'error' => 'Trafft API not configured'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->trafftApiKey,
                'Content-Type' => 'application/json',
            ])->post($this->trafftBaseUrl . '/appointments', [
                'serviceId' => $callData['service_id'],
                'providerId' => $callData['provider_id'],
                'customerId' => $callData['customer_id'] ?? null,
                'customerFirstName' => $callData['customer_name'],
                'customerEmail' => $callData['customer_email'],
                'customerPhone' => $callData['customer_phone'],
                'bookingStart' => $callData['scheduled_at'],
                'notifyParticipants' => true,
                'note' => $callData['notes'] ?? '',
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'booking_id' => $response->json()['data']['id'] ?? null,
                    'response' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->body(),
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error("Trafft API failed: {$e->getMessage()}", $callData);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function cancelTrafftBooking(string $bookingId): array
    {
        if (!$this->trafftApiKey || !$this->trafftBaseUrl) {
            return ['success' => false, 'error' => 'Trafft API not configured'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->trafftApiKey,
            ])->delete($this->trafftBaseUrl . '/appointments/' . $bookingId);

            return [
                'success' => $response->successful(),
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Trafft cancellation failed: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function rescheduleTrafftBooking(string $bookingId, string $newDateTime): array
    {
        if (!$this->trafftApiKey || !$this->trafftBaseUrl) {
            return ['success' => false, 'error' => 'Trafft API not configured'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->trafftApiKey,
                'Content-Type' => 'application/json',
            ])->put($this->trafftBaseUrl . '/appointments/' . $bookingId, [
                'bookingStart' => $newDateTime,
            ]);

            return [
                'success' => $response->successful(),
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Trafft reschedule failed: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function triggerCustomWebhook(string $url, string $method, array $payload, array $headers = []): array
    {
        try {
            $defaultHeaders = [
                'Content-Type' => 'application/json',
            ];
            $headers = array_merge($defaultHeaders, $headers);

            $http = Http::withHeaders($headers)->timeout(30);

            switch (strtoupper($method)) {
                case 'GET':
                    $response = $http->get($url, $payload);
                    break;
                case 'POST':
                    $response = $http->post($url, $payload);
                    break;
                case 'PUT':
                    $response = $http->put($url, $payload);
                    break;
                case 'DELETE':
                    $response = $http->delete($url, $payload);
                    break;
                default:
                    return ['success' => false, 'error' => 'Unsupported HTTP method'];
            }

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error("Custom webhook failed: {$e->getMessage()}", [
                'url' => $url,
                'method' => $method,
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function handleIncomingWebhook(string $source, array $payload, string $signature = null): array
    {
        Log::info("Incoming webhook from {$source}", ['payload' => $payload]);

        switch ($source) {
            case 'trafft':
                return $this->handleTrafftWebhook($payload);
            case 'aisensy':
                return $this->handleAisensyWebhook($payload);
            default:
                return ['success' => true, 'message' => 'Webhook received'];
        }
    }

    protected function handleTrafftWebhook(array $payload): array
    {
        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        Log::info("Trafft webhook: {$event}", $data);

        return ['success' => true, 'event' => $event];
    }

    protected function handleAisensyWebhook(array $payload): array
    {
        $messageId = $payload['messageId'] ?? null;
        $status = $payload['status'] ?? null;

        if ($messageId && $status) {
            $log = NotificationLog::where('provider_message_id', $messageId)->first();

            if ($log) {
                switch ($status) {
                    case 'delivered':
                        $log->markAsDelivered();
                        break;
                    case 'read':
                        $log->markAsRead();
                        break;
                    case 'failed':
                        $log->markAsFailed($payload['error'] ?? 'Unknown error');
                        break;
                }
            }
        }

        return ['success' => true];
    }
}
