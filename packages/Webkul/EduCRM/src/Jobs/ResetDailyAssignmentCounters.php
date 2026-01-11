<?php

namespace Webkul\EduCRM\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Webkul\EduCRM\Services\RoundRobinAssignmentService;

class ResetDailyAssignmentCounters implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(RoundRobinAssignmentService $assignmentService): void
    {
        $count = $assignmentService->resetDailyCounters();

        Log::info("Reset daily assignment counters for {$count} users");
    }
}
