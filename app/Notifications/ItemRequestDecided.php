<?php

namespace App\Notifications;

use App\Enums\ItemRequestStatus;
use App\Filament\Resources\ItemRequests\ItemRequestResource;
use App\Models\ItemRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Tells whoever recorded an item request that it was approved or rejected.
 */
class ItemRequestDecided extends Notification
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
        $isApproved = $this->request->status === ItemRequestStatus::Approved;

        return FilamentNotification::make()
            ->title($isApproved
                ? "Item request {$this->request->number} approved"
                : "Item request {$this->request->number} rejected")
            ->body($isApproved
                ? 'Issue the goods from a warehouse.'
                : $this->request->rejection_reason)
            ->icon($isApproved ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle')
            ->status($isApproved ? 'success' : 'danger')
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(ItemRequestResource::getUrl('view', ['record' => $this->request]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
