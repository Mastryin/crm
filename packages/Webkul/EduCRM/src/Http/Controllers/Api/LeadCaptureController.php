<?php

namespace Webkul\EduCRM\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Webkul\EduCRM\Services\LeadCaptureService;

class LeadCaptureController extends Controller
{
    protected LeadCaptureService $leadCaptureService;

    public function __construct(LeadCaptureService $leadCaptureService)
    {
        $this->leadCaptureService = $leadCaptureService;
    }

    public function captureMetaAds(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required_without:name|string|max:255',
            'name' => 'required_without:full_name|string|max:255',
            'email' => 'required|email',
            'phone_number' => 'required_without:phone|string',
            'phone' => 'required_without:phone_number|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->leadCaptureService->captureFromMetaAds($request->all());

            return response()->json([
                'success' => true,
                'data' => $result,
            ], $result['is_new'] ? 201 : 200);
        } catch (\Exception $e) {
            Log::error("Meta Ads capture failed: {$e->getMessage()}", $request->all());

            return response()->json([
                'success' => false,
                'error' => 'Failed to capture lead',
            ], 500);
        }
    }

    public function captureDeftform(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->leadCaptureService->captureFromDeftform($request->all());

            return response()->json([
                'success' => true,
                'data' => $result,
            ], $result['is_new'] ? 201 : 200);
        } catch (\Exception $e) {
            Log::error("Deftform capture failed: {$e->getMessage()}", $request->all());

            return response()->json([
                'success' => false,
                'error' => 'Failed to capture lead',
            ], 500);
        }
    }

    public function capturePabbly(Request $request): JsonResponse
    {
        try {
            $result = $this->leadCaptureService->captureFromPabbly($request->all());

            return response()->json([
                'success' => true,
                'data' => $result,
            ], $result['is_new'] ? 201 : 200);
        } catch (\Exception $e) {
            Log::error("Pabbly capture failed: {$e->getMessage()}", $request->all());

            return response()->json([
                'success' => false,
                'error' => 'Failed to capture lead',
            ], 500);
        }
    }

    public function captureGeneric(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string',
            'source' => 'string|in:meta_ads,deftform,pabbly,webform,manual,api',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $source = $request->input('source', 'api');
            $data = $request->all();

            $result = match ($source) {
                'meta_ads' => $this->leadCaptureService->captureFromMetaAds($data),
                'deftform' => $this->leadCaptureService->captureFromDeftform($data),
                'pabbly' => $this->leadCaptureService->captureFromPabbly($data),
                default => $this->leadCaptureService->captureFromCSV($data),
            };

            return response()->json([
                'success' => true,
                'data' => $result,
            ], $result['is_new'] ? 201 : 200);
        } catch (\Exception $e) {
            Log::error("Generic capture failed: {$e->getMessage()}", $request->all());

            return response()->json([
                'success' => false,
                'error' => 'Failed to capture lead',
            ], 500);
        }
    }

    public function bulkImport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'leads' => 'required|array|min:1|max:1000',
            'leads.*.name' => 'required|string|max:255',
            'leads.*.email' => 'required|email',
            'leads.*.phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $results = [
            'total' => count($request->input('leads')),
            'created' => 0,
            'merged' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($request->input('leads') as $index => $leadData) {
            try {
                $result = $this->leadCaptureService->captureFromCSV($leadData);

                if ($result['is_new']) {
                    $results['created']++;
                } else {
                    $results['merged']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }
}
