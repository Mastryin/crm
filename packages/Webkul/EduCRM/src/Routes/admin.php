<?php

use Illuminate\Support\Facades\Route;
use Webkul\EduCRM\Http\Controllers\Admin\ProgramController;
use Webkul\EduCRM\Http\Controllers\Admin\CohortController;
use Webkul\EduCRM\Http\Controllers\Admin\PaymentController;
use Webkul\EduCRM\Http\Controllers\Admin\QualificationController;
use Webkul\EduCRM\Http\Controllers\Admin\AutomationController;
use Webkul\EduCRM\Http\Controllers\Admin\AssignmentController;

Route::group([
    'prefix' => 'admin/educrm',
    'middleware' => ['web', 'admin', 'admin_locale'],
], function () {
    Route::resource('programs', ProgramController::class);
    Route::get('programs/{program}/cohorts', [ProgramController::class, 'cohorts']);

    Route::resource('cohorts', CohortController::class);
    Route::get('cohorts/available', [CohortController::class, 'getAvailable']);

    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::get('/{id}', [PaymentController::class, 'show']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::put('/{id}', [PaymentController::class, 'update']);
        Route::post('/{id}/record-payment', [PaymentController::class, 'recordPayment']);
        Route::get('/overdue', [PaymentController::class, 'overdue']);
        Route::get('/upcoming', [PaymentController::class, 'upcoming']);
    });

    Route::prefix('qualification')->group(function () {
        Route::get('/rules', [QualificationController::class, 'rules']);
        Route::post('/rules', [QualificationController::class, 'storeRule']);
        Route::put('/rules/{id}', [QualificationController::class, 'updateRule']);
        Route::delete('/rules/{id}', [QualificationController::class, 'deleteRule']);

        Route::get('/negative-keywords', [QualificationController::class, 'negativeKeywords']);
        Route::post('/negative-keywords', [QualificationController::class, 'storeKeyword']);
        Route::delete('/negative-keywords/{id}', [QualificationController::class, 'deleteKeyword']);

        Route::post('/qualify-lead/{leadId}', [QualificationController::class, 'qualifyLead']);
        Route::post('/manual-override/{leadId}', [QualificationController::class, 'manualOverride']);
    });

    Route::prefix('automations')->group(function () {
        Route::get('/', [AutomationController::class, 'index']);
        Route::post('/', [AutomationController::class, 'store']);
        Route::put('/{id}', [AutomationController::class, 'update']);
        Route::delete('/{id}', [AutomationController::class, 'destroy']);
        Route::get('/action-types', [AutomationController::class, 'actionTypes']);
    });

    Route::prefix('assignments')->group(function () {
        Route::get('/stats', [AssignmentController::class, 'stats']);
        Route::get('/available-users', [AssignmentController::class, 'availableUsers']);
        Route::post('/assign-lead/{leadId}', [AssignmentController::class, 'assignLead']);
        Route::post('/reassign-lead/{leadId}', [AssignmentController::class, 'reassignLead']);
        Route::put('/user/{userId}/settings', [AssignmentController::class, 'updateUserSettings']);
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/templates', [AutomationController::class, 'notificationTemplates']);
        Route::post('/templates', [AutomationController::class, 'storeTemplate']);
        Route::put('/templates/{id}', [AutomationController::class, 'updateTemplate']);
        Route::delete('/templates/{id}', [AutomationController::class, 'deleteTemplate']);
        Route::get('/logs', [AutomationController::class, 'notificationLogs']);
    });
});
