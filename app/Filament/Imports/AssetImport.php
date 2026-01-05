<?php

namespace App\Filament\Imports;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Brand;
use App\Enums\Condition;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;

class AssetImport extends Importer
{
    protected static ?string $model = Asset::class;

    public function resolveRecord(): ?\Illuminate\Database\Eloquent\Model
    {
        // Find or create category
        $category = Category::firstOrCreate(['name' => $this->data['category_name']]);

        // Find or create brand
        $brand = Brand::firstOrCreate(['name' => $this->data['brand_name']]);

        return Asset::updateOrCreate(
            ['code' => $this->data['code']],
            [
                'name' => $this->data['name'],
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'model' => $this->data['model'] ?? null,
                'serial_number' => $this->data['serial_number'] ?? null,
                'description' => $this->data['description'] ?? null,
                'condition' => Condition::tryFrom($this->data['condition']) ?? Condition::Good,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->requiredMapping()
                ->rules(['required', 'string', 'unique:assets,code']),
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'string']),
            ImportColumn::make('category_name')
                ->requiredMapping()
                ->rules(['required', 'string']),
            ImportColumn::make('brand_name')
                ->requiredMapping()
                ->rules(['required', 'string']),
            ImportColumn::make('model')
                ->rules(['nullable', 'string']),
            ImportColumn::make('serial_number')
                ->rules(['nullable', 'string']),
            ImportColumn::make('description')
                ->rules(['nullable', 'string']),
            ImportColumn::make('condition')
                ->rules(['nullable', 'in:good,damage']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your asset import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
