<?php

namespace App\Filament\Resources\StockDocuments\RelationManagers;

use App\Enums\StockDocumentType;
use App\Filament\Actions\PostStockDocumentAction;
use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Support\Quantity;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

/**
 * The items on a stock document. Lines are written on the document form; the
 * posted value appears here once the document is posted.
 */
class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Items';

    public function isReadOnly(): bool
    {
        return true;
    }

    #[On(PostStockDocumentAction::POSTED_EVENT)]
    public function refreshDocument(): void
    {
        $this->getOwnerRecord()->refresh();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Items')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('stockItem'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('stockItem.code')->label('Code')->badge()->color('gray')
                    ->url(fn (StockDocumentLine $record): string => StockItemResource::getUrl('view', ['record' => $record->stock_item_id])),
                TextColumn::make('stockItem.name')->label('Item')->wrap(),
                TextColumn::make('system_quantity')->label('On Hand at Posting')->alignEnd()->placeholder('—')
                    ->formatStateUsing(fn (StockDocumentLine $record): string => Quantity::format($record->system_quantity).' '.$record->stockItem->unit)
                    ->visible(fn (): bool => $this->documentType() === StockDocumentType::Count),
                TextColumn::make('quantity')
                    ->label(fn (): string => $this->documentType()->quantityLabel())
                    ->alignEnd()
                    ->formatStateUsing(fn (StockDocumentLine $record): string => Quantity::format($record->quantity).' '.$record->stockItem->unit),
                TextColumn::make('unit_cost')->label('Unit Cost')->alignEnd()->money('IDR')
                    ->visible(fn (): bool => $this->documentType() === StockDocumentType::Receipt),
                TextColumn::make('posted_value')->label('Value')->alignEnd()->money('IDR')->placeholder('When posted')
                    ->summarize(Sum::make()->money('IDR')->label('Total')),
                TextColumn::make('notes')->label('Notes')->placeholder('—')->wrap(),
            ])
            ->paginated(false);
    }

    private function documentType(): StockDocumentType
    {
        /** @var StockDocument $document */
        $document = $this->getOwnerRecord();

        return $document->type;
    }
}
