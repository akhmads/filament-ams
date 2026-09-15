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

/**
 * Signs off writing the proposed assets off the books.
 */
class ApproveAssetDisposalAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approveAssetDisposal';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Approve')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve Disposal')
            ->modalDescription('The assets stay on the books until the disposal is completed.')
            ->modalSubmitActionLabel('Approve')
            ->visible(fn (AssetDisposal $record): bool => $record->status === AssetDisposalStatus::Proposed
                && (bool) Auth::user()?->can('approve', $record))
            ->action(function (AssetDisposal $record): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(AssetDisposalService::class)->approve($record, $user);
                } catch (AssetDisposalException $exception) {
                    Notification::make()->title('Disposal not approved')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Disposal approved')->success()->send();
            });
    }
}
