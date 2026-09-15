<?php

namespace App\Models;

use App\Enums\ItemRequestStatus;
use App\Services\ItemRequestService;
use Database\Factories\ItemRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An employee's request for consumables. Status changes go through
 * {@see ItemRequestService}, not through the edit form.
 */
class ItemRequest extends Model
{
    /** @use HasFactory<ItemRequestFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Mirrored from the column default so a new request reads correctly before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'submitted',
    ];

    protected $fillable = [
        'number', 'status', 'employee_id', 'department_id', 'request_date', 'needed_by', 'purpose',
        'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_reason',
        'cancelled_at', 'cancellation_reason', 'fulfilled_at', 'stock_document_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ItemRequestStatus::class,
            'request_date' => 'date',
            'needed_by' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'status', 'employee_id', 'request_date', 'needed_by', 'rejection_reason', 'cancellation_reason', 'stock_document_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('stock');
    }

    /** @return HasMany<ItemRequestLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ItemRequestLine::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The goods issue that fulfilled the request.
     *
     * @return BelongsTo<StockDocument, $this>
     */
    public function stockDocument(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class);
    }

    /**
     * The request can be corrected until someone decides on it.
     */
    public function isEditable(): bool
    {
        return $this->status === ItemRequestStatus::Submitted;
    }

    /** @param Builder<ItemRequest> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', ItemRequestStatus::openValues());
    }
}
