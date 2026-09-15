<?php

namespace App\Filament\Actions;

use App\Enums\AssetAuditStatus;
use App\Exceptions\AssetAuditException;
use App\Filament\Resources\AssetAudits\RelationManagers\LinesRelationManager;
use App\Models\AssetAudit;
use App\Models\User;
use App\Services\AssetAuditService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Stops scanning and hands the audit over for review.
 */
class FinishCountingAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'finishCounting';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Finish Counting')
            ->icon('heroicon-o-flag')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Finish Counting')
            ->modalDescription('Assets not scanned yet are recorded as missing, and no more scans are accepted.')
            ->modalSubmitActionLabel('Finish counting')
            ->visible(fn (AssetAudit $record): bool => $record->status === AssetAuditStatus::InProgress
                && (bool) Auth::user()?->can('update', $record))
            ->action(function (AssetAudit $record, Component $livewire): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(AssetAuditService::class)->finishCounting($record, $user);
                } catch (AssetAuditException $exception) {
                    Notification::make()->title('Counting not finished')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                $livewire->dispatch(LinesRelationManager::STATUS_CHANGED_EVENT);

                Notification::make()->title('Counting finished')->success()->send();
            });
    }
}
