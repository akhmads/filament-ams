<?php

namespace App\Filament\Actions;

use App\Enums\WorkOrderStatus;
use App\Exceptions\WorkOrderException;
use App\Filament\Resources\WorkOrders\RelationManagers\ChecklistItemsRelationManager;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Marks the work as begun, which unlocks the checklist.
 */
class StartWorkOrderAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'startWorkOrder';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Start')
            ->icon('heroicon-o-play')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Start Work Order')
            ->modalDescription('The checklist can be ticked off once work has started. Details can no longer be edited.')
            ->modalSubmitActionLabel('Start work')
            ->visible(fn (WorkOrder $record): bool => $record->status === WorkOrderStatus::Open
                && (bool) Auth::user()?->can('update', $record))
            ->action(function (WorkOrder $record, Component $livewire): void {
                try {
                    app(WorkOrderService::class)->start($record);
                } catch (WorkOrderException $exception) {
                    Notification::make()->title('Work order not started')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                $livewire->dispatch(ChecklistItemsRelationManager::STATUS_CHANGED_EVENT);

                Notification::make()->title('Work started')->success()->send();
            });
    }
}
