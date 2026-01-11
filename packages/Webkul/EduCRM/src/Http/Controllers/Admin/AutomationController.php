<?php

namespace Webkul\EduCRM\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Webkul\EduCRM\Models\StatusAutomation;
use Webkul\EduCRM\Models\NotificationTemplate;
use Webkul\EduCRM\Models\NotificationLog;

class AutomationController extends Controller
{
    public function index(Request $request)
    {
        $automations = StatusAutomation::when($request->input('entity_type'), function ($query, $entityType) {
            $query->where('entity_type', $entityType);
        })
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->paginate(50);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $automations,
            ]);
        }

        return view('educrm::admin.automations.index', compact('automations'));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entity_type' => 'required|in:lead,person,payment',
            'from_status' => 'nullable|string|max:50',
            'to_status' => 'required|string|max:50',
            'pipeline_id' => 'nullable|integer',
            'from_stage_id' => 'nullable|integer',
            'to_stage_id' => 'nullable|integer',
            'conditions' => 'nullable|array',
            'actions' => 'required|array|min:1',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $automation = StatusAutomation::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Automation created successfully',
            'data' => $automation,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $automation = StatusAutomation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'from_status' => 'nullable|string|max:50',
            'to_status' => 'string|max:50',
            'conditions' => 'nullable|array',
            'actions' => 'array|min:1',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $automation->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Automation updated successfully',
            'data' => $automation,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $automation = StatusAutomation::findOrFail($id);
        $automation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Automation deleted successfully',
        ]);
    }

    public function actionTypes(): JsonResponse
    {
        $types = \Webkul\EduCRM\Models\AutomationActionType::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    public function notificationTemplates(Request $request): JsonResponse
    {
        $templates = NotificationTemplate::when($request->input('channel'), function ($query, $channel) {
            $query->where('channel', $channel);
        })
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:notification_templates,code',
            'channel' => 'required|in:email,whatsapp,sms',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'variables' => 'nullable|array',
            'trigger_event' => 'nullable|string|max:50',
            'trigger_status' => 'nullable|string|max:50',
            'provider_template_id' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $template = NotificationTemplate::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Notification template created successfully',
            'data' => $template,
        ], 201);
    }

    public function updateTemplate(Request $request, string $id): JsonResponse
    {
        $template = NotificationTemplate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'subject' => 'nullable|string|max:255',
            'content' => 'string',
            'variables' => 'nullable|array',
            'trigger_event' => 'nullable|string|max:50',
            'trigger_status' => 'nullable|string|max:50',
            'provider_template_id' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $template->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Notification template updated successfully',
            'data' => $template,
        ]);
    }

    public function deleteTemplate(string $id): JsonResponse
    {
        $template = NotificationTemplate::findOrFail($id);
        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification template deleted successfully',
        ]);
    }

    public function notificationLogs(Request $request): JsonResponse
    {
        $logs = NotificationLog::when($request->input('lead_id'), function ($query, $leadId) {
            $query->where('lead_id', $leadId);
        })
            ->when($request->input('channel'), function ($query, $channel) {
                $query->where('channel', $channel);
            })
            ->when($request->input('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}
