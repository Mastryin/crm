<?php

namespace Webkul\EduCRM\Services;

use Webkul\EduCRM\Models\LeadExtension;
use Webkul\EduCRM\Models\LeadQualification;
use Webkul\EduCRM\Models\NegativeKeyword;
use Webkul\EduCRM\Models\QualificationRule;
use Webkul\Lead\Models\Lead;

class LeadQualificationService
{
    protected int $defaultThreshold;

    public function __construct()
    {
        $this->defaultThreshold = config('educrm.qualification.default_threshold', 70);
    }

    public function qualifyLead(Lead $lead, array $formData = []): LeadQualification
    {
        $qualification = LeadQualification::firstOrNew(['lead_id' => $lead->id]);
        $qualification->lead_id = $lead->id;
        $qualification->qualification_score = 0;
        $qualification->disqualification_reasons = [];
        $qualification->matched_rules = [];

        $leadExtension = LeadExtension::where('lead_id', $lead->id)->first();
        if ($leadExtension) {
            $qualification->program_id = $leadExtension->program_id;
            $qualification->cohort_id = $leadExtension->cohort_id;
        }

        $data = $this->prepareDataForQualification($lead, $leadExtension, $formData);

        $negativeKeywordResult = $this->checkNegativeKeywords($data);
        if ($negativeKeywordResult['has_negative']) {
            $qualification->is_qualified = false;
            $qualification->disqualification_reasons = $negativeKeywordResult['reasons'];
            $qualification->save();

            if ($leadExtension) {
                $leadExtension->qualification_status = 'disqualified';
                $leadExtension->save();
            }

            return $qualification;
        }

        $ruleResults = $this->evaluateRules($data, $leadExtension?->program_id);
        $totalScore = 0;
        $isDisqualified = false;
        $disqualificationReasons = [];
        $matchedRules = [];

        foreach ($ruleResults as $result) {
            if ($result['matched']) {
                $matchedRules[] = $result;

                switch ($result['action']) {
                    case 'disqualify':
                        $isDisqualified = true;
                        $disqualificationReasons[] = $result['rule_name'];
                        break;

                    case 'qualify':
                        $totalScore += 100;
                        break;

                    case 'score_add':
                        $totalScore += $result['score_value'];
                        break;

                    case 'score_subtract':
                        $totalScore -= $result['score_value'];
                        break;

                    case 'flag':
                        break;
                }
            }
        }

        $qualification->qualification_score = max(0, $totalScore);
        $qualification->matched_rules = $matchedRules;
        $qualification->disqualification_reasons = $disqualificationReasons;

        if ($isDisqualified) {
            $qualification->is_qualified = false;
        } elseif ($totalScore >= $this->defaultThreshold) {
            $qualification->is_qualified = true;
            $qualification->qualified_at = now();
        } else {
            $qualification->is_qualified = false;
            $qualification->disqualification_reasons = array_merge(
                $disqualificationReasons,
                ['Score below threshold: ' . $totalScore . '/' . $this->defaultThreshold]
            );
        }

        $qualification->save();

        if ($leadExtension) {
            $leadExtension->qualification_status = $qualification->is_qualified ? 'qualified' : 'disqualified';
            $leadExtension->qualification_score = $qualification->qualification_score;
            $leadExtension->save();
        }

        return $qualification;
    }

    protected function prepareDataForQualification(Lead $lead, ?LeadExtension $extension, array $formData): array
    {
        $data = array_merge([
            'title' => $lead->title,
            'description' => $lead->description,
            'lead_value' => $lead->lead_value,
            'source' => $lead->source?->name,
            'type' => $lead->type?->name,
        ], $formData);

        if ($lead->person) {
            $data['name'] = $lead->person->name;
            $data['email'] = $lead->person->emails[0]['value'] ?? null;
            $data['phone'] = $lead->person->contact_numbers[0]['value'] ?? null;
        }

        if ($extension) {
            $data = array_merge($data, [
                'experience_years' => $extension->experience_years,
                'job_role' => $extension->job_role,
                'company_name' => $extension->company_name,
                'education_level' => $extension->education_level,
                'source_channel' => $extension->source_channel,
            ]);

            if (is_array($extension->original_source_data)) {
                $data = array_merge($data, $extension->original_source_data);
            }
        }

        return $data;
    }

