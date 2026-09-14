<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Enums\PlacementType;
use Database\Factories\AssetAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A handover document (BAST) that can carry many assets at once.
 */
class AssetAssignment extends Model
{
    /** @use HasFactory<AssetAssignmentFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The default lives on the model, not only on the column, so a freshly
     * created instance already carries the right status without a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected $fillable = [
        'number', 'type', 'assignment_date', 'expected_return_date', 'completed_at',
        'branch_id', 'to_placement_type', 'to_branch_id', 'to_location_id',
        'to_employee_id', 'to_department_id',
        'handed_over_by', 'received_by_name', 'handover_signature', 'receiver_signature',
        'status', 'purpose', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssignmentType::class,
            'status' => AssignmentStatus::class,
            'to_placement_type' => PlacementType::class,
            'assignment_date' => 'date',
            'expected_return_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'type', 'status', 'assignment_date', 'to_employee_id', 'to_location_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return HasMany<AssetAssignmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AssetAssignmentItem::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function toEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_employee_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function handedOverBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'handed_over_by');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return $this->status === AssignmentStatus::Draft;
    }

    public function isOverdue(): bool
    {
        return $this->status === AssignmentStatus::Completed
            && $this->type === AssignmentType::Checkout
            && $this->expected_return_date !== null
            && $this->expected_return_date->isPast();
    }

    public function getRecipientNameAttribute(): string
    {
        return $this->toEmployee?->name
            ?? $this->toLocation?->name
            ?? $this->received_by_name
            ?? '—';
    }

    /** @param Builder<AssetAssignment> $query */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', AssignmentStatus::Completed->value)
            ->where('type', AssignmentType::Checkout->value)
            ->whereNotNull('expected_return_date')
            ->whereDate('expected_return_date', '<', now());
    }
}
