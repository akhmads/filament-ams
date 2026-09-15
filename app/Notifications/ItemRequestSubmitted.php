<?php

namespace App\Notifications;

use App\Filament\Resources\ItemRequests\ItemRequestResource;
use App\Models\ItemRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Tells approvers that an item request is waiting for their decision.
 */
class ItemRequestSubmitted extends Notification
{
    public function __construct(
        public readonly ItemRequest $request,
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
        $this->request->loadMissing('employee')->loadCount('lines');

        return FilamentNotification::make()
            ->title("Item request {$this->request->number} needs approval")
            ->body("{$this->request->employee?->name} asks for {$this->request->lines_count} item(s).")
            ->icon('heroicon-o-inbox-arrow-down')
            ->status('info')
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(ItemRequestResource::getUrl('view', ['record' => $this->request]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
