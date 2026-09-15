<?php

namespace App\Support;

use App\Models\StockBalance;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Searchable stock item options for selects, where loading every item up front
 * would be too many.
 */
class StockItemOptions
{
    private const RESULT_LIMIT = 50;

    /**
     * Active items whose code or name matches.
     *
     * @return array<int, string>
     */
    public static function search(string $search): array
    {
        return StockItem::query()
            ->active()
            ->where(fn (Builder $query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"))
            ->orderBy('code')
            ->limit(self::RESULT_LIMIT)
            ->get(['id', 'code', 'name', 'unit'])
            ->mapWithKeys(fn (StockItem $item): array => [$item->id => self::format($item)])
            ->all();
    }

    public static function label(mixed $stockItemId): ?string
    {
        $item = StockItem::withTrashed()->find($stockItemId, ['id', 'code', 'name', 'unit']);

        return $item === null ? null : self::format($item);
    }

    /**
     * "On hand: 12.5 pcs" for an item in a warehouse, or null until both are chosen.
     */
    public static function onHandHint(mixed $stockItemId, mixed $locationId): ?string
    {
        if (blank($stockItemId) || blank($locationId)) {
            return null;
        }

        $item = StockItem::withTrashed()->find($stockItemId, ['id', 'unit']);

        if ($item === null) {
            return null;
        }

        $quantity = StockBalance::query()
            ->where('stock_item_id', $item->id)
            ->where('location_id', $locationId)
            ->value('quantity');

        return 'On hand: '.Quantity::format($quantity).' '.$item->unit;
    }

    private static function format(StockItem $item): string
    {
        return "{$item->code} — {$item->name} ({$item->unit})";
    }
}
