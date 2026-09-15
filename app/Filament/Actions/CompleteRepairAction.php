<?php

namespace App\Filament\Actions;

use App\Enums\AssetCondition;
use App\Enums\RepairTicketStatus;
use App\Exceptions\RepairTicketException;
use App\Models\RepairTicket;
use App\Models\User;
use App\Services\RepairTicketService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;

/**
 * Records how the repair ended and returns the asset to where it was sent from.
 */
class CompleteRepairAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'completeRepair';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Finish Repair')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->modalHeading('Finish Repair')
            ->modalDescription('The asset goes back to where it was before the repair. An asset that cannot be repaired is retired.')
            ->modalSubmitActionLabel('Finish repair')
            ->visible(fn (RepairTicket $record): bool => $record->status === RepairTicketStatus::InRepair
                && (bool) Auth::user()?->can('update', $record))
            ->fillForm(fn (RepairTicket $record): array => [
                'outcome' => RepairTicketStatus::Repaired->value,
                'condition' => AssetCondition::Good->value,
                'actual_cost' => $record->estimated_cost,
            ])
            ->schema([
                Select::make('outcome')
                    ->label('Outcome')
                    ->options([
                        RepairTicketStatus::Repaired->value => RepairTicketStatus::Repaired->getLabel(),
                        RepairTicketStatus::Unrepairable->value => RepairTicketStatus::Unrepairable->getLabel(),
                    ])
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
                Grid::make(2)->schema([
                    Select::make('condition')
                        ->label('Asset Condition')
                        ->options(AssetCondition::class)
                        ->selectablePlaceholder(false)
                        ->required(),
                    TextInput::make('actual_cost')
                        ->label('Actual Cost')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp'),
                ]),
                Textarea::make('resolution')
                    ->label('Resolution')
                    ->helperText('What was done, or why the asset cannot be repaired.')
                    ->rows(3)
                    ->required(fn (Get $get): bool => $get('outcome') === RepairTicketStatus::Unrepairable->value),
            ])
            ->action(function (RepairTicket $record, array $data, Action $action): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(RepairTicketService::class)->complete(
                        ticket: $record,
                        isRepaired: $data['outcome'] !== RepairTicketStatus::Unrepairable->value,
                        completedBy: $user,
                        actualCost: filled($data['actual_cost'] ?? null) ? (string) $data['actual_cost'] : null,
                        resolution: $data['resolution'] ?? null,
                        condition: self::conditionFrom($data['condition'] ?? null),
                    );
                } catch (RepairTicketException $exception) {
                    Notification::make()->title('Repair not finished')->body($exception->getMessage())->danger()->persistent()->send();

                    $action->halt();
                }

                Notification::make()->title('Repair finished')->success()->send();
            });
    }

    private static function conditionFrom(mixed $state): ?AssetCondition
    {
        return $state instanceof AssetCondition ? $state : AssetCondition::tryFrom((string) $state);
    }
}
