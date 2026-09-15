<?php

namespace App\Models;

use App\Services\StockLedger;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock on hand and its value for one item in one warehouse. A cache of the
 * stock movements, written only by {@see StockLedger}.
 */
class StockBalance extends Model
{
    protected $fillable = ['stock_item_id', 'location_id', 'quantity', 'total_value'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'total_value' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The moving average cost of one unit.
     */
    public function averageCost(): string
    {
        $quantity = Quantity::toHundredths($this->quantity);

        return $quantity <= 0
            ? '0.00'
            : Money::toRupiah(Money::share(Money::toSen($this->total_value), 100, $quantity));
    }
}
