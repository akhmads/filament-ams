<?php

namespace App\Filament\Resources\ItemRequests\Pages;

use App\Filament\Resources\ItemRequests\ItemRequestResource;
use App\Models\User;
use App\Services\ItemRequestService;
use App\Services\StockNotifier;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateItemRequest extends CreateRecord
{
    protected static string $resource = ItemRequestResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = app(ItemRequestService::class)->nextNumber();
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        app(StockNotifier::class)->itemRequestSubmitted($this->record, $user);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
