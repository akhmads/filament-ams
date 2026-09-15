<?php

namespace App\Filament\Resources\StockItems\RelationManagers;

use App\Models\StockBalance;
use App\Support\Quantity;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stock on hand per warehouse, with its moving average cost.
 */
class BalancesRelationManager extends RelationManager
{
    protected static string $relationship = 'balances';

    protected static ?string $title = 'Per Warehouse';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Stock per Warehouse')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['location', 'stockItem']))
            ->defaultSort('location_id')
            ->emptyStateHeading('Never stocked')
            ->emptyStateDescription('Post a goods receipt to bring this item into a warehouse.')
            ->columns([
                TextColumn::make('location.name')->label('Warehouse'),
                TextColumn::make('quantity')->label('On Hand')->alignEnd()
                    ->formatStateUsing(fn (StockBalance $record): string => Quantity::format($record->quantity).' '.$record->stockItem->unit),
                TextColumn::make('average_cost')->label('Average Cost')->alignEnd()
                    ->state(fn (StockBalance $record): float => (float) $record->averageCost())
                    ->money('IDR'),
                TextColumn::make('total_value')->label('Stock Value')->alignEnd()->money('IDR')
                    ->summarize(Sum::make()->money('IDR')->label('Total')),
            ])
            ->paginated(false);
    }
}
