<?php

namespace App\Notifications;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * The daily count of a user's work orders that are overdue or due today.
 */
class MaintenanceReminder extends Notification
{
    public function __construct(
        public readonly int $overdue,
        public readonly int $dueToday,
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
        $counts = array_filter([
            $this->overdue > 0 ? "{$this->overdue} overdue" : null,
            $this->dueToday > 0 ? "{$this->dueToday} due today" : null,
        ]);

        return FilamentNotification::make()
            ->title('Work orders need attention')
            ->body('Work orders: '.implode(' · ', $counts).'.')
            ->icon('heroicon-o-clock')
            ->status($this->overdue > 0 ? 'warning' : 'info')
            ->actions([
                Action::make('view')
                    ->label('View work orders')
                    ->url(WorkOrderResource::getUrl('index', ['tab' => $this->overdue > 0 ? 'overdue' : 'open']))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
