<?php

namespace App\Filament\Actions;

use App\Enums\RepairTicketStatus;
use App\Exceptions\RepairTicketException;
use App\Models\RepairTicket;
use App\Models\User;
use App\Services\RepairTicketService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Closes a ticket that will not be repaired, keeping the reason on record.
 */
class RejectRepairTicketAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'rejectRepairTicket';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->modalHeading('Reject Repair Ticket')
            ->modalDescription('Use this for a false report or a duplicate. The asset is not touched.')
            ->modalSubmitActionLabel('Reject ticket')
            ->visible(fn (RepairTicket $record): bool => in_array($record->status, [RepairTicketStatus::Reported, RepairTicketStatus::Verified, RepairTicketStatus::Approved], strict: true)
                && (bool) Auth::user()?->can('update', $record))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (RepairTicket $record, array $data): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(RepairTicketService::class)->reject($record, $data['reason'], $user);
                } catch (RepairTicketException $exception) {
                    Notification::make()->title('Repair ticket not rejected')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Repair ticket rejected')->success()->send();
            });
    }
}
