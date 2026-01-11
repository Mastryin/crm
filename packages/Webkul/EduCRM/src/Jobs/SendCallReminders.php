<?php

namespace Webkul\EduCRM\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Webkul\EduCRM\Models\ScheduledCall;
use Webkul\EduCRM\Services\OmnichannelNotificationService;

class SendCallReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $hoursBeforeCall;

    public function __construct(int $hoursBeforeCall = 1)
    {
        $this->hoursBeforeCall = $hoursBeforeCall;
    }

    public function handle(OmnichannelNotificationService $notificationService): void
    {
        $calls = ScheduledCall::needsReminder($this->hoursBeforeCall)
            ->with(['lead.person', 'user'])
            ->get();

        foreach ($calls as $call) {
            try {
                $this->sendReminderToLead($call, $notificationService);
                $this->sendReminderToAdvisor($call, $notificationService);

                $call->markReminderSent();

                Log::info("Call reminder sent for call {$call->id}");
            } catch (\Exception $e) {
                Log::error("Failed to send call reminder: {$e->getMessage()}", [
                    'call_id' => $call->id,
                ]);
            }
        }
    }

    protected function sendReminderToLead(ScheduledCall $call, OmnichannelNotificationService $notificationService): void
    {
        if (!$call->lead || !$call->lead->person) {
            return;
        }

        $variables = [
            'name' => $call->lead->person->name,
            'call_type' => ScheduledCall::CALL_TYPES[$call->call_type] ?? $call->call_type,
            'scheduled_time' => $call->scheduled_at->format('M d, Y \a\t h:i A'),
            'advisor_name' => $call->user?->name ?? 'Our team',
            'meeting_link' => $call->meeting_link ?? '',
            'duration' => $call->duration_minutes,
        ];

        $notificationService->sendToLead($call->lead, 'call_reminder_lead', $variables);
    }

    protected function sendReminderToAdvisor(ScheduledCall $call, OmnichannelNotificationService $notificationService): void
    {
        if (!$call->user) {
            return;
        }

        $person = $call->lead?->person;

        Log::info("Would send reminder to advisor {$call->user->email} for call with {$person?->name}");
    }
}
