<?php

namespace App\Filament\Actions;

use App\Enums\AssetDisposalStatus;
use App\Exceptions\AssetDisposalException;
use App\Models\AssetDisposal;
use App\Models\User;
use App\Services\AssetDisposalService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Writes the approved assets off: book values and gain or loss are fixed and
 * every asset becomes Disposed.
 */
class CompleteAssetDisposalAction extends Action
{
    /**
     * Dispatched so the asset list shows the book values without a reload.
     */
    public const COMPLETED_EVENT = 'asset-disposal-completed';

    public static function getDefaultName(): ?string
    {
        return 'completeAssetDisposal';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Complete Disposal')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Complete Disposal')
            ->modalDescription(fn (AssetDisposal $record): string => "Every asset on this disposal is written off as of {$record->disposal_date->format('d M Y')}. "
                .'Book values and gain or loss are fixed from the depreciation posted up to the month before. This cannot be undone.')
            ->modalSubmitActionLabel('Write off')
            ->visible(fn (AssetDisposal $record): bool => $record->status === AssetDisposalStatus::Approved
                && (bool) Auth::user()?->can('update', $record))
            ->action(function (AssetDisposal $record, Component $livewire): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(AssetDisposalService::class)->complete($record, $user);
                } catch (AssetDisposalException $exception) {
                    Notification::make()->title('Disposal not completed')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                $livewire->dispatch(self::COMPLETED_EVENT);

                Notification::make()->title('Disposal completed')->success()->send();
            });
    }
}
