<?php

namespace App\Filament\Actions;

use App\Enums\RepairTicketStatus;
use App\Enums\RepairType;
use App\Exceptions\RepairTicketException;
use App\Models\RepairTicket;
use App\Models\Supplier;
use App\Models\User;
use App\Services\RepairTicketService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;

/**
 * Confirms the damage and decides who repairs it: in-house or a service vendor.
 */
class VerifyRepairTicketAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'verifyRepairTicket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Verify')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('info')
            ->modalHeading('Verify Repair Ticket')
            ->modalDescription('Confirm the damage and decide who repairs it. The repair then waits for approval.')
            ->modalSubmitActionLabel('Verify')
            ->visible(fn (RepairTicket $record): bool => $record->status === RepairTicketStatus::Reported
                && (bool) Auth::user()?->can('update', $record))
            ->fillForm(fn (RepairTicket $record): array => [
                'repair_type' => ($record->is_under_warranty ? RepairType::Vendor : RepairType::Internal)->value,
                'estimated_cost' => $record->estimated_cost,
            ])
            ->schema([
                Select::make('repair_type')
                    ->label('Repaired By')
                    ->options(RepairType::class)
                    ->selectablePlaceholder(false)
                    ->helperText(fn (RepairTicket $record): ?string => $record->is_under_warranty
                        ? 'The asset was under warranty when reported: claim the repair with the vendor.'
                        : null)
                    ->required()
                    ->live(),
                Grid::make(2)->schema([
                    Select::make('supplier_id')
                        ->label('Service Vendor')
                        ->options(fn (): array => Supplier::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->visible(fn (Get $get): bool => self::repairTypeFrom($get('repair_type')) === RepairType::Vendor)
                        ->required(fn (Get $get): bool => self::repairTypeFrom($get('repair_type')) === RepairType::Vendor),
                    Select::make('assigned_to')
                        ->label('Technician')
                        ->helperText('Who carries out or follows up the repair.')
                        ->options(fn (): array => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),
                    TextInput::make('estimated_cost')
                        ->label('Estimated Cost')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('Rp'),
                ]),
            ])
            ->action(function (RepairTicket $record, array $data, Action $action): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(RepairTicketService::class)->verify(
                        ticket: $record,
                        repairType: self::repairTypeFrom($data['repair_type']) ?? RepairType::Internal,
                        verifiedBy: $user,
                        assignedTo: filled($data['assigned_to'] ?? null) ? (int) $data['assigned_to'] : null,
                        supplierId: filled($data['supplier_id'] ?? null) ? (int) $data['supplier_id'] : null,
                        estimatedCost: filled($data['estimated_cost'] ?? null) ? (string) $data['estimated_cost'] : null,
                    );
                } catch (RepairTicketException $exception) {
                    Notification::make()->title('Repair ticket not verified')->body($exception->getMessage())->danger()->persistent()->send();

                    $action->halt();
                }

                Notification::make()->title('Repair ticket verified')->success()->send();
            });
    }

    private static function repairTypeFrom(mixed $state): ?RepairType
    {
        return $state instanceof RepairType ? $state : RepairType::tryFrom((string) $state);
    }
}
