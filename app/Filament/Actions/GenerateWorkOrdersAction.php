<?php

namespace App\Filament\Actions;

use App\Models\WorkOrder;
use App\Services\MaintenanceScheduler;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Runs the daily work order generation on demand, for a plan that was just
 * added or when the scheduler is not running.
 */
class GenerateWorkOrdersAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generateWorkOrders';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Open Due Work Orders')
            ->icon('heroicon-o-play')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Open Due Work Orders')
            ->modalDescription('Opens a work order for every active plan that falls due within its lead time. The daily schedule does the same at 06:00.')
            ->modalSubmitActionLabel('Open work orders')
            ->visible(fn (): bool => (bool) Auth::user()?->can('create', WorkOrder::class))
            ->action(function (): void {
                $opened = app(MaintenanceScheduler::class)->generate(CarbonImmutable::today());

                Notification::make()
                    ->title($opened > 0 ? "{$opened} work order(s) opened" : 'Nothing is due yet')
                    ->success()
                    ->send();
            });
    }
}
