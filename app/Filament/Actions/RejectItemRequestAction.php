<?php

namespace App\Filament\Actions;

use App\Enums\ItemRequestStatus;
use App\Exceptions\ItemRequestException;
use App\Models\ItemRequest;
use App\Models\User;
use App\Services\ItemRequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Turns an item request down, keeping the reason on record.
 */
class RejectItemRequestAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'rejectItemRequest';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->modalHeading('Reject Item Request')
            ->modalSubmitActionLabel('Reject request')
            ->visible(fn (ItemRequest $record): bool => $record->status === ItemRequestStatus::Submitted
                && (bool) Auth::user()?->can('approve', $record))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (ItemRequest $record, array $data): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(ItemRequestService::class)->reject($record, $data['reason'], $user);
                } catch (ItemRequestException $exception) {
                    Notification::make()->title('Item request not rejected')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Item request rejected')->success()->send();
            });
    }
}
