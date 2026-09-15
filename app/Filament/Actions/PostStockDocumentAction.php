<?php

namespace App\Filament\Actions;

use App\Enums\StockDocumentType;
use App\Exceptions\StockException;
use App\Models\StockDocument;
use App\Models\User;
use App\Services\StockLedger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Posts a draft stock document: stock changes and the document is locked.
 */
class PostStockDocumentAction extends Action
{
    /**
     * Dispatched so the document's item list shows the posted values without a reload.
     */
    public const POSTED_EVENT = 'stock-document-posted';

    public static function getDefaultName(): ?string
    {
        return 'postStockDocument';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Post')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (StockDocument $record): string => "Post {$record->number}")
            ->modalDescription(fn (StockDocument $record): string => $record->type === StockDocumentType::Count
                ? 'Stock in the warehouse is set to the counted quantities, measured against what is on hand right now. The document can no longer be edited.'
                : 'Stock changes as soon as the document is posted, and it can no longer be edited. Correct a mistake with an adjustment.')
            ->modalSubmitActionLabel('Post')
            ->visible(fn (StockDocument $record): bool => $record->isEditable()
                && (bool) Auth::user()?->can('post', $record))
            ->action(function (StockDocument $record, Component $livewire): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    app(StockLedger::class)->post($record, $user);
                } catch (StockException $exception) {
                    Notification::make()->title('Stock document not posted')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                $livewire->dispatch(self::POSTED_EVENT);

                Notification::make()->title('Stock document posted')->success()->send();
            });
    }
}
