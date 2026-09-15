<?php

namespace App\Notifications;

use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Models\RepairTicket;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Tells a technician a repair is theirs.
 */
class RepairTicketAssigned extends Notification
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
        return FilamentNotification::make()
            ->title("Repair ticket {$this->ticket->number} is assigned to you")
            ->body("{$this->ticket->title}. It can be started once approved.")
            ->icon('heroicon-o-wrench')
            ->status('info')
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(RepairTicketResource::getUrl('view', ['record' => $this->ticket]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
