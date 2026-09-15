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
 * Applies the chosen corrections to the register and closes the audit.
 */
class CloseAssetAuditAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'closeAssetAudit';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Apply & Close')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Apply Corrections and Close Audit')
            ->modalDescription(function (AssetAudit $record): string {
                $chosen = $record->lines()
                    ->where(fn ($query) => $query->where('apply_relocation', true)->orWhere('apply_condition', true)->orWhere('mark_lost', true))
                    ->count();

                return "{$chosen} asset(s) will be corrected in the register as audit adjustments. The audit can no longer change afterwards.";
            })
            ->modalSubmitActionLabel('Apply & close')
            ->visible(fn (AssetAudit $record): bool => $record->status === AssetAuditStatus::UnderReview
                && (bool) Auth::user()?->can('close', $record))
            ->action(function (AssetAudit $record, Component $livewire): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(AssetAuditService::class)->close($record, $user);
                } catch (AssetAuditException $exception) {
                    Notification::make()->title('Audit not closed')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                $livewire->dispatch(LinesRelationManager::STATUS_CHANGED_EVENT);

                Notification::make()->title('Audit closed')->success()->send();
            });
    }
}
