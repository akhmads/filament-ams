<?php

namespace App\Filament\Resources\AssetDisposals\Pages;

use App\Filament\Resources\AssetDisposals\AssetDisposalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAssetDisposal extends EditRecord
{
    protected static string $resource = AssetDisposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Once someone has decided on the proposal, what was proposed is on record.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->record->isEditable(), 403, 'Only a disposal waiting for approval can be edited.');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
