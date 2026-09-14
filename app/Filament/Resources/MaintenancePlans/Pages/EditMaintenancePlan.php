<?php

namespace App\Filament\Resources\MaintenancePlans\Pages;

use App\Filament\Resources\MaintenancePlans\MaintenancePlanResource;
use App\Filament\Resources\MaintenancePlans\Schemas\MaintenancePlanForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenancePlan extends EditRecord
{
    protected static string $resource = MaintenancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return MaintenancePlanForm::normalizeTarget($data);
    }
}
