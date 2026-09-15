<?php

namespace App\Filament\Actions;

use App\Exceptions\ItemRequestException;
use App\Models\ItemRequest;
use App\Services\ItemRequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Withdraws a request that is no longer needed, before its goods are issued.
 */
class CancelItemRequestAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'cancelItemRequest';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Cancel Request')
            ->icon('heroicon-o-no-symbol')
            ->color('gray')
            ->modalHeading('Cancel Item Request')
            ->modalSubmitActionLabel('Cancel request')
            ->visible(fn (ItemRequest $record): bool => $record->status->isOpen()
                && (bool) Auth::user()?->can('update', $record))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (ItemRequest $record, array $data): void {
                try {
                    app(ItemRequestService::class)->cancel($record, $data['reason']);
                } catch (ItemRequestException $exception) {
                    Notification::make()->title('Item request not cancelled')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Item request cancelled')->success()->send();
            });
    }
}
