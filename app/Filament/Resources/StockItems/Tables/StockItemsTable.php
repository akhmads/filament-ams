<?php

namespace App\Filament\Resources\StockItems\Tables;

use App\Enums\StockItemType;
use App\Models\StockItem;
use App\Support\Quantity;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StockItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->emptyStateHeading('No stock items')
            ->emptyStateDescription('Add the consumables and spare parts you keep in your warehouses.')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('item_type')
                    ->label('Type')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('on_hand_quantity')
                    ->label('On Hand')
                    ->formatStateUsing(fn (StockItem $record): string => Quantity::format($record->on_hand_quantity).' '.$record->unit)
                    ->placeholder(fn (StockItem $record): string => '0 '.$record->unit)
                    ->color(fn (StockItem $record): ?string => (float) $record->minimum_quantity > 0
                        && (float) $record->on_hand_quantity < (float) $record->minimum_quantity ? 'danger' : null)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('minimum_quantity')
                    ->label('Minimum')
                    ->formatStateUsing(fn (StockItem $record): string => Quantity::format($record->minimum_quantity))
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('on_hand_value')
                    ->label('Stock Value')
                    ->money('IDR')
                    ->placeholder('Rp0.00')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('item_type')
                    ->label('Type')
                    ->options(StockItemType::class),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
