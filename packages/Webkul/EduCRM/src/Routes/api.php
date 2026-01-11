<?php

use Illuminate\Support\Facades\Route;
use Webkul\EduCRM\Http\Controllers\Api\LeadCaptureController;
use Webkul\EduCRM\Http\Controllers\Api\WebhookController;

Route::prefix('api/educrm')->group(function () {
    Route::prefix('capture')->group(function () {
        Route::post('/meta-ads', [LeadCaptureController::class, 'captureMetaAds']);
        Route::post('/deftform', [LeadCaptureController::class, 'captureDeftform']);
        Route::post('/pabbly', [LeadCaptureController::class, 'capturePabbly']);
        Route::post('/generic', [LeadCaptureController::class, 'captureGeneric']);
        Route::post('/bulk', [LeadCaptureController::class, 'bulkImport']);
    });

    Route::prefix('webhooks')->group(function () {
        Route::post('/trafft', [WebhookController::class, 'handleTrafft']);
        Route::post('/aisensy', [WebhookController::class, 'handleAisensy']);
        Route::post('/{source}', [WebhookController::class, 'handleGeneric']);
    });
});
