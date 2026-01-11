<?php

namespace Webkul\EduCRM\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\EduCRM\Models\TrashLead;

class CleanupTrashLeads implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $batchSize;

    public function __construct(int $batchSize = 100)
    {
        $this->batchSize = $batchSize;
    }

    public function handle(): void
    {
        $count = $this->permanentlyDeleteExpiredLeads();

        Log::info("Trash cleanup completed. Permanently deleted {$count} leads.");
    }

    protected function permanentlyDeleteExpiredLeads(): int
    {
        $totalDeleted = 0;

        do {
            $trashedLeads = TrashLead::where('permanent_delete_at', '<=', now())
                ->where('restored', false)
                ->limit($this->batchSize)
                ->get();

            if ($trashedLeads->isEmpty()) {
                break;
            }

            foreach ($trashedLeads as $trashed) {
                try {
                    DB::transaction(function () use ($trashed) {
                        Log::info("Permanently deleting lead from trash", [
                            'trash_id' => $trashed->id,
                            'original_lead_id' => $trashed->original_lead_id,
                        ]);

                        $trashed->delete();
                    });

                    $totalDeleted++;
                } catch (\Exception $e) {
                    Log::error("Failed to delete trash lead: {$e->getMessage()}", [
                        'trash_id' => $trashed->id,
                    ]);
                }
            }
        } while ($trashedLeads->count() === $this->batchSize);

        return $totalDeleted;
    }
}
