<?php

namespace Webkul\EduCRM\Services;

use Illuminate\Support\Facades\DB;
use Webkul\EduCRM\Models\LeadExtension;
use Webkul\EduCRM\Models\LeadMergeLog;
use Webkul\Lead\Models\Lead;
use Webkul\Contact\Models\Person;

class LeadDeduplicationService
{
    protected array $matchFields;
    protected string $mergePriority;

    public function __construct()
    {
        $this->matchFields = config('educrm.deduplication.match_fields', ['phone', 'email']);
        $this->mergePriority = config('educrm.deduplication.merge_priority', 'application_form');
    }

    public function findDuplicate(?string $phone, ?string $email): array
    {
        $normalizedPhone = LeadExtension::normalizePhone($phone);
        $normalizedEmail = LeadExtension::normalizeEmail($email);

        $existingExtension = null;

        if ($normalizedPhone && in_array('phone', $this->matchFields)) {
            $existingExtension = LeadExtension::where('phone_normalized', $normalizedPhone)
                ->where('is_duplicate', false)
                ->first();
        }

        if (!$existingExtension && $normalizedEmail && in_array('email', $this->matchFields)) {
            $existingExtension = LeadExtension::where('email_normalized', $normalizedEmail)
                ->where('is_duplicate', false)
                ->first();
        }

        if ($existingExtension) {
            $lead = Lead::find($existingExtension->lead_id);

            return [
                'is_duplicate' => true,
                'existing_lead' => $lead,
                'existing_extension' => $existingExtension,
                'matched_on' => $normalizedPhone ? 'phone' : 'email',
            ];
        }

        return [
            'is_duplicate' => false,
            'existing_lead' => null,
            'existing_extension' => null,
            'matched_on' => null,
        ];
    }

    public function mergeLead(Lead $existingLead, array $newData, string $sourceChannel): array
    {
        return DB::transaction(function () use ($existingLead, $newData, $sourceChannel) {
            $existingExtension = LeadExtension::where('lead_id', $existingLead->id)->first();

            $shouldOverwrite = $this->shouldOverwriteData($existingExtension, $sourceChannel);

            $fieldsUpdated = [];
            $mergeData = [];

            if ($shouldOverwrite) {
                $person = $existingLead->person;
                if ($person && !empty($newData['name']) && $newData['name'] !== $person->name) {
                    $mergeData['name'] = ['old' => $person->name, 'new' => $newData['name']];
                    $person->name = $newData['name'];
                    $person->save();
                    $fieldsUpdated[] = 'name';
                }

                if (!empty($newData['email']) && $person) {
                    $currentEmail = $person->emails[0]['value'] ?? null;
                    if ($currentEmail !== $newData['email']) {
                        $mergeData['email'] = ['old' => $currentEmail, 'new' => $newData['email']];
                        $person->emails = [['value' => $newData['email'], 'label' => 'work']];
                        $person->save();
                        $fieldsUpdated[] = 'email';
                    }
                }

                if ($existingExtension) {
                    $extensionFields = ['experience_years', 'job_role', 'company_name', 'education_level', 'linkedin_url'];

                    foreach ($extensionFields as $field) {
                        $dataKey = str_replace('_', '', $field);
                        $newValue = $newData[$dataKey] ?? $newData[$field] ?? null;

                        if ($newValue && $newValue !== $existingExtension->$field) {
                            $mergeData[$field] = ['old' => $existingExtension->$field, 'new' => $newValue];
                            $existingExtension->$field = $newValue;
                            $fieldsUpdated[] = $field;
                        }
                    }

                    $existingHistory = $existingExtension->merge_history ?? [];
                    $existingHistory[] = [
                        'merged_at' => now()->toIso8601String(),
                        'source_channel' => $sourceChannel,
                        'fields_updated' => $fieldsUpdated,
                        'original_data' => $newData,
                    ];
                    $existingExtension->merge_history = $existingHistory;
                    $existingExtension->save();
                }
            }

            LeadMergeLog::create([
                'primary_lead_id' => $existingLead->id,
                'merged_lead_id' => $existingLead->id,
                'merge_reason' => 'duplicate_' . ($this->matchFields[0] ?? 'phone'),
                'merge_data' => $mergeData,
                'fields_updated' => $fieldsUpdated,
            ]);

            return [
                'overwritten' => $shouldOverwrite,
                'fields_updated' => $fieldsUpdated,
                'merge_data' => $mergeData,
            ];
        });
    }

