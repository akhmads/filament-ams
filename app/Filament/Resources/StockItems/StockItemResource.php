<?php

namespace App\Filament\Resources\StockItems;

use App\Filament\Resources\StockItems\Pages\CreateStockItem;
use App\Filament\Resources\StockItems\Pages\EditStockItem;
use App\Filament\Resources\StockItems\Pages\ListStockItems;
use App\Filament\Resources\StockItems\Pages\ViewStockItem;
use App\Filament\Resources\StockItems\RelationManagers\BalancesRelationManager;
use App\Filament\Resources\StockItems\RelationManagers\MovementsRelationManager;
use App\Filament\Resources\StockItems\Schemas\StockItemForm;
use App\Filament\Resources\StockItems\Schemas\StockItemInfolist;
use App\Filament\Resources\StockItems\Tables\StockItemsTable;
use App\Models\StockItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class StockItemResource extends Resource
{
    protected static ?string $model = StockItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Stock Item';

    protected static ?string $pluralModelLabel = 'Stock Items';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $belowMinimum = static::getModel()::query()->active()->belowMinimum()->count();

        return $belowMinimum > 0 ? (string) $belowMinimum : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Items below their minimum stock';
    }

    public static function form(Schema $schema): Schema
    {
        return StockItemForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockItemInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BalancesRelationManager::class,
            MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockItems::route('/'),
            'create' => CreateStockItem::route('/create'),
            'view' => ViewStockItem::route('/{record}'),
            'edit' => EditStockItem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withStockOnHand();
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
