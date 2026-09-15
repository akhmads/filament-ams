<?php

namespace App\Filament\Resources\AssetDisposals;

use App\Enums\AssetDisposalStatus;
use App\Filament\Resources\AssetDisposals\Pages\CreateAssetDisposal;
use App\Filament\Resources\AssetDisposals\Pages\EditAssetDisposal;
use App\Filament\Resources\AssetDisposals\Pages\ListAssetDisposals;
use App\Filament\Resources\AssetDisposals\Pages\ViewAssetDisposal;
use App\Filament\Resources\AssetDisposals\RelationManagers\LinesRelationManager;
use App\Filament\Resources\AssetDisposals\Schemas\AssetDisposalForm;
use App\Filament\Resources\AssetDisposals\Schemas\AssetDisposalInfolist;
use App\Filament\Resources\AssetDisposals\Tables\AssetDisposalsTable;
use App\Models\AssetDisposal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AssetDisposalResource extends Resource
{
    protected static ?string $model = AssetDisposal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxXMark;

    protected static string|UnitEnum|null $navigationGroup = 'Assets';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Disposal';

    protected static ?string $pluralModelLabel = 'Disposals';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getModel()::query()->where('status', AssetDisposalStatus::Proposed->value)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Disposals waiting for approval';
    }

    public static function form(Schema $schema): Schema
    {
        return AssetDisposalForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssetDisposalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetDisposalsTable::configure($table);
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
            'index' => ListAssetDisposals::route('/'),
            'create' => CreateAssetDisposal::route('/create'),
            'view' => ViewAssetDisposal::route('/{record}'),
            'edit' => EditAssetDisposal::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('lines');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