    protected function shouldOverwriteData(?LeadExtension $existingExtension, string $newSourceChannel): bool
    {
        if (!$existingExtension) {
            return true;
        }

        $priorityOrder = [
            'webform' => 10,
            'deftform' => 9,
            'manual' => 8,
            'pabbly' => 5,
            'meta_ads' => 3,
            'csv_import' => 1,
        ];

        $existingPriority = $priorityOrder[$existingExtension->source_channel] ?? 0;
        $newPriority = $priorityOrder[$newSourceChannel] ?? 0;

        return $newPriority >= $existingPriority;
    }

    public function findPotentialDuplicates(int $leadId): array
    {
        $extension = LeadExtension::where('lead_id', $leadId)->first();

        if (!$extension) {
            return [];
        }

        $query = LeadExtension::where('lead_id', '!=', $leadId)
            ->where('is_duplicate', false);

        if ($extension->phone_normalized) {
            $query->orWhere('phone_normalized', $extension->phone_normalized);
        }

        if ($extension->email_normalized) {
            $query->orWhere('email_normalized', $extension->email_normalized);
        }

        $potentialDuplicates = $query->with('lead')->get();

        return $potentialDuplicates->map(function ($ext) use ($extension) {
            $matchedFields = [];

            if ($extension->phone_normalized && $ext->phone_normalized === $extension->phone_normalized) {
                $matchedFields[] = 'phone';
            }

            if ($extension->email_normalized && $ext->email_normalized === $extension->email_normalized) {
                $matchedFields[] = 'email';
            }

            return [
                'lead_id' => $ext->lead_id,
                'lead' => $ext->lead,
                'matched_fields' => $matchedFields,
                'confidence' => count($matchedFields) * 50,
            ];
        })->toArray();
    }

    public function markAsDuplicate(int $duplicateLeadId, int $primaryLeadId, int $mergedBy = null): bool
    {
        return DB::transaction(function () use ($duplicateLeadId, $primaryLeadId, $mergedBy) {
            $duplicateExtension = LeadExtension::where('lead_id', $duplicateLeadId)->first();

            if ($duplicateExtension) {
                $duplicateExtension->is_duplicate = true;
                $duplicateExtension->duplicate_of_lead_id = $primaryLeadId;
                $duplicateExtension->save();
            }

            LeadMergeLog::create([
                'primary_lead_id' => $primaryLeadId,
                'merged_lead_id' => $duplicateLeadId,
                'merge_reason' => 'manual_duplicate',
                'merged_by' => $mergedBy,
            ]);

            return true;
        });
    }

    public function runDeduplicationScan(int $limit = 1000): array
    {
        $extensions = LeadExtension::where('is_duplicate', false)
            ->limit($limit)
            ->get();

        $duplicatesFound = [];

        foreach ($extensions as $extension) {
            $duplicates = LeadExtension::where('id', '!=', $extension->id)
                ->where('is_duplicate', false)
                ->where(function ($query) use ($extension) {
                    if ($extension->phone_normalized) {
                        $query->orWhere('phone_normalized', $extension->phone_normalized);
                    }
                    if ($extension->email_normalized) {
                        $query->orWhere('email_normalized', $extension->email_normalized);
                    }
                })
                ->get();

            if ($duplicates->count() > 0) {
                $duplicatesFound[$extension->lead_id] = $duplicates->pluck('lead_id')->toArray();
            }
        }

        return $duplicatesFound;
    }
}
