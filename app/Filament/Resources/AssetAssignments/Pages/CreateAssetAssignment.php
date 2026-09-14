<?php

namespace App\Filament\Resources\AssetAssignments\Pages;

use App\Enums\AssignmentType;
use App\Filament\Resources\AssetAssignments\AssetAssignmentResource;
use App\Services\DocumentNumberGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAssetAssignment extends CreateRecord
{
    protected static string $resource = AssetAssignmentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $prefix = match ($data['type']) {
            AssignmentType::Checkin->value => 'BAP',
            AssignmentType::Transfer->value => 'BAM',
            default => 'BAST',
        };

        $data['number'] = app(DocumentNumberGenerator::class)->document('assignment', $prefix);
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
