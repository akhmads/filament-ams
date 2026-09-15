<?php

namespace App\Filament\Resources\StockItems\RelationManagers;

use App\Enums\StockMovementType;
use App\Filament\Resources\StockDocuments\StockDocumentResource;
use App\Models\StockMovement;
use App\Support\Quantity;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The stock card: every posted change to the item's stock, newest first, with the
 * warehouse's running balance after each one.
 */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Stock Card';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Stock Card')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['stockDocument', 'location']))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No stock movements yet')
            ->columns([
                TextColumn::make('moved_on')->label('Date')->date('d M Y'),
                TextColumn::make('stockDocument.number')->label('Document')->badge()->color('gray')
                    ->url(fn (StockMovement $record): string => StockDocumentResource::getUrl('view', ['record' => $record->stock_document_id])),
                TextColumn::make('movement_type')->label('Type')->badge(),
                TextColumn::make('location.name')->label('Warehouse'),
                TextColumn::make('quantity')->label('In / Out')->alignEnd()
                    ->formatStateUsing(fn (string $state): string => ((float) $state > 0 ? '+' : '').Quantity::format($state))
                    ->color(fn (string $state): string => (float) $state < 0 ? 'danger' : 'success'),
                TextColumn::make('value')->label('Value')->alignEnd()->money('IDR'),
                TextColumn::make('balance_quantity')->label('Balance')->alignEnd()
                    ->formatStateUsing(fn (string $state): string => Quantity::format($state)),
                TextColumn::make('balance_value')->label('Balance Value')->alignEnd()->money('IDR')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('location_id')
                    ->label('Warehouse')
                    ->relationship('location', 'name'),
                SelectFilter::make('movement_type')
                    ->label('Type')
                    ->options(StockMovementType::class),
            ])
            ->paginated([10, 25, 50]);
    }
}
