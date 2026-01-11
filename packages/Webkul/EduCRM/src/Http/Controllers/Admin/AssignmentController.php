<?php

namespace Webkul\EduCRM\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Webkul\EduCRM\Services\RoundRobinAssignmentService;
use Webkul\Lead\Models\Lead;

class AssignmentController extends Controller
{
    protected RoundRobinAssignmentService $assignmentService;

    public function __construct(RoundRobinAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    public function stats(): JsonResponse
    {
        $stats = $this->assignmentService->getAssignmentStats();

        $summary = [
            'total_users' => count($stats),
            'available_users' => collect($stats)->where('is_available', true)->count(),
            'total_current_load' => collect($stats)->sum('current_load'),
            'total_capacity' => collect($stats)->sum('max_load'),
            'average_utilization' => count($stats) > 0
                ? round(collect($stats)->avg('utilization'), 1)
                : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'summary' => $summary,
        ]);
    }

    public function availableUsers(Request $request): JsonResponse
    {
        $specialization = $request->input('specialization');
        $users = $this->assignmentService->getAvailableUsers($specialization);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function assignLead(Request $request, int $leadId): JsonResponse
    {
        $lead = Lead::findOrFail($leadId);

        if ($lead->user_id) {
            return response()->json([
                'success' => false,
                'error' => 'Lead is already assigned',
            ], 422);
        }

        $specialization = $request->input('specialization');
        $user = $this->assignmentService->assignLead($lead, $specialization);

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'No available users to assign',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead assigned successfully',
            'data' => [
                'lead_id' => $lead->id,
                'assigned_to' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    public function reassignLead(Request $request, int $leadId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $lead = Lead::findOrFail($leadId);

        $success = $this->assignmentService->reassignLead(
            $lead,
            $request->input('user_id'),
            $request->input('reason')
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to reassign lead',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead reassigned successfully',
        ]);
    }

    public function updateUserSettings(Request $request, int $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'max_load' => 'nullable|integer|min:1',
            'daily_limit' => 'nullable|integer|min:1',
            'is_available' => 'nullable|boolean',
            'specializations' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->has('max_load') || $request->has('daily_limit')) {
            $this->assignmentService->updateUserCapacity(
                $userId,
                $request->input('max_load', 50),
                $request->input('daily_limit')
            );
        }

        if ($request->has('is_available')) {
            $this->assignmentService->setUserAvailability(
                $userId,
                $request->input('is_available')
            );
        }

        if ($request->has('specializations')) {
            $this->assignmentService->updateUserSpecializations(
                $userId,
                $request->input('specializations')
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'User settings updated successfully',
        ]);
    }
}
