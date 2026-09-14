<?php

namespace App\Filament\Resources\AssetAssignments\Pages;

use App\Filament\Actions\CompleteAssignmentAction;
use App\Filament\Resources\AssetAssignments\AssetAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAssetAssignment extends EditRecord
{
    protected static string $resource = AssetAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CompleteAssignmentAction::make(),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * A completed document may no longer be edited.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->record->isEditable(), 403, 'A completed document can no longer be edited.');
    }
}
