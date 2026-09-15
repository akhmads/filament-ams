<?php

namespace App\Filament\Resources\ItemRequests;

use App\Enums\ItemRequestStatus;
use App\Filament\Resources\ItemRequests\Pages\CreateItemRequest;
use App\Filament\Resources\ItemRequests\Pages\EditItemRequest;
use App\Filament\Resources\ItemRequests\Pages\ListItemRequests;
use App\Filament\Resources\ItemRequests\Pages\ViewItemRequest;
use App\Filament\Resources\ItemRequests\RelationManagers\LinesRelationManager;
use App\Filament\Resources\ItemRequests\Schemas\ItemRequestForm;
use App\Filament\Resources\ItemRequests\Schemas\ItemRequestInfolist;
use App\Filament\Resources\ItemRequests\Tables\ItemRequestsTable;
use App\Models\ItemRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ItemRequestResource extends Resource
{
    protected static ?string $model = ItemRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Item Request';

    protected static ?string $pluralModelLabel = 'Item Requests';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getModel()::query()->where('status', ItemRequestStatus::Submitted->value)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Requests waiting for approval';
    }

    public static function form(Schema $schema): Schema
    {
        return ItemRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemRequestsTable::configure($table);
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
            'index' => ListItemRequests::route('/'),
            'create' => CreateItemRequest::route('/create'),
            'view' => ViewItemRequest::route('/{record}'),
            'edit' => EditItemRequest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['employee', 'department'])
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
