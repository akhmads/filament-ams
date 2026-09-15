<?php

namespace App\Filament\Resources\StockDocuments\Pages;

use App\Filament\Resources\StockDocuments\StockDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStockDocument extends EditRecord
{
    protected static string $resource = StockDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * A posted document is part of the stock card and stays as it was posted.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->record->isEditable(), 403, 'Only a draft stock document can be edited.');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
