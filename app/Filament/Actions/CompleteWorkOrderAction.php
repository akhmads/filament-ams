<?php

namespace App\Filament\Actions;

use App\Enums\WorkOrderResult;
use App\Enums\WorkOrderStatus;
use App\Exceptions\WorkOrderException;
use App\Filament\Resources\WorkOrders\RelationManagers\ChecklistItemsRelationManager;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Records the outcome of the work: result, actual cost, time spent and findings.
 */
class CompleteWorkOrderAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'completeWorkOrder';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Complete')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->modalHeading('Complete Work Order')
            ->modalDescription('An OK result needs every checklist item ticked. Complete unfinished work as Needs Follow-up.')
            ->modalSubmitActionLabel('Complete')
            ->visible(fn (WorkOrder $record): bool => $record->status === WorkOrderStatus::InProgress
                && (bool) Auth::user()?->can('update', $record))
            ->fillForm(fn (WorkOrder $record): array => [
                'result' => WorkOrderResult::Ok->value,
                'actual_cost' => $record->estimated_cost,
            ])
            ->schema([
                Select::make('result')
                    ->label('Result')
                    ->options(WorkOrderResult::class)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
                Grid::make(2)->schema([
                    TextInput::make('actual_cost')
                        ->label('Actual Cost')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp'),
                    TextInput::make('labor_minutes')
                        ->label('Time Spent')
                        ->integer()
                        ->minValue(0)
                        ->suffix('minutes'),
                ]),
                Textarea::make('findings')
                    ->label('Findings')
                    ->helperText('Required when follow-up is needed.')
                    ->rows(3)
                    ->required(fn (Get $get): bool => self::resultFrom($get('result')) === WorkOrderResult::NeedsFollowUp),
            ])
            ->action(function (WorkOrder $record, array $data, Action $action, Component $livewire): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(WorkOrderService::class)->complete(
                        workOrder: $record,
                        result: self::resultFrom($data['result']) ?? WorkOrderResult::Ok,
                        user: $user,
                        actualCost: filled($data['actual_cost'] ?? null) ? (string) $data['actual_cost'] : null,
                        laborMinutes: filled($data['labor_minutes'] ?? null) ? (int) $data['labor_minutes'] : null,
                        findings: $data['findings'] ?? null,
                    );
                } catch (WorkOrderException $exception) {
                    Notification::make()->title('Work order not completed')->body($exception->getMessage())->danger()->persistent()->send();

                    $action->halt();
                }

                $livewire->dispatch(ChecklistItemsRelationManager::STATUS_CHANGED_EVENT);

                Notification::make()->title('Work order completed')->success()->send();
            });
    }

    private static function resultFrom(mixed $state): ?WorkOrderResult
    {
        return $state instanceof WorkOrderResult ? $state : WorkOrderResult::tryFrom((string) $state);
    }
}
