<?php

namespace App\Models;

use App\Enums\WorkOrderResult;
use App\Enums\WorkOrderStatus;
use App\Models\Concerns\UsesSpareParts;
use App\Services\WorkOrderService;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Maintenance work on one asset. Status changes go through
 * {@see WorkOrderService}, not through the edit form.
 */
class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use HasFactory, LogsActivity, SoftDeletes, UsesSpareParts;

    /**
     * Mirrored from the column defaults so a new work order reads correctly before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
        'estimated_cost' => 0,
    ];

    protected $fillable = [
        'number', 'maintenance_plan_id', 'asset_id', 'title', 'due_date', 'status',
        'assigned_to', 'supplier_id', 'estimated_cost', 'estimated_minutes', 'instructions',
        'started_at', 'completed_at', 'completed_by', 'result', 'actual_cost', 'labor_minutes', 'findings',
        'cancelled_at', 'cancellation_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'status' => WorkOrderStatus::class,
            'result' => WorkOrderResult::class,
            'estimated_cost' => 'decimal:2',
            'estimated_minutes' => 'integer',
            'actual_cost' => 'decimal:2',
            'labor_minutes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'status', 'due_date', 'assigned_to', 'supplier_id', 'result', 'actual_cost', 'cancellation_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('maintenance');
    }

    /** @return BelongsTo<MaintenancePlan, $this> */
    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<WorkOrderChecklistItem, $this> */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(WorkOrderChecklistItem::class)->orderBy('sort_order');
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
     * Details and checklist can still change only before work starts.
     */
    public function isEditable(): bool
    {
        return $this->status === WorkOrderStatus::Open;
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_date->lt(today());
    }

    /** @param Builder<WorkOrder> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', WorkOrderStatus::openValues());
    }

    /** @param Builder<WorkOrder> $query */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereIn('status', WorkOrderStatus::openValues())
            ->where('due_date', '<', today()->toDateString());
    }
}
