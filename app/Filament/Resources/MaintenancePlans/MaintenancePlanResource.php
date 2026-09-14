<?php

namespace App\Filament\Resources\MaintenancePlans;

use App\Filament\Resources\MaintenancePlans\Pages\CreateMaintenancePlan;
use App\Filament\Resources\MaintenancePlans\Pages\EditMaintenancePlan;
use App\Filament\Resources\MaintenancePlans\Pages\ListMaintenancePlans;
use App\Filament\Resources\MaintenancePlans\Schemas\MaintenancePlanForm;
use App\Filament\Resources\MaintenancePlans\Tables\MaintenancePlansTable;
use App\Models\MaintenancePlan;
use App\Services\MaintenanceScheduler;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Preventive maintenance plans. Work orders are opened from them by
 * {@see MaintenanceScheduler}.
 */
class MaintenancePlanResource extends Resource
{
    protected static ?string $model = MaintenancePlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Maintenance';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Maintenance Plan';

    protected static ?string $pluralModelLabel = 'Maintenance Plans';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MaintenancePlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenancePlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenancePlans::route('/'),
            'create' => CreateMaintenancePlan::route('/create'),
            'edit' => EditMaintenancePlan::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['asset', 'category', 'assignedTo'])
            ->withCount(['workOrders as open_work_orders_count' => fn (Builder $query) => $query->open()]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
