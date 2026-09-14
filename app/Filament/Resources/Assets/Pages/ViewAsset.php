<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Actions\PrintAssetLabelsAction;
use App\Filament\Resources\Assets\AssetResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    public function getSubheading(): ?string
    {
        return $this->record->current_holder;
    }

    protected function getHeaderActions(): array
    {
        return [
            PrintAssetLabelsAction::make(),
            EditAction::make(),
        ];
    }
}
