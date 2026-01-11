<?php

namespace Webkul\EduCRM\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Webkul\EduCRM\Services\WebhookIntegrationService;

class WebhookController extends Controller
{
    protected WebhookIntegrationService $webhookService;

    public function __construct(WebhookIntegrationService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    public function handleTrafft(Request $request): JsonResponse
    {
        Log::info('Trafft webhook received', $request->all());

        $result = $this->webhookService->handleIncomingWebhook('trafft', $request->all());

        return response()->json($result);
    }

    public function handleAisensy(Request $request): JsonResponse
    {
        Log::info('Aisensy webhook received', $request->all());

        $result = $this->webhookService->handleIncomingWebhook('aisensy', $request->all());

        return response()->json($result);
    }

    public function handleGeneric(Request $request, string $source): JsonResponse
    {
        Log::info("Generic webhook received from {$source}", $request->all());

        $result = $this->webhookService->handleIncomingWebhook($source, $request->all());

        return response()->json($result);
    }
}
