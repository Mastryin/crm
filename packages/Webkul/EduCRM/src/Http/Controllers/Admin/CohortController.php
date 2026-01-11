<?php

namespace Webkul\EduCRM\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Webkul\EduCRM\Models\Cohort;
use Webkul\EduCRM\Models\Program;

class CohortController extends Controller
{
    public function index(Request $request)
    {
        $cohorts = Cohort::with('program')
            ->when($request->input('program_id'), function ($query, $programId) {
                $query->where('program_id', $programId);
            })
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('start_date', 'desc')
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json($cohorts);
        }

        return view('educrm::admin.cohorts.index', compact('cohorts'));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'program_id' => 'required|exists:programs,id',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'application_deadline' => 'nullable|date|before:start_date',
            'capacity' => 'required|integer|min:1',
            'status' => 'in:upcoming,active,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $cohort = Cohort::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cohort created successfully',
            'data' => $cohort->load('program'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $cohort = Cohort::with(['program', 'leadExtensions.lead.person', 'studentPayments'])
            ->findOrFail($id);

        $stats = [
            'total_leads' => $cohort->leadExtensions()->count(),
            'qualified_leads' => $cohort->leadExtensions()->where('qualification_status', 'qualified')->count(),
            'enrolled' => $cohort->enrolled_count,
            'capacity' => $cohort->capacity,
            'fill_rate' => $cohort->capacity > 0
                ? round(($cohort->enrolled_count / $cohort->capacity) * 100, 1)
                : 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $cohort,
            'stats' => $stats,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $cohort = Cohort::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'start_date' => 'date',
            'end_date' => 'nullable|date|after:start_date',
            'application_deadline' => 'nullable|date',
            'capacity' => 'integer|min:1',
            'status' => 'in:upcoming,active,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $cohort->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cohort updated successfully',
            'data' => $cohort->load('program'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $cohort = Cohort::findOrFail($id);

        if ($cohort->enrolled_count > 0) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete cohort with enrolled students',
            ], 422);
        }

        $cohort->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cohort deleted successfully',
        ]);
    }

    public function getAvailable(Request $request): JsonResponse
    {
        $cohorts = Cohort::acceptingApplications()
            ->with('program')
            ->get()
            ->map(function ($cohort) {
                return [
                    'id' => $cohort->id,
                    'name' => $cohort->name,
                    'program' => $cohort->program?->name,
                    'start_date' => $cohort->start_date->format('M d, Y'),
                    'deadline' => $cohort->application_deadline?->format('M d, Y'),
                    'spots_remaining' => $cohort->capacity - $cohort->enrolled_count,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $cohorts,
        ]);
    }
}
