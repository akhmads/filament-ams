<?php

namespace App\Filament\Resources\DepreciationPeriods\Pages;

use App\Filament\Actions\PostDepreciationAction;
use App\Filament\Actions\RecalculateDepreciationAction;
use App\Filament\Resources\DepreciationPeriods\DepreciationPeriodResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDepreciationPeriod extends ViewRecord
{
    protected static string $resource = DepreciationPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecalculateDepreciationAction::make(),
            PostDepreciationAction::make(),
        ];
    }
}
