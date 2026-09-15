<?php

namespace App\Notifications;

use App\Enums\RepairPriority;
use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Models\RepairTicket;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Tells repair staff that damage has been reported and needs verifying.
 */
class RepairTicketReported extends Notification
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
            ->title("Damage reported on {$this->ticket->number}")
            ->body("{$this->ticket->title}. Verify it to plan the repair.")
            ->icon('heroicon-o-exclamation-triangle')
            ->status($this->ticket->priority === RepairPriority::Urgent ? 'danger' : 'warning')
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(RepairTicketResource::getUrl('view', ['record' => $this->ticket]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
