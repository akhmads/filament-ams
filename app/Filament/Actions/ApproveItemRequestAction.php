<?php

namespace App\Filament\Actions;

use App\Enums\ItemRequestStatus;
use App\Exceptions\ItemRequestException;
use App\Models\ItemRequest;
use App\Models\User;
use App\Services\ItemRequestService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Signs off an item request so its goods can be issued.
 */
class ApproveItemRequestAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'approveItemRequest';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Approve')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve Item Request')
            ->modalDescription('The requested items can then be issued from a warehouse.')
            ->modalSubmitActionLabel('Approve')
            ->visible(fn (ItemRequest $record): bool => $record->status === ItemRequestStatus::Submitted
                && (bool) Auth::user()?->can('approve', $record))
            ->action(function (ItemRequest $record): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(ItemRequestService::class)->approve($record, $user);
                } catch (ItemRequestException $exception) {
                    Notification::make()->title('Item request not approved')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Item request approved')->success()->send();
            });
    }
}
