<?php

namespace App\Filament\Resources\Assets\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Imports\AssetImport;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(AssetImport::class)
                ->icon('heroicon-m-arrow-up-tray'),
            CreateAction::make()
                ->icon('heroicon-m-plus')
        ];
    }
}
