<?php

namespace Webkul\EduCRM\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Webkul\EduCRM\Models\PaymentInstallment;
use Webkul\EduCRM\Services\OmnichannelNotificationService;

class SendPaymentReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $daysBeforeDue;
    protected int $maxReminders;

    public function __construct(int $daysBeforeDue = 3, int $maxReminders = 3)
    {
        $this->daysBeforeDue = $daysBeforeDue;
        $this->maxReminders = $maxReminders;
    }

    public function handle(OmnichannelNotificationService $notificationService): void
    {
        $this->sendUpcomingReminders($notificationService);
        $this->sendOverdueReminders($notificationService);
    }

    protected function sendUpcomingReminders(OmnichannelNotificationService $notificationService): void
    {
        $installments = PaymentInstallment::upcoming($this->daysBeforeDue)
            ->needsReminder($this->maxReminders)
            ->with(['studentPayment.lead.person'])
            ->get();

        foreach ($installments as $installment) {
            try {
                $lead = $installment->studentPayment->lead;
                $person = $lead->person;

                if (!$person) {
                    continue;
                }

                $variables = [
                    'name' => $person->name,
                    'amount' => number_format($installment->amount, 2),
                    'currency' => $installment->studentPayment->currency,
                    'due_date' => $installment->due_date->format('M d, Y'),
                    'installment_number' => $installment->installment_number,
                    'days_until_due' => $installment->due_date->diffInDays(now()),
                ];

                $notificationService->sendToLead($lead, 'payment_reminder_upcoming', $variables);

                $installment->recordReminderSent();

                Log::info("Payment reminder sent for installment {$installment->id}");
            } catch (\Exception $e) {
                Log::error("Failed to send payment reminder: {$e->getMessage()}", [
                    'installment_id' => $installment->id,
                ]);
            }
        }
    }

    protected function sendOverdueReminders(OmnichannelNotificationService $notificationService): void
    {
        $installments = PaymentInstallment::overdue()
            ->needsReminder($this->maxReminders)
            ->with(['studentPayment.lead.person'])
            ->get();

        foreach ($installments as $installment) {
            try {
                $lead = $installment->studentPayment->lead;
                $person = $lead->person;

                if (!$person) {
                    continue;
                }

                $variables = [
                    'name' => $person->name,
                    'amount' => number_format($installment->amount, 2),
                    'currency' => $installment->studentPayment->currency,
                    'due_date' => $installment->due_date->format('M d, Y'),
                    'days_overdue' => $installment->due_date->diffInDays(now()),
                    'installment_number' => $installment->installment_number,
                ];

                $notificationService->sendToLead($lead, 'payment_reminder_overdue', $variables);

                $installment->recordReminderSent();
                $installment->status = 'overdue';
                $installment->save();

                $installment->studentPayment->updateStatus();

                Log::info("Overdue reminder sent for installment {$installment->id}");
            } catch (\Exception $e) {
                Log::error("Failed to send overdue reminder: {$e->getMessage()}", [
                    'installment_id' => $installment->id,
                ]);
            }
        }
    }
}
