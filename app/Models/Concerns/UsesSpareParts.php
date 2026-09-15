<?php

namespace App\Models\Concerns;

use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Support\Money;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Work that draws spare parts from stock: each use is a posted goods issue whose
 * source is this record.
 */
trait UsesSpareParts
{
    /** @return MorphMany<StockDocument, $this> */
    public function stockIssues(): MorphMany
    {
        return $this->morphMany(StockDocument::class, 'source');
    }

    /** @return HasManyThrough<StockDocumentLine, StockDocument, $this> */
    public function sparePartLines(): HasManyThrough
    {
        return $this->hasManyThrough(StockDocumentLine::class, StockDocument::class, 'source_id', 'stock_document_id')
            ->where('stock_documents.source_type', $this->getMorphClass());
    }

    /**
     * What the parts used so far cost, at the stock value they left the warehouse with.
     */
    public function sparePartsCost(): string
    {
        return Money::toRupiah($this->sparePartLines()
            ->pluck('posted_value')
            ->sum(fn (?string $value): int => $value === null ? 0 : Money::toSen($value)));
    }
}
