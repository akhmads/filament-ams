<?php

namespace App\Filament\Actions;

use App\Exceptions\WorkOrderException;
use App\Filament\Resources\WorkOrders\RelationManagers\ChecklistItemsRelationManager;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Cancels work that will not be done, keeping the reason on record. A cancelled
 * work order still counts for its plan, so the next one follows the schedule.
 */
class CancelWorkOrderAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancelWorkOrder';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->modalHeading('Cancel Work Order')
            ->modalSubmitActionLabel('Cancel work order')
            ->visible(fn (WorkOrder $record): bool => $record->status->isOpen()
                && (bool) Auth::user()?->can('update', $record))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (WorkOrder $record, array $data, Component $livewire): void {
                try {
                    app(WorkOrderService::class)->cancel($record, $data['reason']);
                } catch (WorkOrderException $exception) {
                    Notification::make()->title('Work order not cancelled')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                $livewire->dispatch(ChecklistItemsRelationManager::STATUS_CHANGED_EVENT);

                Notification::make()->title('Work order cancelled')->success()->send();
            });
    }
}
