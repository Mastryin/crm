<?php

namespace Webkul\EduCRM\Listeners;

use Illuminate\Support\Facades\Log;
use Webkul\EduCRM\Models\LeadExtension;
use Webkul\EduCRM\Models\StatusAutomation;
use Webkul\EduCRM\Models\TrashLead;
use Webkul\EduCRM\Services\LeadQualificationService;
use Webkul\EduCRM\Services\OmnichannelNotificationService;
use Webkul\EduCRM\Services\RoundRobinAssignmentService;
use Webkul\EduCRM\Services\WebhookIntegrationService;
use Webkul\Lead\Models\Lead;

class LeadEventListener
{
    protected LeadQualificationService $qualificationService;
    protected RoundRobinAssignmentService $assignmentService;
    protected OmnichannelNotificationService $notificationService;
    protected WebhookIntegrationService $webhookService;

    public function __construct(
        LeadQualificationService $qualificationService,
        RoundRobinAssignmentService $assignmentService,
        OmnichannelNotificationService $notificationService,
        WebhookIntegrationService $webhookService
    ) {
        $this->qualificationService = $qualificationService;
        $this->assignmentService = $assignmentService;
        $this->notificationService = $notificationService;
        $this->webhookService = $webhookService;
    }

    public function afterCreate($lead): void
    {
        Log::info("Lead created: {$lead->id}");

        $this->webhookService->sendLeadToPabbly($lead, 'lead.created');

        $this->processAutomations($lead, null, $lead->stage?->code);
    }

    public function afterUpdate($lead): void
    {
        Log::info("Lead updated: {$lead->id}");

        $originalStageId = $lead->getOriginal('lead_pipeline_stage_id');
        $newStageId = $lead->lead_pipeline_stage_id;

        if ($originalStageId !== $newStageId) {
            $this->handleStageChange($lead, $originalStageId, $newStageId);
        }

        $this->webhookService->sendLeadToPabbly($lead, 'lead.updated');
    }

    public function beforeDelete($lead): void
    {
        Log::info("Lead being deleted: {$lead->id}");

        $this->moveToTrash($lead);

        $extension = LeadExtension::where('lead_id', $lead->id)->first();
        if ($extension && $lead->user_id) {
            $this->assignmentService->unassignLead($lead);
        }

        $this->webhookService->sendLeadToPabbly($lead, 'lead.deleted');
    }

    protected function handleStageChange(Lead $lead, ?int $originalStageId, ?int $newStageId): void
    {
        $originalStage = $originalStageId
            ? \Webkul\Lead\Models\Stage::find($originalStageId)
            : null;

        $newStage = $newStageId
            ? \Webkul\Lead\Models\Stage::find($newStageId)
            : null;

        Log::info("Lead {$lead->id} stage changed", [
            'from' => $originalStage?->code,
            'to' => $newStage?->code,
        ]);

        $this->notificationService->sendOnStatusChange(
            $lead,
            $originalStage?->code ?? '',
            $newStage?->code ?? ''
        );

        $this->processAutomations($lead, $originalStage?->code, $newStage?->code);

        $this->webhookService->triggerPabblyWebhook('lead.stage_changed', [
            'lead_id' => $lead->id,
            'from_stage' => $originalStage?->name,
            'from_stage_code' => $originalStage?->code,
            'to_stage' => $newStage?->name,
            'to_stage_code' => $newStage?->code,
            'pipeline' => $lead->pipeline?->name,
        ]);

        if ($newStage && $newStage->code === 'won') {
            $this->handleLeadWon($lead);
        } elseif ($newStage && $newStage->code === 'lost') {
            $this->handleLeadLost($lead);
        }
    }

    protected function processAutomations(Lead $lead, ?string $fromStageCode, ?string $toStageCode): void
    {
        $automations = StatusAutomation::where('is_active', true)
            ->where('entity_type', 'lead')
            ->where(function ($query) use ($fromStageCode, $toStageCode) {
                $query->where('to_status', $toStageCode);
                if ($fromStageCode) {
                    $query->where(function ($q) use ($fromStageCode) {
                        $q->whereNull('from_status')
                            ->orWhere('from_status', $fromStageCode);
                    });
                }
            })
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($automations as $automation) {
            $this->executeAutomationActions($lead, $automation);
        }
    }

