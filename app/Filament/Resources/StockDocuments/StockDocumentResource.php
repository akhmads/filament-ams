<?php

namespace App\Filament\Resources\StockDocuments;

use App\Enums\StockDocumentStatus;
use App\Filament\Resources\StockDocuments\Pages\CreateStockDocument;
use App\Filament\Resources\StockDocuments\Pages\EditStockDocument;
use App\Filament\Resources\StockDocuments\Pages\ListStockDocuments;
use App\Filament\Resources\StockDocuments\Pages\ViewStockDocument;
use App\Filament\Resources\StockDocuments\RelationManagers\LinesRelationManager;
use App\Filament\Resources\StockDocuments\Schemas\StockDocumentForm;
use App\Filament\Resources\StockDocuments\Schemas\StockDocumentInfolist;
use App\Filament\Resources\StockDocuments\Tables\StockDocumentsTable;
use App\Models\StockDocument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class StockDocumentResource extends Resource
{
    protected static ?string $model = StockDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Stock Document';

    protected static ?string $pluralModelLabel = 'Stock Documents';

    protected static ?string $navigationLabel = 'Stock Transactions';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $drafts = static::getModel()::query()->where('status', StockDocumentStatus::Draft->value)->count();

        return $drafts > 0 ? (string) $drafts : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Drafts waiting to be posted';
    }

    public static function form(Schema $schema): Schema
    {
        return StockDocumentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockDocumentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockDocumentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockDocuments::route('/'),
            'create' => CreateStockDocument::route('/create'),
            'view' => ViewStockDocument::route('/{record}'),
            'edit' => EditStockDocument::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['location', 'destinationLocation'])
            ->withCount('lines');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
