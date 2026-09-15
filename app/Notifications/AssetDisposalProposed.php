<?php

namespace App\Notifications;

use App\Filament\Resources\AssetDisposals\AssetDisposalResource;
use App\Models\AssetDisposal;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * Tells approvers that assets have been proposed for disposal.
 */
class AssetDisposalProposed extends Notification
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
        $this->disposal->loadCount('lines');

        return FilamentNotification::make()
            ->title("Disposal {$this->disposal->number} needs approval")
            ->body("{$this->disposal->lines_count} asset(s) proposed for disposal.")
            ->icon('heroicon-o-archive-box-x-mark')
            ->status('warning')
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url(AssetDisposalResource::getUrl('view', ['record' => $this->disposal]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
