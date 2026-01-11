<?php

namespace Webkul\EduCRM\Services;

use Illuminate\Support\Facades\DB;
use Webkul\EduCRM\Models\UserAssignment;
use Webkul\EduCRM\Models\LeadExtension;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class RoundRobinAssignmentService
{
    protected bool $considerSpecialization;

    public function __construct()
    {
        $this->considerSpecialization = config('educrm.assignment.consider_specialization', true);
    }

    public function assignLead(Lead $lead, string $specialization = null): ?User
    {
        return DB::transaction(function () use ($lead, $specialization) {
            $assignment = $this->getNextAvailableAssignment($specialization);

            if (!$assignment) {
                return null;
            }

            $lead->user_id = $assignment->user_id;
            $lead->save();

            $assignment->incrementLoad();

            $extension = LeadExtension::where('lead_id', $lead->id)->first();
            if ($extension) {
                $extension->first_response_at = now();
                $extension->save();
            }

            return $assignment->user;
        });
    }

    public function getNextAvailableAssignment(string $specialization = null): ?UserAssignment
    {
        $query = UserAssignment::available()->orderedByLoad();

        if ($specialization && $this->considerSpecialization) {
            $specialized = (clone $query)->withSpecialization($specialization)->first();
            if ($specialized) {
                return $specialized;
            }
        }

        return $query->first();
    }

    public function assignBulk(array $leadIds, string $specialization = null): array
    {
        $results = [];

        foreach ($leadIds as $leadId) {
            $lead = Lead::find($leadId);
            if ($lead && !$lead->user_id) {
                $user = $this->assignLead($lead, $specialization);
                $results[$leadId] = $user ? $user->id : null;
            }
        }

        return $results;
    }

    public function reassignLead(Lead $lead, int $newUserId, string $reason = null): bool
    {
        return DB::transaction(function () use ($lead, $newUserId, $reason) {
            $oldUserId = $lead->user_id;

            if ($oldUserId) {
                $oldAssignment = UserAssignment::where('user_id', $oldUserId)->first();
                if ($oldAssignment) {
                    $oldAssignment->decrementLoad();
                }
            }

            $lead->user_id = $newUserId;
            $lead->save();

            $newAssignment = UserAssignment::getOrCreateForUser($newUserId);
            $newAssignment->incrementLoad();

            return true;
        });
    }

    public function unassignLead(Lead $lead): bool
    {
        return DB::transaction(function () use ($lead) {
            if ($lead->user_id) {
                $assignment = UserAssignment::where('user_id', $lead->user_id)->first();
                if ($assignment) {
                    $assignment->decrementLoad();
                }
            }

            $lead->user_id = null;
            $lead->save();

            return true;
        });
    }

    public function getAssignmentStats(): array
    {
        $assignments = UserAssignment::with('user')->get();

        return $assignments->map(function ($assignment) {
            return [
                'user_id' => $assignment->user_id,
                'user_name' => $assignment->user?->name,
                'current_load' => $assignment->current_load,
                'max_load' => $assignment->max_load,
                'today_assigned' => $assignment->today_assigned,
                'daily_limit' => $assignment->daily_limit,
                'is_available' => $assignment->is_available,
                'utilization' => $assignment->max_load > 0
                    ? round(($assignment->current_load / $assignment->max_load) * 100, 1)
                    : 0,
                'last_assigned_at' => $assignment->last_assigned_at,
                'specializations' => $assignment->specializations,
            ];
        })->toArray();
    }

    public function setUserAvailability(int $userId, bool $isAvailable): UserAssignment
    {
        $assignment = UserAssignment::getOrCreateForUser($userId);
        $assignment->is_available = $isAvailable;
        $assignment->save();

        return $assignment;
    }

    public function updateUserCapacity(int $userId, int $maxLoad, int $dailyLimit = null): UserAssignment
    {
        $assignment = UserAssignment::getOrCreateForUser($userId);
        $assignment->max_load = $maxLoad;

        if ($dailyLimit !== null) {
            $assignment->daily_limit = $dailyLimit;
        }

        $assignment->save();

        return $assignment;
    }

    public function updateUserSpecializations(int $userId, array $specializations): UserAssignment
    {
        $assignment = UserAssignment::getOrCreateForUser($userId);
        $assignment->specializations = $specializations;
        $assignment->save();

        return $assignment;
    }

    public function resetDailyCounters(): int
    {
        return UserAssignment::query()->update(['today_assigned' => 0]);
    }

    public function recalculateLoads(): void
    {
        $assignments = UserAssignment::all();

        foreach ($assignments as $assignment) {
            $activeLeadsCount = Lead::where('user_id', $assignment->user_id)
                ->whereHas('stage', function ($query) {
                    $query->whereNotIn('code', ['won', 'lost']);
                })
                ->count();

            $assignment->current_load = $activeLeadsCount;
            $assignment->save();
        }
    }

    public function getAvailableUsers(string $specialization = null): array
    {
        $query = UserAssignment::available()->with('user');

        if ($specialization && $this->considerSpecialization) {
            $query->withSpecialization($specialization);
        }

        return $query->get()->map(function ($assignment) {
            return [
                'user_id' => $assignment->user_id,
                'user_name' => $assignment->user?->name,
                'current_load' => $assignment->current_load,
                'available_capacity' => $assignment->max_load - $assignment->current_load,
            ];
        })->toArray();
    }

    public function autoAssignUnassignedLeads(int $limit = 100): array
    {
        $leads = Lead::whereNull('user_id')
            ->whereHas('stage', function ($query) {
                $query->whereNotIn('code', ['won', 'lost']);
            })
            ->limit($limit)
            ->get();

        $results = [];

        foreach ($leads as $lead) {
            $extension = LeadExtension::where('lead_id', $lead->id)->first();

            if ($extension && $extension->qualification_status === 'qualified') {
                $user = $this->assignLead($lead);
                $results[$lead->id] = $user ? $user->id : null;
            }
        }

        return $results;
    }
}
