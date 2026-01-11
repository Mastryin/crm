<?php

namespace Webkul\EduCRM\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Webkul\EduCRM\Models\Program;
use Webkul\EduCRM\Models\Cohort;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        $programs = Program::with(['cohorts' => function ($query) {
            $query->orderBy('start_date', 'desc');
        }])
            ->when($request->input('status'), function ($query, $status) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->orderBy('name')
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json($programs);
        }

        return view('educrm::admin.programs.index', compact('programs'));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_weeks' => 'nullable|integer|min:1',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $program = Program::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Program created successfully',
            'data' => $program,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $program = Program::with(['cohorts', 'paymentPlans', 'qualificationRules'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $program,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $program = Program::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'duration_weeks' => 'nullable|integer|min:1',
            'price' => 'numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $program->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Program updated successfully',
            'data' => $program,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $program = Program::findOrFail($id);

        $activeCohorts = $program->cohorts()->whereIn('status', ['upcoming', 'active'])->count();

        if ($activeCohorts > 0) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete program with active cohorts',
            ], 422);
        }

        $program->delete();

        return response()->json([
            'success' => true,
            'message' => 'Program deleted successfully',
        ]);
    }
}
