<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Concerns\HasNumberExportColumns;
use App\Filament\Imports\AssetImporter;
use App\Models\Asset;
use BackedEnum;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * The asset register as a file. Headers are the importer's column names and
 * masters are written as the codes it looks up {@see AssetImporter}, so an export
 * with the code column cleared is a ready-made import template. Readable names
 * ride along in columns that are off by default.
 */
class AssetExporter extends Exporter
{
    use HasNumberExportColumns;

    protected static ?string $model = Asset::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['category', 'branch', 'currentLocation', 'currentEmployee', 'department', 'brand', 'assetModel', 'supplier']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('code')->label('code'),
            ExportColumn::make('name')->label('name'),
            ExportColumn::make('category.code')->label('category'),
            ExportColumn::make('category.name')->label('category_name')->enabledByDefault(false),
            ExportColumn::make('branch.code')->label('branch'),
            ExportColumn::make('currentLocation.code')->label('location'),
            ExportColumn::make('currentEmployee.employee_number')->label('employee'),
            ExportColumn::make('current_holder')->label('position')->enabledByDefault(false),
            ExportColumn::make('department.code')->label('department'),
            ExportColumn::make('brand.name')->label('brand'),
            ExportColumn::make('assetModel.name')->label('model'),
            ExportColumn::make('serial_number')->label('serial_number'),
            ExportColumn::make('manufacture_year')->label('manufacture_year'),
            static::enumColumn('status'),
            static::enumColumn('condition'),
            static::dateColumn('acquisition_date'),
            NumberExportColumn::make('acquisition_cost')->label('acquisition_cost'),
            ExportColumn::make('supplier.code')->label('supplier'),
            ExportColumn::make('po_number')->label('po_number'),
            ExportColumn::make('invoice_number')->label('invoice_number'),
            ExportColumn::make('funding_source')->label('funding_source'),
            ExportColumn::make('is_depreciable')->label('is_depreciable')
                ->formatStateUsing(fn (mixed $state): string => $state ? '1' : '0'),
            static::enumColumn('depreciation_method'),
            ExportColumn::make('useful_life_months')->label('useful_life_months'),
            NumberExportColumn::make('residual_value')->label('residual_value'),
            static::dateColumn('depreciation_start_date'),
            static::enumColumn('fiscal_group'),
            static::enumColumn('fiscal_method'),
            static::dateColumn('warranty_start'),
            static::dateColumn('warranty_end'),
            ExportColumn::make('warranty_vendor')->label('warranty_vendor'),
            ExportColumn::make('notes')->label('notes'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Asset export finished: '.Number::format($export->successful_rows).' asset(s) exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' row(s) failed.';
        }

        return $body;
    }

    /**
     * An enum written as its value, which the importer reads back.
     */
    private static function enumColumn(string $name): ExportColumn
    {
        return ExportColumn::make($name)
            ->label($name)
            ->formatStateUsing(fn (mixed $state): ?string => $state instanceof BackedEnum ? (string) $state->value : $state);
    }

    /**
     * A date as YYYY-MM-DD, the one spelling the importer reads.
     */
    private static function dateColumn(string $name): ExportColumn
    {
        return ExportColumn::make($name)
            ->label($name)
            ->formatStateUsing(fn (?Carbon $state): ?string => $state?->format('Y-m-d'));
    }
}
