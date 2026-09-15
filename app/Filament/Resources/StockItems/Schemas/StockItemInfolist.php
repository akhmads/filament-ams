<?php

namespace App\Filament\Resources\StockItems\Schemas;

use App\Models\StockItem;
use App\Support\Quantity;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Stock Item')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('code')->label('Code')->badge()->copyable(),
                            TextEntry::make('name')->label('Name')->columnSpan(2),
                            TextEntry::make('item_type')->label('Type')->badge(),
                            TextEntry::make('on_hand_quantity')->label('On Hand')
                                ->state(fn (StockItem $record): string => Quantity::format($record->on_hand_quantity).' '.$record->unit)
                                ->color(fn (StockItem $record): ?string => self::isBelowMinimum($record) ? 'danger' : null)
                                ->belowContent(fn (StockItem $record): ?string => self::isBelowMinimum($record) ? 'Below minimum stock' : null),
                            TextEntry::make('on_hand_value')->label('Stock Value')
                                ->state(fn (StockItem $record): float => (float) $record->on_hand_value)
                                ->money('IDR'),
                            TextEntry::make('minimum_quantity')->label('Minimum Stock')
                                ->formatStateUsing(fn (StockItem $record): string => Quantity::format($record->minimum_quantity).' '.$record->unit),
                            TextEntry::make('reorder_quantity')->label('Reorder Quantity')->placeholder('—')
                                ->formatStateUsing(fn (StockItem $record): string => Quantity::format($record->reorder_quantity).' '.$record->unit),
                            IconEntry::make('is_active')->label('Active')->boolean(),
                            TextEntry::make('description')->label('Description')->placeholder('—')->columnSpan(3),
                        ]),
                    ]),
            ]);
    }

    private static function isBelowMinimum(StockItem $record): bool
    {
        return (float) $record->minimum_quantity > 0
            && (float) $record->on_hand_quantity < (float) $record->minimum_quantity;
    }
}
