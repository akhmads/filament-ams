<?php

namespace App\Models;

use Database\Factories\ItemRequestLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemRequestLine extends Model
{
    /** @use HasFactory<ItemRequestLineFactory> */
    use HasFactory;

    protected $fillable = ['item_request_id', 'stock_item_id', 'quantity', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    /** @return BelongsTo<ItemRequest, $this> */
    public function itemRequest(): BelongsTo
    {
        return $this->belongsTo(ItemRequest::class);
    }

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
