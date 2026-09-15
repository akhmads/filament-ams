<?php

namespace App\Filament\Resources\StockDocuments\Pages;

use App\Enums\StockDocumentType;
use App\Filament\Resources\StockDocuments\StockDocumentResource;
use App\Services\StockDocumentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateStockDocument extends CreateRecord
{
    protected static string $resource = StockDocumentResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = $data['type'] instanceof StockDocumentType ? $data['type'] : StockDocumentType::from($data['type']);

        $data['number'] = app(StockDocumentService::class)->nextNumber($type);
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
