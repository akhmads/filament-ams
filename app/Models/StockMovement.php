<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The stock card. Rows are append-only — a correction is a new document, never
 * an edit of an existing row.
 */
class StockMovement extends Model
{
    protected $fillable = [
        'stock_item_id', 'location_id', 'stock_document_id', 'stock_document_line_id', 'movement_type', 'moved_on',
        'quantity', 'value', 'balance_quantity', 'balance_value', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'moved_on' => 'date',
            'quantity' => 'decimal:2',
            'value' => 'decimal:2',
            'balance_quantity' => 'decimal:2',
            'balance_value' => 'decimal:2',
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

    /** @return BelongsTo<StockDocument, $this> */
    public function stockDocument(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class)->withTrashed();
    }

    /** @return BelongsTo<StockDocumentLine, $this> */
    public function stockDocumentLine(): BelongsTo
    {
        return $this->belongsTo(StockDocumentLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
