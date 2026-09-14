<?php

namespace App\Filament\Resources\MaintenancePlans\Pages;

use App\Filament\Resources\MaintenancePlans\MaintenancePlanResource;
use App\Filament\Resources\MaintenancePlans\Schemas\MaintenancePlanForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateMaintenancePlan extends CreateRecord
{
    protected static string $resource = MaintenancePlanResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...MaintenancePlanForm::normalizeTarget($data),
            'created_by' => Auth::id(),
        ];
    }
}
