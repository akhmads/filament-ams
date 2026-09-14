<?php

namespace App\Filament\Resources\LabelTemplates\Pages;

use App\Filament\Resources\LabelTemplates\LabelTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLabelTemplates extends ManageRecords
{
    protected static string $resource = LabelTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
