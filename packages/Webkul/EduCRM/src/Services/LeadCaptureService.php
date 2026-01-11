<?php

namespace Webkul\EduCRM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\EduCRM\Models\LeadExtension;
use Webkul\EduCRM\Models\LeadSourceConfig;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Models\Lead;

class LeadCaptureService
{
    protected LeadRepository $leadRepository;
    protected PersonRepository $personRepository;
    protected LeadDeduplicationService $deduplicationService;
    protected LeadQualificationService $qualificationService;
    protected RoundRobinAssignmentService $assignmentService;

    public function __construct(
        LeadRepository $leadRepository,
        PersonRepository $personRepository,
        LeadDeduplicationService $deduplicationService,
        LeadQualificationService $qualificationService,
        RoundRobinAssignmentService $assignmentService
    ) {
        $this->leadRepository = $leadRepository;
        $this->personRepository = $personRepository;
        $this->deduplicationService = $deduplicationService;
        $this->qualificationService = $qualificationService;
        $this->assignmentService = $assignmentService;
    }

    public function captureFromMetaAds(array $data): array
    {
        return $this->captureLead($data, 'meta_ads', [
            'name' => $data['full_name'] ?? $data['name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone_number'] ?? $data['phone'] ?? '',
            'campaign' => $data['campaign_name'] ?? $data['campaign'] ?? '',
            'adset' => $data['adset_name'] ?? '',
            'ad' => $data['ad_name'] ?? '',
        ]);
    }

    public function captureFromDeftform(array $data): array
    {
        return $this->captureLead($data, 'deftform', [
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'experience' => $data['work_experience'] ?? $data['experience'] ?? '',
            'job_role' => $data['current_role'] ?? $data['job_title'] ?? '',
            'company' => $data['company'] ?? $data['organization'] ?? '',
        ]);
    }

    public function captureFromPabbly(array $data): array
    {
        $sourceType = $data['source_type'] ?? 'pabbly';
        $mappedData = $this->mapPabblyFields($data);

        return $this->captureLead($data, $sourceType, $mappedData);
    }

    public function captureFromCSV(array $data): array
    {
        return $this->captureLead($data, 'csv_import', [
            'name' => $data['name'] ?? $data['Name'] ?? '',
            'email' => $data['email'] ?? $data['Email'] ?? '',
            'phone' => $data['phone'] ?? $data['Phone'] ?? $data['Mobile'] ?? '',
            'experience' => $data['experience'] ?? $data['Experience'] ?? '',
            'company' => $data['company'] ?? $data['Company'] ?? '',
        ]);
    }

    public function captureFromWebForm(array $data, int $webFormId): array
    {
        return $this->captureLead($data, 'webform', array_merge($data, [
            'source_form_id' => $webFormId,
        ]));
    }

    public function captureManual(array $data, int $userId): array
    {
        $data['created_by'] = $userId;
        return $this->captureLead($data, 'manual', $data);
    }

    protected function captureLead(array $rawData, string $sourceChannel, array $mappedData): array
    {
        return DB::transaction(function () use ($rawData, $sourceChannel, $mappedData) {
            $phone = LeadExtension::normalizePhone($mappedData['phone'] ?? '');
            $email = LeadExtension::normalizeEmail($mappedData['email'] ?? '');

            $deduplicationResult = $this->deduplicationService->findDuplicate($phone, $email);

            if ($deduplicationResult['is_duplicate']) {
                $lead = $deduplicationResult['existing_lead'];
                $mergeResult = $this->deduplicationService->mergeLead($lead, $mappedData, $sourceChannel);

                return [
                    'success' => true,
                    'is_new' => false,
                    'is_merged' => true,
                    'lead_id' => $lead->id,
                    'merge_details' => $mergeResult,
                ];
            }

            $person = $this->createOrUpdatePerson($mappedData);

            $lead = $this->leadRepository->create([
                'title' => $mappedData['name'] ?? 'New Lead',
                'description' => $mappedData['description'] ?? '',
                'lead_value' => $mappedData['lead_value'] ?? 0,
                'person_id' => $person->id,
                'lead_source_id' => $this->getSourceId($sourceChannel),
                'lead_pipeline_id' => $mappedData['pipeline_id'] ?? $this->getDefaultPipelineId(),
                'lead_pipeline_stage_id' => $mappedData['stage_id'] ?? $this->getDefaultStageId(),
                'status' => 1,
            ]);

            $extension = $this->createLeadExtension($lead, $sourceChannel, $rawData, $mappedData, $phone, $email);

            $qualification = null;
            $assignedUser = null;

            if (config('educrm.lead_sources.' . $sourceChannel . '.auto_qualify', true)) {
                $qualification = $this->qualificationService->qualifyLead($lead, $mappedData);

                if ($qualification->is_qualified && config('educrm.qualification.auto_assign_qualified', true)) {
                    $assignedUser = $this->assignmentService->assignLead($lead);
                }
            }

            return [
                'success' => true,
                'is_new' => true,
                'is_merged' => false,
                'lead_id' => $lead->id,
                'person_id' => $person->id,
                'extension_id' => $extension->id,
                'is_qualified' => $qualification?->is_qualified ?? null,
                'qualification_score' => $qualification?->qualification_score ?? null,
                'assigned_to' => $assignedUser?->id,
            ];
        });
    }

