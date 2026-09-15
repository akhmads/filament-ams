<?php

namespace App\Filament\Actions;

use App\Enums\RepairTicketStatus;
use App\Exceptions\RepairTicketException;
use App\Models\RepairTicket;
use App\Models\User;
use App\Services\RepairTicketService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

/**
 * Signs off a verified repair and its estimated cost, so it can start.
 */
class ApproveRepairTicketAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approveRepairTicket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Approve')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve Repair')
            ->modalDescription(fn (RepairTicket $record): string => 'Approve this repair at an estimated Rp '
                .Number::format((float) $record->estimated_cost, precision: 0).'.')
            ->modalSubmitActionLabel('Approve')
            ->visible(fn (RepairTicket $record): bool => $record->status === RepairTicketStatus::Verified
                && (bool) Auth::user()?->can('approve', $record))
            ->action(function (RepairTicket $record): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(RepairTicketService::class)->approve($record, $user);
                } catch (RepairTicketException $exception) {
                    Notification::make()->title('Repair not approved')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Repair approved')->success()->send();
            });
    }
}
