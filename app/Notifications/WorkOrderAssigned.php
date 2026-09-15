<?php

namespace App\Notifications;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Tells a technician a work order is theirs. Stored for the panel's notification
 * bell and sent straight away, so it needs no queue worker.
 */
class WorkOrderAssigned extends Notification
{
    public function __construct(
        public readonly WorkOrder $workOrder,
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
            ->title("Work order {$this->workOrder->number} is assigned to you")
            ->body("{$this->workOrder->title}, due {$this->workOrder->due_date->format('d M Y')}.")
            ->icon('heroicon-o-wrench-screwdriver')
            ->status('info')
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(WorkOrderResource::getUrl('view', ['record' => $this->workOrder]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
