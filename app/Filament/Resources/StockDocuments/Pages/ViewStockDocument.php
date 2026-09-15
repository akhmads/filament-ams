<?php

namespace App\Filament\Resources\StockDocuments\Pages;

use App\Filament\Actions\PostStockDocumentAction;
use App\Filament\Resources\StockDocuments\StockDocumentResource;
use App\Models\StockDocument;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStockDocument extends ViewRecord
{
    protected static string $resource = StockDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PostStockDocumentAction::make(),
            EditAction::make()
                ->visible(fn (StockDocument $record): bool => $record->isEditable()),
            DeleteAction::make(),
        ];
    }
}
