<?php

namespace App\Filament\Resources\DepreciationPeriods\Pages;

use App\Filament\Actions\CalculateDepreciationAction;
use App\Filament\Resources\DepreciationPeriods\DepreciationPeriodResource;
use Filament\Resources\Pages\ListRecords;

class ListDepreciationPeriods extends ListRecords
{
    protected static string $resource = DepreciationPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CalculateDepreciationAction::make(),
        ];
    }
}
