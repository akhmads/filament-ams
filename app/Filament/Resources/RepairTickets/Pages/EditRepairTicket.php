<?php

namespace App\Filament\Resources\RepairTickets\Pages;

use App\Filament\Resources\RepairTickets\RepairTicketResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRepairTicket extends EditRecord
{
    protected static string $resource = RepairTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Once a repair is approved the report is what was signed off.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->record->isEditable(), 403, 'Only a ticket that has not been approved can be edited.');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
