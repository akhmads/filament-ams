<?php

namespace App\Filament\Resources\AssetAssignments;

use App\Filament\Resources\AssetAssignments\Pages\CreateAssetAssignment;
use App\Filament\Resources\AssetAssignments\Pages\EditAssetAssignment;
use App\Filament\Resources\AssetAssignments\Pages\ListAssetAssignments;
use App\Filament\Resources\AssetAssignments\Pages\ViewAssetAssignment;
use App\Filament\Resources\AssetAssignments\Schemas\AssetAssignmentForm;
use App\Filament\Resources\AssetAssignments\Schemas\AssetAssignmentInfolist;
use App\Filament\Resources\AssetAssignments\Tables\AssetAssignmentsTable;
use App\Models\AssetAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AssetAssignmentResource extends Resource
{
    protected static ?string $model = AssetAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Assets';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Handover';

    protected static ?string $pluralModelLabel = 'Handovers';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $overdue = static::getModel()::query()->overdue()->count();

        return $overdue > 0 ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Loans past their due date';
    }

    public static function form(Schema $schema): Schema
    {
        return AssetAssignmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssetAssignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetAssignmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetAssignments::route('/'),
            'create' => CreateAssetAssignment::route('/create'),
            'view' => ViewAssetAssignment::route('/{record}'),
            'edit' => EditAssetAssignment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['branch', 'toEmployee', 'toLocation'])
            ->withCount('items');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
