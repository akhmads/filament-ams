<?php

namespace App\Models;

use App\Enums\AssetDisposalStatus;
use App\Services\AssetDisposalService;
use App\Support\Money;
use Database\Factories\AssetDisposalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A proposal to write assets off the books. Status changes go through
 * {@see AssetDisposalService}, not through the edit form.
 */
class AssetDisposal extends Model
{
    /** @use HasFactory<AssetDisposalFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Mirrored from the column default so a new disposal reads correctly before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'proposed',
    ];

    protected $fillable = [
        'number', 'status', 'disposal_date', 'recipient_name', 'reference_number', 'reason', 'notes',
        'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_reason',
        'cancelled_at', 'cancellation_reason', 'completed_at', 'completed_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetDisposalStatus::class,
            'disposal_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'status', 'disposal_date', 'recipient_name', 'reference_number', 'rejection_reason', 'cancellation_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('disposal');
    }

    /** @return HasMany<AssetDisposalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(AssetDisposalLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The proposal can be corrected until someone decides on it.
     */
    public function isEditable(): bool
    {
        return $this->status === AssetDisposalStatus::Proposed;
    }

    /**
     * The sum of an amount column across the lines, e.g. "commercial_gain_loss".
     */
    public function lineTotal(string $column): string
    {
        return Money::toRupiah($this->lines()
            ->pluck($column)
            ->sum(fn (?string $amount): int => $amount === null ? 0 : Money::toSen($amount)));
    }

    /** @param Builder<AssetDisposal> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', AssetDisposalStatus::openValues());
    }
}
