<?php

namespace App\Filament\Resources\DepreciationPeriods;

use App\Filament\Resources\DepreciationPeriods\Pages\ListDepreciationPeriods;
use App\Filament\Resources\DepreciationPeriods\Pages\ViewDepreciationPeriod;
use App\Filament\Resources\DepreciationPeriods\RelationManagers\EntriesRelationManager;
use App\Filament\Resources\DepreciationPeriods\Schemas\DepreciationPeriodInfolist;
use App\Filament\Resources\DepreciationPeriods\Tables\DepreciationPeriodsTable;
use App\Models\DepreciationPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Monthly depreciation per book. Periods are created and posted through the
 * depreciation runner only, never typed in, so there are no create or edit pages.
 */
class DepreciationPeriodResource extends Resource
{
    protected static ?string $model = DepreciationPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Depreciation';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Depreciation Period';

    protected static ?string $pluralModelLabel = 'Depreciation Periods';

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof DepreciationPeriod
            ? "{$record->book->getLabel()} — {$record->period_label}"
            : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return DepreciationPeriodInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepreciationPeriodsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            EntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepreciationPeriods::route('/'),
            'view' => ViewDepreciationPeriod::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('postedBy');
    }
}
