<?php

namespace App\Models;

use App\Enums\DepreciationBook;
use Database\Factories\DepreciationEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One asset's depreciation for one month of one book.
 */
class DepreciationEntry extends Model
{
    /** @use HasFactory<DepreciationEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'depreciation_period_id', 'asset_id', 'book', 'period',
        'opening_book_value', 'amount', 'accumulated', 'closing_book_value', 'is_final',
    ];

    protected function casts(): array
    {
        return [
            'book' => DepreciationBook::class,
            'period' => 'date',
            'opening_book_value' => 'decimal:2',
            'amount' => 'decimal:2',
            'accumulated' => 'decimal:2',
            'closing_book_value' => 'decimal:2',
            'is_final' => 'boolean',
        ];
    }

    /** @return BelongsTo<DepreciationPeriod, $this> */
    public function depreciationPeriod(): BelongsTo
    {
        return $this->belongsTo(DepreciationPeriod::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
