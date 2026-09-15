<?php

namespace App\Filament\Resources\RepairTickets\Pages;

use App\Exceptions\RepairTicketException;
use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Models\Asset;
use App\Models\User;
use App\Services\RepairTicketService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class CreateRepairTicket extends CreateRecord
{
    protected static string $resource = RepairTicketResource::class;

    /**
     * Set when the form is opened from an asset's page, which picks that asset.
     */
    #[Url]
    public ?string $asset = null;

    protected function afterFill(): void
    {
        if (is_string($this->asset) && ctype_digit($this->asset)) {
            $this->data['asset_id'] = (int) $this->asset;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            return app(RepairTicketService::class)->report(Asset::query()->findOrFail($data['asset_id']), $data, $user);
        } catch (RepairTicketException $exception) {
            Notification::make()->title('Repair ticket not created')->body($exception->getMessage())->danger()->persistent()->send();

            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
