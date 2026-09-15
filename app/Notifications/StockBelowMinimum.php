<?php

namespace App\Notifications;

use App\Filament\Resources\StockItems\StockItemResource;
use App\Models\StockItem;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;

/**
 * Tells stock keepers that items have just dropped below their minimum stock.
 */
class StockBelowMinimum extends Notification
{
    private const LISTED_ITEMS = 5;

    /**
     * @param  Collection<int, StockItem>  $items
     */
    public function __construct(
        public readonly Collection $items,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $count = $this->items->count();

        $names = $this->items
            ->take(self::LISTED_ITEMS)
            ->map(fn (StockItem $item): string => "{$item->code} {$item->name}")
            ->join(', ');

        if ($count > self::LISTED_ITEMS) {
            $names .= ' and '.($count - self::LISTED_ITEMS).' more';
        }

        $url = $count === 1
            ? StockItemResource::getUrl('view', ['record' => $this->items->first()])
            : StockItemResource::getUrl('index');

        return FilamentNotification::make()
            ->title($count === 1 ? 'Stock below minimum' : "{$count} items below minimum stock")
            ->body("{$names}. Restock soon.")
            ->icon('heroicon-o-archive-box-x-mark')
            ->status('warning')
            ->actions([
                Action::make('view')
                    ->label('Open')
                    ->url($url)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