    protected function createOrUpdatePerson(array $data): \Webkul\Contact\Models\Person
    {
        $emails = [];
        if (!empty($data['email'])) {
            $emails[] = ['value' => $data['email'], 'label' => 'work'];
        }

        $phones = [];
        if (!empty($data['phone'])) {
            $phones[] = ['value' => $data['phone'], 'label' => 'work'];
        }

        return $this->personRepository->create([
            'name' => $data['name'] ?? 'Unknown',
            'emails' => $emails,
            'contact_numbers' => $phones,
            'organization_id' => $data['organization_id'] ?? null,
        ]);
    }

    protected function createLeadExtension(Lead $lead, string $sourceChannel, array $rawData, array $mappedData, ?string $phone, ?string $email): LeadExtension
    {
        return LeadExtension::create([
            'lead_id' => $lead->id,
            'source_channel' => $sourceChannel,
            'source_campaign' => $mappedData['campaign'] ?? null,
            'source_medium' => $mappedData['medium'] ?? null,
            'source_form_id' => $mappedData['source_form_id'] ?? null,
            'original_source_data' => $rawData,
            'phone_normalized' => $phone,
            'email_normalized' => $email,
            'experience_years' => $this->parseExperienceYears($mappedData['experience'] ?? ''),
            'job_role' => $mappedData['job_role'] ?? null,
            'company_name' => $mappedData['company'] ?? null,
            'education_level' => $mappedData['education'] ?? null,
            'linkedin_url' => $mappedData['linkedin'] ?? null,
            'utm_source' => $mappedData['utm_source'] ?? null,
            'utm_medium' => $mappedData['utm_medium'] ?? null,
            'utm_campaign' => $mappedData['utm_campaign'] ?? null,
            'utm_term' => $mappedData['utm_term'] ?? null,
            'utm_content' => $mappedData['utm_content'] ?? null,
            'referrer_url' => $mappedData['referrer'] ?? null,
            'landing_page' => $mappedData['landing_page'] ?? null,
            'form_submitted_at' => now(),
            'qualification_status' => 'pending',
        ]);
    }

    protected function mapPabblyFields(array $data): array
    {
        $fieldMapping = $data['_field_mapping'] ?? [];

        $mapped = [];
        foreach ($fieldMapping as $targetField => $sourceField) {
            if (isset($data[$sourceField])) {
                $mapped[$targetField] = $data[$sourceField];
            }
        }

        return array_merge([
            'name' => $data['name'] ?? $data['full_name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? $data['mobile'] ?? '',
        ], $mapped);
    }

    protected function parseExperienceYears(?string $experience): ?float
    {
        if (empty($experience)) {
            return null;
        }

        if (is_numeric($experience)) {
            return (float) $experience;
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:years?|yrs?)/i', $experience, $matches)) {
            return (float) $matches[1];
        }

        $experienceMap = [
            'fresher' => 0,
            'no experience' => 0,
            '0-1' => 0.5,
            '1-2' => 1.5,
            '2-3' => 2.5,
            '3-5' => 4,
            '5-7' => 6,
            '7-10' => 8.5,
            '10+' => 12,
            '10-15' => 12.5,
            '15+' => 17,
        ];

        $normalizedExperience = strtolower(trim($experience));
        return $experienceMap[$normalizedExperience] ?? null;
    }

    protected function getSourceId(string $sourceChannel): ?int
    {
        $sourceMap = [
            'meta_ads' => 1,
            'deftform' => 2,
            'csv_import' => 3,
            'pabbly' => 4,
            'manual' => 5,
            'webform' => 6,
        ];

        return $sourceMap[$sourceChannel] ?? null;
    }

    protected function getDefaultPipelineId(): int
    {
        return \Webkul\Lead\Models\Pipeline::where('is_default', true)->first()?->id ?? 1;
    }

    protected function getDefaultStageId(): int
    {
        $pipeline = \Webkul\Lead\Models\Pipeline::where('is_default', true)->first();
        return $pipeline?->stages()->orderBy('sort_order')->first()?->id ?? 1;
    }
}
