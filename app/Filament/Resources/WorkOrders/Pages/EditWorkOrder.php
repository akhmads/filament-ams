<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\User;
use App\Services\MaintenanceNotifier;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditWorkOrder extends EditRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Once work has started the details and checklist are part of the record.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->record->isEditable(), 403, 'Only an open work order can be edited.');
    }

    protected function afterSave(): void
    {
        if (! $this->record->wasChanged('assigned_to')) {
            return;
        }

        /** @var User|null $user */
        $user = Auth::user();

        app(MaintenanceNotifier::class)->workOrderAssigned($this->record, $user);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
