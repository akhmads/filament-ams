<?php

namespace App\Filament\Actions;

use App\Enums\RepairTicketStatus;
use App\Enums\RepairType;
use App\Exceptions\RepairTicketException;
use App\Models\RepairTicket;
use App\Services\RepairTicketService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Takes the asset out of use for an approved repair.
 */
class StartRepairAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'startRepair';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Start Repair')
            ->icon('heroicon-o-play')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Start Repair')
            ->modalDescription(fn (RepairTicket $record): string => $record->repair_type === RepairType::Vendor
                ? 'The asset is recorded as handed to the vendor and marked Under Repair until the repair is finished.'
                : 'The asset is marked Under Repair and cannot be handed over until the repair is finished.')
            ->modalSubmitActionLabel('Start repair')
            ->visible(fn (RepairTicket $record): bool => $record->status === RepairTicketStatus::Approved
                && (bool) Auth::user()?->can('update', $record))
            ->action(function (RepairTicket $record): void {
                try {
                    app(RepairTicketService::class)->start($record);
                } catch (RepairTicketException $exception) {
                    Notification::make()->title('Repair not started')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Repair started')->success()->send();
            });
    }
}
