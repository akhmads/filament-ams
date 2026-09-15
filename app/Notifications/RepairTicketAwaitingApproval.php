<?php

namespace App\Notifications;

use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Models\RepairTicket;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

/**
 * Tells the people who approve repairs that a verified repair is waiting on them.
 */
class RepairTicketAwaitingApproval extends Notification
{
    public function __construct(
        public readonly RepairTicket $ticket,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $estimate = 'Rp '.Number::format((float) $this->ticket->estimated_cost, precision: 0);

        return FilamentNotification::make()
            ->title("Repair ticket {$this->ticket->number} is waiting for approval")
            ->body("{$this->ticket->title}, estimated at {$estimate}.")
            ->icon('heroicon-o-check-badge')
            ->status('info')
            ->actions([
                Action::make('view')
                    ->label('Review')
                    ->url(RepairTicketResource::getUrl('view', ['record' => $this->ticket]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
