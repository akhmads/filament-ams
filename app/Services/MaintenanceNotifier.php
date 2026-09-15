<?php

namespace App\Services;

use App\Models\RepairTicket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\MaintenanceReminder;
use App\Notifications\RepairTicketAssigned;
use App\Notifications\RepairTicketAwaitingApproval;
use App\Notifications\RepairTicketReported;
use App\Notifications\WorkOrderAssigned;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Decides who hears about maintenance work in the panel's notification bell.
 * Nobody is told about something they did themselves, and inactive users are
 * never notified.
 */
class MaintenanceNotifier
{
    /** Whoever plans maintenance also looks after work orders nobody is assigned to. */
    private const PLANNER_PERMISSION = 'Create:MaintenancePlan';

    private const REPAIR_HANDLER_PERMISSION = 'Update:RepairTicket';

    private const REPAIR_APPROVER_PERMISSION = 'Approve:RepairTicket';

    public function workOrderAssigned(WorkOrder $workOrder, ?User $assignedBy = null): void
    {
        $this->assignee($workOrder->assigned_to, $assignedBy)?->notify(new WorkOrderAssigned($workOrder));
    }

    public function repairTicketReported(RepairTicket $ticket, ?User $reportedBy = null): void
    {
        Notification::send($this->usersHolding(self::REPAIR_HANDLER_PERMISSION, $reportedBy), new RepairTicketReported($ticket));
    }

    public function repairTicketAwaitingApproval(RepairTicket $ticket, ?User $verifiedBy = null): void
    {
        Notification::send($this->usersHolding(self::REPAIR_APPROVER_PERMISSION, $verifiedBy), new RepairTicketAwaitingApproval($ticket));
    }

    public function repairTicketAssigned(RepairTicket $ticket, ?User $assignedBy = null): void
    {
        $this->assignee($ticket->assigned_to, $assignedBy)?->notify(new RepairTicketAssigned($ticket));
    }

    /**
     * Tells each technician how many of their open work orders are overdue or due
     * today. Work orders nobody is assigned to count for every planner. A user is
     * reminded at most once a day, so running this again sends nothing new.
     *
     * @return int The number of users reminded.
     */
    public function sendDailyReminders(CarbonImmutable $today): int
    {
        // Ranges rather than equality: SQLite keeps a time on date columns.
        $counts = WorkOrder::query()
            ->open()
            ->where('due_date', '<', $today->addDay()->toDateString())
            ->groupBy('assigned_to')
            ->selectRaw('assigned_to, count(*) as due_count, sum(case when due_date < ? then 1 else 0 end) as overdue_count', [$today->toDateString()])
            ->toBase()
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->assigned_to);

        if ($counts->isEmpty()) {
            return 0;
        }

        $unassigned = $counts->pull('');

        $plannerIds = $unassigned === null
            ? []
            : User::query()->holdingPermission(self::PLANNER_PERMISSION)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        $remindedToday = DatabaseNotification::query()
            ->where('type', MaintenanceReminder::class)
            ->where('notifiable_type', (new User)->getMorphClass())
            ->where('created_at', '>=', $today->startOfDay())
            ->pluck('notifiable_id');

        $recipients = User::query()
            ->where('is_active', true)
            ->whereIn('id', [...$counts->keys()->map(fn (string $id): int => (int) $id)->all(), ...$plannerIds])
            ->whereNotIn('id', $remindedToday)
            ->get();

        foreach ($recipients as $user) {
            $own = $counts->get((string) $user->id);
            $shared = in_array((int) $user->id, $plannerIds, strict: true) ? $unassigned : null;

            $due = (int) ($own->due_count ?? 0) + (int) ($shared->due_count ?? 0);
            $overdue = (int) ($own->overdue_count ?? 0) + (int) ($shared->overdue_count ?? 0);

            $user->notify(new MaintenanceReminder(overdue: $overdue, dueToday: $due - $overdue));
        }

        return $recipients->count();
    }

    private function assignee(int|string|null $userId, ?User $actingUser): ?User
    {
        if ($userId === null || (int) $userId === $actingUser?->id) {
            return null;
        }

        return User::query()->where('is_active', true)->find((int) $userId);
    }

    /**
     * @return Collection<int, User>
     */
    private function usersHolding(string $permission, ?User $except = null): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->holdingPermission($permission)
            ->when($except !== null, fn (Builder $query) => $query->whereKeyNot($except->getKey()))
            ->get();
    }
}
