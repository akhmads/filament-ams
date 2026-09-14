<?php

namespace App\Filament\Resources\AssetAssignments\Pages;

use App\Filament\Actions\CompleteAssignmentAction;
use App\Filament\Actions\PrintHandoverAction;
use App\Filament\Resources\AssetAssignments\AssetAssignmentResource;
use App\Models\AssetAssignment;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAssetAssignment extends ViewRecord
{
    protected static string $resource = AssetAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CompleteAssignmentAction::make(),
            PrintHandoverAction::make(),
            EditAction::make()
                ->visible(fn (AssetAssignment $record): bool => $record->isEditable()),
        ];
    }
}
