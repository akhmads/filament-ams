<?php

namespace App\Filament\Resources\ItemRequests\RelationManagers;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\ItemRequestLine;
use App\Support\Quantity;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The items asked for. Lines are written on the request form.
 */
class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Items';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Items')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('stockItem'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('stockItem.code')->label('Code')->badge()->color('gray')
                    ->url(fn (ItemRequestLine $record): string => StockItemResource::getUrl('view', ['record' => $record->stock_item_id])),
                TextColumn::make('stockItem.name')->label('Item')->wrap(),
                TextColumn::make('quantity')->label('Quantity')->alignEnd()
                    ->formatStateUsing(fn (ItemRequestLine $record): string => Quantity::format($record->quantity).' '.$record->stockItem->unit),
                TextColumn::make('notes')->label('Notes')->placeholder('—')->wrap(),
            ])
            ->paginated(false);
    }
}