    protected function executeAutomationActions(Lead $lead, StatusAutomation $automation): void
    {
        $actions = $automation->actions ?? [];

        foreach ($actions as $action) {
            try {
                $this->executeAction($lead, $action);
            } catch (\Exception $e) {
                Log::error("Automation action failed: {$e->getMessage()}", [
                    'automation_id' => $automation->id,
                    'lead_id' => $lead->id,
                    'action' => $action,
                ]);
            }
        }
    }

    protected function executeAction(Lead $lead, array $action): void
    {
        $actionType = $action['type'] ?? '';

        switch ($actionType) {
            case 'send_email':
                $this->notificationService->sendToLead($lead, $action['template_code'] ?? '');
                break;

            case 'send_whatsapp':
                $this->notificationService->sendToLead($lead, $action['template_code'] ?? '');
                break;

            case 'assign_user':
                if (isset($action['user_id'])) {
                    $lead->user_id = $action['user_id'];
                    $lead->save();
                }
                break;

            case 'round_robin_assign':
                if (!$lead->user_id) {
                    $this->assignmentService->assignLead($lead, $action['specialization'] ?? null);
                }
                break;

            case 'trigger_webhook':
                $this->webhookService->triggerCustomWebhook(
                    $action['url'] ?? '',
                    $action['method'] ?? 'POST',
                    $this->buildWebhookPayload($lead, $action)
                );
                break;

            case 'add_tag':
                if (isset($action['tag_id'])) {
                    $lead->tags()->syncWithoutDetaching([$action['tag_id']]);
                }
                break;

            case 'update_field':
                if (isset($action['field']) && isset($action['value'])) {
                    $lead->{$action['field']} = $action['value'];
                    $lead->save();
                }
                break;
        }
    }

    protected function buildWebhookPayload(Lead $lead, array $action): array
    {
        $basePayload = [
            'lead_id' => $lead->id,
            'title' => $lead->title,
            'stage' => $lead->stage?->code,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($lead->person) {
            $basePayload['person'] = [
                'name' => $lead->person->name,
                'email' => $lead->person->emails[0]['value'] ?? null,
                'phone' => $lead->person->contact_numbers[0]['value'] ?? null,
            ];
        }

        return array_merge($basePayload, $action['additional_data'] ?? []);
    }

    protected function handleLeadWon(Lead $lead): void
    {
        $extension = LeadExtension::where('lead_id', $lead->id)->first();

        if ($extension && $extension->cohort_id) {
            $cohort = $extension->cohort;
            if ($cohort) {
                $cohort->incrementEnrollment();
            }
        }

        $this->webhookService->triggerPabblyWebhook('lead.won', [
            'lead_id' => $lead->id,
            'lead_value' => $lead->lead_value,
            'closed_at' => $lead->closed_at?->toIso8601String(),
        ]);
    }

    protected function handleLeadLost(Lead $lead): void
    {
        if ($lead->user_id) {
            $this->assignmentService->unassignLead($lead);
        }

        $this->webhookService->triggerPabblyWebhook('lead.lost', [
            'lead_id' => $lead->id,
            'lost_reason' => $lead->lost_reason,
            'closed_at' => $lead->closed_at?->toIso8601String(),
        ]);
    }

    protected function moveToTrash(Lead $lead): void
    {
        $leadData = $lead->toArray();
        $personData = $lead->person ? $lead->person->toArray() : [];
        $activitiesData = $lead->activities ? $lead->activities->toArray() : [];

        TrashLead::create([
            'original_lead_id' => $lead->id,
            'lead_data' => $leadData,
            'person_data' => $personData,
            'activities_data' => $activitiesData,
            'deleted_at' => now(),
            'permanent_delete_at' => now()->addDays(config('educrm.trash.retention_days', 60)),
        ]);
    }
}
