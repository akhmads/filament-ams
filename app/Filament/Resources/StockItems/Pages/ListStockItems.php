<?php

namespace App\Filament\Resources\StockItems\Pages;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\StockItem;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStockItems extends ListRecords
{
    protected static string $resource = StockItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Stock Item'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'below_minimum' => Tab::make('Below Minimum')
                ->modifyQueryUsing(fn (Builder $query) => $query->belowMinimum())
                ->badge(fn (): int => StockItem::query()->active()->belowMinimum()->count())
                ->badgeColor('danger'),
        ];
    }
}
