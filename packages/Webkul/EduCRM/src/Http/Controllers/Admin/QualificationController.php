<?php

namespace Webkul\EduCRM\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Webkul\EduCRM\Models\QualificationRule;
use Webkul\EduCRM\Models\NegativeKeyword;
use Webkul\EduCRM\Services\LeadQualificationService;
use Webkul\Lead\Models\Lead;

class QualificationController extends Controller
{
    protected LeadQualificationService $qualificationService;

    public function __construct(LeadQualificationService $qualificationService)
    {
        $this->qualificationService = $qualificationService;
    }

    public function rules(Request $request): JsonResponse
    {
        $rules = QualificationRule::when($request->input('program_id'), function ($query, $programId) {
            $query->where('program_id', $programId);
        })
            ->when($request->input('entity_type'), function ($query, $entityType) {
                $query->where('entity_type', $entityType);
            })
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $rules,
            'operators' => QualificationRule::OPERATORS,
            'actions' => QualificationRule::ACTIONS,
        ]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entity_type' => 'required|in:lead,person,form_response',
            'field_name' => 'required|string|max:100',
            'operator' => 'required|in:' . implode(',', array_keys(QualificationRule::OPERATORS)),
            'value' => 'nullable|string',
            'action' => 'required|in:' . implode(',', array_keys(QualificationRule::ACTIONS)),
            'score_value' => 'nullable|integer',
            'priority' => 'integer',
            'program_id' => 'nullable|exists:programs,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $rule = QualificationRule::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Qualification rule created successfully',
            'data' => $rule,
        ], 201);
    }

    public function updateRule(Request $request, string $id): JsonResponse
    {
        $rule = QualificationRule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'field_name' => 'string|max:100',
            'operator' => 'in:' . implode(',', array_keys(QualificationRule::OPERATORS)),
            'value' => 'nullable|string',
            'action' => 'in:' . implode(',', array_keys(QualificationRule::ACTIONS)),
            'score_value' => 'nullable|integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $rule->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Qualification rule updated successfully',
            'data' => $rule,
        ]);
    }

    public function deleteRule(string $id): JsonResponse
    {
        $rule = QualificationRule::findOrFail($id);
        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Qualification rule deleted successfully',
        ]);
    }

    public function negativeKeywords(Request $request): JsonResponse
    {
        $keywords = NegativeKeyword::when($request->input('field_name'), function ($query, $fieldName) {
            $query->where('field_name', $fieldName);
        })
            ->orderBy('keyword')
            ->paginate(100);

        return response()->json([
            'success' => true,
            'data' => $keywords,
            'match_types' => NegativeKeyword::MATCH_TYPES,
        ]);
    }

    public function storeKeyword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'required|string|max:255',
            'field_name' => 'required|string|max:50',
            'match_type' => 'required|in:' . implode(',', array_keys(NegativeKeyword::MATCH_TYPES)),
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $keyword = NegativeKeyword::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Negative keyword added successfully',
            'data' => $keyword,
        ], 201);
    }

    public function deleteKeyword(string $id): JsonResponse
    {
        $keyword = NegativeKeyword::findOrFail($id);
        $keyword->delete();

        return response()->json([
            'success' => true,
            'message' => 'Negative keyword deleted successfully',
        ]);
    }

    public function qualifyLead(int $leadId): JsonResponse
    {
        $lead = Lead::findOrFail($leadId);

        $qualification = $this->qualificationService->qualifyLead($lead);

        return response()->json([
            'success' => true,
            'data' => [
                'is_qualified' => $qualification->is_qualified,
                'score' => $qualification->qualification_score,
                'reasons' => $qualification->disqualification_reasons,
                'matched_rules' => $qualification->matched_rules,
            ],
        ]);
    }

    public function manualOverride(Request $request, int $leadId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_qualified' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $qualification = $this->qualificationService->manualOverride(
            $leadId,
            $request->input('is_qualified'),
            auth()->id(),
            $request->input('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Qualification status updated successfully',
            'data' => $qualification,
        ]);
    }
}
