<?php

namespace App\Filament\Resources\AssetAudits\Pages;

use App\Filament\Resources\AssetAudits\AssetAuditResource;
use App\Services\AssetAuditService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAssetAudit extends CreateRecord
{
    protected static string $resource = AssetAuditResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = app(AssetAuditService::class)->nextNumber();
        $data['created_by'] = Auth::id();
        $data['started_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(AssetAuditService::class)->listExpectedAssets($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
