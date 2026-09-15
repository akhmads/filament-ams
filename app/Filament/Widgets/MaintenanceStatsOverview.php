<?php

namespace App\Filament\Widgets;

use App\Enums\AssetStatus;
use App\Enums\RepairTicketStatus;
use App\Models\Asset;
use App\Models\RepairTicket;
use App\Models\WorkOrder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

/**
 * Work that is due, overdue or taking assets out of use.
 */
class MaintenanceStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Maintenance';

    public static function canView(): bool
    {
        return (bool) Auth::user()?->can('viewAny', WorkOrder::class);
    }

    protected function getStats(): array
    {
        $openWorkOrders = WorkOrder::query()->open()->count();
        $overdue = WorkOrder::query()->overdue()->count();
        $dueWithinMonth = WorkOrder::query()
            ->open()
            ->where('due_date', '>=', today()->toDateString())
            ->where('due_date', '<', today()->addDays(31)->toDateString())
            ->count();
        $openRepairs = RepairTicket::query()->open()->count();
        $awaitingApproval = RepairTicket::query()->where('status', RepairTicketStatus::Verified->value)->count();
        $underRepair = Asset::query()->where('status', AssetStatus::UnderRepair->value)->count();

        return [
            Stat::make('Open Work Orders', Number::format($openWorkOrders))
                ->description("{$overdue} overdue")
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color($overdue > 0 ? 'danger' : 'gray'),

            Stat::make('Due in 30 Days', Number::format($dueWithinMonth))
                ->description('Open work orders due within a month')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($dueWithinMonth > 0 ? 'warning' : 'gray'),

            Stat::make('Open Repair Tickets', Number::format($openRepairs))
                ->description("{$awaitingApproval} waiting for approval")
                ->descriptionIcon('heroicon-m-wrench')
                ->color($openRepairs > 0 ? 'warning' : 'gray'),

            Stat::make('Assets Under Repair', Number::format($underRepair))
                ->description('Out of use until the repair is finished')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($underRepair > 0 ? 'danger' : 'gray'),
        ];
    }
}
