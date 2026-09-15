<?php

namespace App\Filament\Actions;

use App\Exceptions\AssetDisposalException;
use App\Models\AssetDisposal;
use App\Services\AssetDisposalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Withdraws a disposal that will not go ahead, before it is completed.
 */
class CancelAssetDisposalAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancelAssetDisposal';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Cancel Disposal')
            ->icon('heroicon-o-no-symbol')
            ->color('gray')
            ->modalHeading('Cancel Disposal')
            ->modalSubmitActionLabel('Cancel disposal')
            ->visible(fn (AssetDisposal $record): bool => $record->status->isOpen()
                && (bool) Auth::user()?->can('update', $record))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (AssetDisposal $record, array $data): void {
                try {
                    app(AssetDisposalService::class)->cancel($record, $data['reason']);
                } catch (AssetDisposalException $exception) {
                    Notification::make()->title('Disposal not cancelled')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Disposal cancelled')->success()->send();
            });
    }
}
