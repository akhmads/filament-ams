<?php

namespace App\Filament\Resources\StockDocuments\Pages;

use App\Enums\StockDocumentStatus;
use App\Enums\StockDocumentType;
use App\Filament\Resources\StockDocuments\StockDocumentResource;
use App\Models\StockDocument;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStockDocuments extends ListRecords
{
    protected static string $resource = StockDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Stock Document'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'drafts' => Tab::make('Drafts')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', StockDocumentStatus::Draft->value))
                ->badge(fn (): int => StockDocument::query()->where('status', StockDocumentStatus::Draft->value)->count()),
            'receipts' => Tab::make('Receipts')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', StockDocumentType::Receipt->value)),
            'issues' => Tab::make('Issues')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', StockDocumentType::Issue->value)),
            'transfers' => Tab::make('Transfers')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', StockDocumentType::Transfer->value)),
            'corrections' => Tab::make('Adjustments & Counts')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', [StockDocumentType::Adjustment->value, StockDocumentType::Count->value])),
        ];
    }
}
