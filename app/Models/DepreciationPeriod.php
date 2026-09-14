<?php

namespace App\Models;

use App\Enums\DepreciationBook;
use App\Enums\DepreciationPeriodStatus;
use Database\Factories\DepreciationPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One month of one depreciation book. A draft can be recalculated; once posted
 * the period and its entries are locked.
 */
class DepreciationPeriod extends Model
{
    /** @use HasFactory<DepreciationPeriodFactory> */
    use HasFactory, LogsActivity;

    /**
     * Mirrored from the column default so a period built in memory already reads
     * as a draft before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected $fillable = [
        'book', 'period', 'status', 'asset_count', 'total_amount',
        'calculated_at', 'posted_at', 'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'book' => DepreciationBook::class,
            'status' => DepreciationPeriodStatus::class,
            'period' => 'date',
            'asset_count' => 'integer',
            'total_amount' => 'decimal:2',
            'calculated_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total_amount', 'asset_count', 'posted_at', 'posted_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('depreciation');
    }

    /** @return HasMany<DepreciationEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(DepreciationEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isPosted(): bool
    {
        return $this->status === DepreciationPeriodStatus::Posted;
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->period->format('F Y');
    }
}
