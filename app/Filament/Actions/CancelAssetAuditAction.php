<?php

namespace App\Filament\Actions;

use App\Exceptions\AssetAuditException;
use App\Filament\Resources\AssetAudits\RelationManagers\LinesRelationManager;
use App\Models\AssetAudit;
use App\Services\AssetAuditService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Abandons an audit that will not be finished. Nothing on the register changes.
 */
class CancelAssetAuditAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancelAssetAudit';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Cancel Audit')
            ->icon('heroicon-o-no-symbol')
            ->color('gray')
            ->modalHeading('Cancel Audit')
            ->modalSubmitActionLabel('Cancel audit')
            ->visible(fn (AssetAudit $record): bool => $record->status->isOpen()
                && (bool) Auth::user()?->can('update', $record))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (AssetAudit $record, array $data, Component $livewire): void {
                try {
                    app(AssetAuditService::class)->cancel($record, $data['reason']);
                } catch (AssetAuditException $exception) {
                    Notification::make()->title('Audit not cancelled')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                $livewire->dispatch(LinesRelationManager::STATUS_CHANGED_EVENT);

                Notification::make()->title('Audit cancelled')->success()->send();
            });
    }
}
