<?php

namespace App\Filament\Widgets;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class AssetStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $active = Asset::query()->active();

        $total = (clone $active)->count();
        $value = (clone $active)->sum('acquisition_cost');
        $available = (clone $active)->assignable()->count();
        $inUse = (clone $active)->where('status', AssetStatus::InUse->value)->count();
        $warranty = (clone $active)->warrantyExpiringWithin(60)->count();
        $overdue = AssetAssignment::query()->overdue()->count();

        return [
            Stat::make('Active Assets', Number::format($total))
                ->description('Excludes lost and disposed assets')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),

            Stat::make('Acquisition Value', 'Rp '.Number::format($value, precision: 0))
                ->description('Total acquisition cost of active assets')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Ready to Allocate', Number::format($available))
                ->description($inUse.' in use')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($available > 0 ? 'success' : 'gray'),

            Stat::make('Needs Attention', Number::format($warranty + $overdue))
                ->description("{$warranty} warranties expiring soon · {$overdue} overdue loans")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color(($warranty + $overdue) > 0 ? 'warning' : 'gray'),
        ];
    }
}
