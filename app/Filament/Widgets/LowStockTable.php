<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\StockItem;
use App\Support\Quantity;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Stock items that have fallen below their minimum, with what to buy — the
 * purchase suggestion list.
 */
class LowStockTable extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) Auth::user()?->can('viewAny', StockItem::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low Stock')
            ->description('Items below their minimum stock across all warehouses.')
            ->emptyStateHeading('No items are below their minimum stock')
            ->query(fn (): Builder => StockItem::query()
                ->active()
                ->withStockOnHand()
                ->belowMinimum()
                ->orderBy('code'))
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('name')
                    ->label('Item')
                    ->wrap(),
                TextColumn::make('on_hand_quantity')
                    ->label('On Hand')
                    ->state(fn (StockItem $record): string => Quantity::format($record->on_hand_quantity).' '.$record->unit)
                    ->color('danger')
                    ->alignEnd(),
                TextColumn::make('minimum_quantity')
                    ->label('Minimum')
                    ->formatStateUsing(fn (StockItem $record): string => Quantity::format($record->minimum_quantity).' '.$record->unit)
                    ->alignEnd(),
                TextColumn::make('suggested_reorder')
                    ->label('Suggested Purchase')
                    ->state(fn (StockItem $record): string => Quantity::format($record->suggestedReorderQuantity($record->on_hand_quantity)).' '.$record->unit)
                    ->alignEnd(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('open')
                        ->label('Open')
                        ->icon('heroicon-m-arrow-top-right-on-square')
                        ->url(fn (StockItem $record): string => StockItemResource::getUrl('view', ['record' => $record])),
                ]),
            ])
            ->paginated([5, 10, 25]);
    }
}
