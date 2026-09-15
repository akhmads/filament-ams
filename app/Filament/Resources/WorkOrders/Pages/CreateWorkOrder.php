<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\User;
use App\Services\MaintenanceNotifier;
use App\Services\WorkOrderService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateWorkOrder extends CreateRecord
{
    protected static string $resource = WorkOrderResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = app(WorkOrderService::class)->nextNumber();
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        app(MaintenanceNotifier::class)->workOrderAssigned($this->record, $user);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
