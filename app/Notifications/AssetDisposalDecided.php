<?php

namespace App\Notifications;

use App\Enums\AssetDisposalStatus;
use App\Filament\Resources\AssetDisposals\AssetDisposalResource;
use App\Models\AssetDisposal;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Tells whoever proposed a disposal that it was approved or rejected.
 */
class AssetDisposalDecided extends Notification
{
    public function __construct(
        public readonly AssetDisposal $disposal,
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
        $isApproved = $this->disposal->status === AssetDisposalStatus::Approved;

        return FilamentNotification::make()
            ->title($isApproved
                ? "Disposal {$this->disposal->number} approved"
                : "Disposal {$this->disposal->number} rejected")
            ->body($isApproved
                ? 'Complete it once the assets have been sold, donated or scrapped.'
                : $this->disposal->rejection_reason)
            ->icon($isApproved ? 'heroicon-o-check-badge' : 'heroicon-o-x-circle')
            ->status($isApproved ? 'success' : 'danger')
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(AssetDisposalResource::getUrl('view', ['record' => $this->disposal]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
