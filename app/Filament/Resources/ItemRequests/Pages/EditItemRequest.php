<?php

namespace App\Filament\Resources\ItemRequests\Pages;

use App\Filament\Resources\ItemRequests\ItemRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItemRequest extends EditRecord
{
    protected static string $resource = ItemRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Once someone has decided on the request, what was asked for is on record.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->record->isEditable(), 403, 'Only a request waiting for approval can be edited.');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
