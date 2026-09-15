<?php

namespace App\Filament\Resources\AssetAudits;

use App\Enums\AssetAuditStatus;
use App\Filament\Resources\AssetAudits\Pages\CreateAssetAudit;
use App\Filament\Resources\AssetAudits\Pages\ListAssetAudits;
use App\Filament\Resources\AssetAudits\Pages\ScanAssetAudit;
use App\Filament\Resources\AssetAudits\Pages\ViewAssetAudit;
use App\Filament\Resources\AssetAudits\RelationManagers\LinesRelationManager;
use App\Filament\Resources\AssetAudits\Schemas\AssetAuditForm;
use App\Filament\Resources\AssetAudits\Schemas\AssetAuditInfolist;
use App\Filament\Resources\AssetAudits\Tables\AssetAuditsTable;
use App\Models\AssetAudit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AssetAuditResource extends Resource
{
    protected static ?string $model = AssetAudit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'Assets';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'Asset Audit';

    protected static ?string $pluralModelLabel = 'Asset Audits';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $underReview = static::getModel()::query()->where('status', AssetAuditStatus::UnderReview->value)->count();

        return $underReview > 0 ? (string) $underReview : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Audits waiting to be reviewed and closed';
    }

    public static function form(Schema $schema): Schema
    {
        return AssetAuditForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssetAuditInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetAuditsTable::configure($table);
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
            'index' => ListAssetAudits::route('/'),
            'create' => CreateAssetAudit::route('/create'),
            'view' => ViewAssetAudit::route('/{record}'),
            'scan' => ScanAssetAudit::route('/{record}/scan'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['location', 'department'])
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
