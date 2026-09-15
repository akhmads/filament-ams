<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Enums\AssetStatus;
use App\Filament\Actions\ImportAction;
use App\Filament\Exports\AssetExporter;
use App\Filament\Imports\AssetImporter;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Import')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->importer(AssetImporter::class)
                // Registering assets from a file is the create power, in quantity.
                ->visible(fn (): bool => (bool) auth()->user()?->can('create', Asset::class)),
            ExportAction::make()
                ->label('Export')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->exporter(AssetExporter::class),
            CreateAction::make()->label('New Asset'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('All')
                ->badge(fn (): int => AssetResource::getModel()::query()->count()),
            'tersedia' => Tab::make('Available')
                ->modifyQueryUsing(fn (Builder $query) => $query->assignable())
                ->badge(fn (): int => AssetResource::getModel()::query()->assignable()->count()),
            'digunakan' => Tab::make('In Use')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AssetStatus::InUse->value)),
            'perawatan' => Tab::make('Maintenance & Repair')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    AssetStatus::UnderMaintenance->value,
                    AssetStatus::UnderRepair->value,
                ])),
            'nonaktif' => Tab::make('Lost & Disposed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    AssetStatus::Lost->value,
                    AssetStatus::Disposed->value,
                    AssetStatus::Retired->value,
                ])),
        ];
    }
}
