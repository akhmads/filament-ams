<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Enums\AssetStatus;
use App\Filament\Resources\Assets\AssetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
