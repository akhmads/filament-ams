<?php

namespace App\Filament\Resources\AssetDisposals\Pages;

use App\Enums\DisposalMethod;
use App\Enums\RepairTicketStatus;
use App\Filament\Resources\AssetDisposals\AssetDisposalResource;
use App\Models\RepairTicket;
use App\Models\User;
use App\Services\AssetDisposalService;
use App\Services\DisposalNotifier;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class CreateAssetDisposal extends CreateRecord
{
    protected static string $resource = AssetDisposalResource::class;

    /**
     * Set when the form is opened from an asset's page, which lists that asset.
     */
    #[Url]
    public ?string $asset = null;

    /**
     * Set when the form is opened from a repair that could not be done, which lists
     * its asset and carries over why.
     */
    #[Url]
    public ?string $ticket = null;

    protected function afterFill(): void
    {
        if (is_string($this->ticket) && ctype_digit($this->ticket)) {
            $ticket = RepairTicket::query()->find((int) $this->ticket);

            if ($ticket !== null && $ticket->status === RepairTicketStatus::Unrepairable) {
                $this->data['reason'] = "Cannot be repaired ({$ticket->number}): {$ticket->resolution}";
                $this->data['lines'] = [(string) Str::uuid() => $this->line($ticket->asset_id, $ticket->id)];
            }

            return;
        }

        if (is_string($this->asset) && ctype_digit($this->asset)) {
            $this->data['lines'] = [(string) Str::uuid() => $this->line((int) $this->asset)];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = app(AssetDisposalService::class)->nextNumber();
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        app(DisposalNotifier::class)->disposalProposed($this->record, $user);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * @return array<string, mixed>
     */
    private function line(int $assetId, ?int $repairTicketId = null): array
    {
        return [
            'asset_id' => $assetId,
            'method' => DisposalMethod::Scrapped->value,
            'proceeds' => 0,
            'notes' => null,
            'repair_ticket_id' => $repairTicketId,
        ];
    }
}
