<?php

namespace App\Models;

use Database\Factories\StockDocumentLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockDocumentLine extends Model
{
    /** @use HasFactory<StockDocumentLineFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_document_id', 'stock_item_id', 'quantity', 'unit_cost', 'notes', 'system_quantity', 'posted_value',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'system_quantity' => 'decimal:2',
            'posted_value' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<StockDocument, $this> */
    public function stockDocument(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class);
    }

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
