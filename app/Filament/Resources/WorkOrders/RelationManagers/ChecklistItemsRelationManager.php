<?php

namespace App\Filament\Resources\WorkOrders\RelationManagers;

use App\Enums\WorkOrderStatus;
use App\Exceptions\WorkOrderException;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistItem;
use App\Services\WorkOrderService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * The checklist of a work order. Tasks are written on the work order form; here
 * they are only ticked off, and only while the work is in progress.
 */
class ChecklistItemsRelationManager extends RelationManager
{
    /**
     * Dispatched by the work order actions so the checklist unlocks or locks
     * without reloading the page.
     */
    public const STATUS_CHANGED_EVENT = 'work-order-status-changed';

    protected static string $relationship = 'checklistItems';

    protected static ?string $title = 'Checklist';

    #[On(self::STATUS_CHANGED_EVENT)]
    public function refreshWorkOrder(): void
    {
        $this->getOwnerRecord()->refresh();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Checklist')
            ->description(fn (): string => $this->canTick()
                ? 'Tick each task as it is done.'
                : 'Tasks can be ticked once the work order is in progress.')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('doneBy'))
            ->emptyStateHeading('No checklist for this work order')
            ->columns([
                ToggleColumn::make('is_done')
                    ->label('Done')
                    ->disabled(fn (): bool => ! $this->canTick())
                    ->updateStateUsing(fn (WorkOrderChecklistItem $record, bool $state): bool => $this->tick($record, $state)),
                TextColumn::make('task')->label('Task')->wrap(),
                TextColumn::make('doneBy.name')->label('Done By')->placeholder('—'),
                TextColumn::make('done_at')->label('Done At')->dateTime('d M Y H:i')->placeholder('—'),
            ])
            ->paginated(false);
    }

    private function canTick(): bool
    {
        $workOrder = $this->getOwnerRecord();

        return $workOrder instanceof WorkOrder
            && $workOrder->status === WorkOrderStatus::InProgress
            && (bool) Auth::user()?->can('update', $workOrder);
    }

    private function tick(WorkOrderChecklistItem $item, bool $isDone): bool
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $this->canTick()) {
            return $item->is_done;
        }

        try {
            app(WorkOrderService::class)->markChecklistItem($item, $isDone, $user);
        } catch (WorkOrderException $exception) {
            Notification::make()->title('Checklist not updated')->body($exception->getMessage())->danger()->send();
        }

        return $item->is_done;
    }
}
