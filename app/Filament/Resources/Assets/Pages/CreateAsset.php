<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Services\AssetCodeGenerator;
use App\Services\AssetMovementRecorder;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $category = AssetCategory::findOrFail($data['asset_category_id']);
        $branch = Branch::findOrFail($data['branch_id']);

        $data['code'] = app(AssetCodeGenerator::class)->generate(
            category: $category,
            branch: $branch,
            date: filled($data['acquisition_date'] ?? null) ? Carbon::parse($data['acquisition_date']) : null,
        );

        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        // The opening position goes into the ledger too, so the history is complete from day one.
        app(AssetMovementRecorder::class)->recordInitial($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
