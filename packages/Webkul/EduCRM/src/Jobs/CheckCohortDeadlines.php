<?php

namespace Webkul\EduCRM\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Webkul\EduCRM\Models\Cohort;
use Webkul\EduCRM\Models\LeadExtension;
use Webkul\EduCRM\Services\OmnichannelNotificationService;
use Webkul\EduCRM\Services\WebhookIntegrationService;

class CheckCohortDeadlines implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        OmnichannelNotificationService $notificationService,
        WebhookIntegrationService $webhookService
    ): void {
        $this->checkApplicationDeadlines($notificationService, $webhookService);
        $this->checkCohortStartDates();
        $this->updateCohortStatuses();
    }

    protected function checkApplicationDeadlines(
        OmnichannelNotificationService $notificationService,
        WebhookIntegrationService $webhookService
    ): void {
        $upcomingDeadlines = Cohort::where('status', 'upcoming')
            ->whereBetween('application_deadline', [now(), now()->addDays(7)])
            ->with('program')
            ->get();

        foreach ($upcomingDeadlines as $cohort) {
            $daysRemaining = now()->diffInDays($cohort->application_deadline);

            if (in_array($daysRemaining, [7, 3, 1])) {
                $this->notifyLeadsAboutDeadline($cohort, $daysRemaining, $notificationService);
            }

            $webhookService->triggerPabblyWebhook('cohort.deadline_approaching', [
                'cohort_id' => $cohort->id,
                'cohort_name' => $cohort->name,
                'program_name' => $cohort->program?->name,
                'deadline' => $cohort->application_deadline->toIso8601String(),
                'days_remaining' => $daysRemaining,
                'spots_remaining' => $cohort->capacity - $cohort->enrolled_count,
            ]);
        }

        $expiredCohorts = Cohort::where('status', 'upcoming')
            ->where('application_deadline', '<', now())
            ->get();

        foreach ($expiredCohorts as $cohort) {
            Log::info("Cohort {$cohort->name} application deadline has passed");

            $webhookService->triggerPabblyWebhook('cohort.deadline_passed', [
                'cohort_id' => $cohort->id,
                'cohort_name' => $cohort->name,
                'final_enrollment' => $cohort->enrolled_count,
            ]);
        }
    }

    protected function notifyLeadsAboutDeadline(
        Cohort $cohort,
        int $daysRemaining,
        OmnichannelNotificationService $notificationService
    ): void {
        $extensions = LeadExtension::where('cohort_id', $cohort->id)
            ->where('qualification_status', 'qualified')
            ->whereHas('lead', function ($query) {
                $query->whereHas('stage', function ($q) {
                    $q->whereNotIn('code', ['won', 'lost']);
                });
            })
            ->with('lead.person')
            ->get();

        foreach ($extensions as $extension) {
            if ($extension->lead && $extension->lead->person) {
                try {
                    $notificationService->sendToLead($extension->lead, 'cohort_deadline_reminder', [
                        'cohort_name' => $cohort->name,
                        'program_name' => $cohort->program?->name,
                        'deadline_date' => $cohort->application_deadline->format('M d, Y'),
                        'days_remaining' => $daysRemaining,
                        'spots_remaining' => $cohort->capacity - $cohort->enrolled_count,
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to send deadline reminder: {$e->getMessage()}");
                }
            }
        }
    }

    protected function checkCohortStartDates(): void
    {
        $startingSoon = Cohort::where('status', 'upcoming')
            ->whereBetween('start_date', [now(), now()->addDays(7)])
            ->get();

        foreach ($startingSoon as $cohort) {
            $daysUntilStart = now()->diffInDays($cohort->start_date);

            if ($daysUntilStart <= 1) {
                $cohort->status = 'active';
                $cohort->save();

                Log::info("Cohort {$cohort->name} is now active");
            }
        }
    }

    protected function updateCohortStatuses(): void
    {
        Cohort::where('status', 'active')
            ->where('end_date', '<', now())
            ->update(['status' => 'completed']);

        Cohort::where('status', 'upcoming')
            ->where('start_date', '<=', now())
            ->update(['status' => 'active']);
    }
}
