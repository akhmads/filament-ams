<?php

namespace App\Filament\Actions;

use App\Enums\ItemRequestStatus;
use App\Exceptions\ItemRequestException;
use App\Models\ItemRequest;
use App\Models\Location;
use App\Models\StockDocument;
use App\Models\User;
use App\Services\ItemRequestService;
use App\Support\WarehouseOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Issues an approved request's items from one warehouse and posts the issue.
 */
class IssueItemRequestAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'issueItemRequest';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Issue Items')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->modalHeading('Issue Requested Items')
            ->modalDescription('Every item on the request leaves the chosen warehouse at once. If the warehouse cannot cover the whole request, nothing is issued.')
            ->modalSubmitActionLabel('Issue items')
            ->visible(fn (ItemRequest $record): bool => $record->status === ItemRequestStatus::Approved
                && (bool) Auth::user()?->can('update', $record)
                && (bool) Auth::user()?->can('create', StockDocument::class))
            ->schema([
                Select::make('location_id')
                    ->label('Warehouse')
                    ->options(fn (): array => WarehouseOptions::all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (ItemRequest $record, array $data, Action $action): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(ItemRequestService::class)->fulfill($record, Location::query()->findOrFail($data['location_id']), $user);
                } catch (ItemRequestException $exception) {
                    Notification::make()->title('Items not issued')->body($exception->getMessage())->danger()->persistent()->send();

                    $action->halt();
                }

                Notification::make()->title('Items issued')->success()->send();
            });
    }
}
