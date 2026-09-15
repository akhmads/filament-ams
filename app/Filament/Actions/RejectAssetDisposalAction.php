<?php

namespace App\Filament\Actions;

use App\Enums\AssetDisposalStatus;
use App\Exceptions\AssetDisposalException;
use App\Models\AssetDisposal;
use App\Models\User;
use App\Services\AssetDisposalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Turns a disposal proposal down, keeping the reason on record.
 */
class RejectAssetDisposalAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'rejectAssetDisposal';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->modalHeading('Reject Disposal')
            ->modalSubmitActionLabel('Reject disposal')
            ->visible(fn (AssetDisposal $record): bool => $record->status === AssetDisposalStatus::Proposed
                && (bool) Auth::user()?->can('approve', $record))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (AssetDisposal $record, array $data): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(AssetDisposalService::class)->reject($record, $data['reason'], $user);
                } catch (AssetDisposalException $exception) {
                    Notification::make()->title('Disposal not rejected')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Disposal rejected')->success()->send();
            });
    }
}