    protected function checkNegativeKeywords(array $data): array
    {
        $fieldsToCheck = ['experience', 'job_role', 'description', 'company_name'];
        $matchedKeywords = [];

        foreach ($fieldsToCheck as $field) {
            $value = $data[$field] ?? '';
            if (empty($value)) {
                continue;
            }

            $matches = NegativeKeyword::checkText((string) $value, $field);
            foreach ($matches as $match) {
                $matchedKeywords[] = "Negative keyword '{$match['keyword']}' found in {$field}";
            }
        }

        $experienceText = $data['experience'] ?? $data['work_experience'] ?? '';
        if (!empty($experienceText)) {
            $generalMatches = NegativeKeyword::checkText((string) $experienceText, 'experience');
            foreach ($generalMatches as $match) {
                $matchedKeywords[] = "Negative keyword '{$match['keyword']}' found in experience";
            }
        }

        return [
            'has_negative' => !empty($matchedKeywords),
            'reasons' => array_unique($matchedKeywords),
        ];
    }

    protected function evaluateRules(array $data, ?string $programId = null): array
    {
        $query = QualificationRule::active()->ordered();

        if ($programId) {
            $query->where(function ($q) use ($programId) {
                $q->where('program_id', $programId)
                    ->orWhereNull('program_id');
            });
        } else {
            $query->whereNull('program_id');
        }

        $rules = $query->get();
        $results = [];

        foreach ($rules as $rule) {
            $results[] = $rule->evaluate($data);
        }

        return $results;
    }

    public function bulkQualify(array $leadIds): array
    {
        $results = [];

        foreach ($leadIds as $leadId) {
            $lead = Lead::find($leadId);
            if ($lead) {
                $results[$leadId] = $this->qualifyLead($lead);
            }
        }

        return $results;
    }

    public function requalifyByProgram(string $programId): int
    {
        $extensions = LeadExtension::where('program_id', $programId)->get();
        $count = 0;

        foreach ($extensions as $extension) {
            $lead = Lead::find($extension->lead_id);
            if ($lead) {
                $this->qualifyLead($lead);
                $count++;
            }
        }

        return $count;
    }

    public function getQualificationStatus(int $leadId): ?array
    {
        $qualification = LeadQualification::where('lead_id', $leadId)->first();

        if (!$qualification) {
            return null;
        }

        return [
            'is_qualified' => $qualification->is_qualified,
            'score' => $qualification->qualification_score,
            'threshold' => $this->defaultThreshold,
            'reasons' => $qualification->disqualification_reasons,
            'matched_rules' => $qualification->matched_rules,
            'qualified_at' => $qualification->qualified_at,
            'reviewed_by' => $qualification->reviewed_by,
            'reviewed_at' => $qualification->reviewed_at,
        ];
    }

    public function manualOverride(int $leadId, bool $isQualified, int $reviewerId, string $reason = null): LeadQualification
    {
        $qualification = LeadQualification::firstOrNew(['lead_id' => $leadId]);
        $qualification->lead_id = $leadId;
        $qualification->is_qualified = $isQualified;
        $qualification->reviewed_by = $reviewerId;
        $qualification->reviewed_at = now();

        if ($isQualified) {
            $qualification->qualified_at = now();
        } elseif ($reason) {
            $qualification->addDisqualificationReason('Manual override: ' . $reason);
        }

        $qualification->save();

        $extension = LeadExtension::where('lead_id', $leadId)->first();
        if ($extension) {
            $extension->qualification_status = $isQualified ? 'qualified' : 'disqualified';
            $extension->save();
        }

        return $qualification;
    }
}
