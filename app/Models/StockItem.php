<?php

namespace App\Models;

use App\Enums\StockItemType;
use App\Support\Quantity;
use Database\Factories\StockItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A consumable or spare part, counted by quantity per warehouse. Stock only
 * changes by posting a {@see StockDocument}.
 */
class StockItem extends Model
{
    /** @use HasFactory<StockItemFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Mirrored from the column defaults so a new item reads correctly before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'item_type' => 'consumable',
        'minimum_quantity' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'code', 'name', 'item_type', 'unit', 'minimum_quantity', 'reorder_quantity', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'item_type' => StockItemType::class,
            'minimum_quantity' => 'decimal:2',
            'reorder_quantity' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('stock');
    }

    /** @return HasMany<StockBalance, $this> */
    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Stock on hand across every warehouse, in hundredths.
     */
    public function onHandHundredths(): int
    {
        return $this->balances()
            ->pluck('quantity')
            ->sum(fn (string $quantity): int => Quantity::toHundredths($quantity));
    }

    /**
     * What to buy to get back above the minimum: the usual reorder quantity, or
     * the shortfall when none is set.
     */
    public function suggestedReorderQuantity(string|int|float|null $onHand): string
    {
        if ($this->reorder_quantity !== null && Quantity::toHundredths($this->reorder_quantity) > 0) {
            return $this->reorder_quantity;
        }

        // The on-hand figure comes from an SQL sum, which some drivers return as a float.
        $shortfall = Quantity::toHundredths($this->minimum_quantity) - (int) round((float) ($onHand ?? 0) * 100);

        return Quantity::toDecimal(max($shortfall, 0));
    }

    /**
     * Adds `on_hand_quantity` and `on_hand_value` summed over every warehouse.
     *
     * @param  Builder<StockItem>  $query
     */
    public function scopeWithStockOnHand(Builder $query): void
    {
        $query->withSum('balances as on_hand_quantity', 'quantity')
            ->withSum('balances as on_hand_value', 'total_value');
    }

    /**
     * Items with a minimum whose stock across all warehouses has fallen below it.
     *
     * @param  Builder<StockItem>  $query
     */
    public function scopeBelowMinimum(Builder $query): void
    {
        $onHand = StockBalance::query()
            ->selectRaw('coalesce(sum(quantity), 0)')
            ->whereColumn('stock_balances.stock_item_id', 'stock_items.id');

        $query->where('stock_items.minimum_quantity', '>', 0)
            ->where('stock_items.minimum_quantity', '>', $onHand);
    }

    /** @param Builder<StockItem> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
