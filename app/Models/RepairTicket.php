<?php

namespace App\Models;

use App\Enums\RepairPriority;
use App\Enums\RepairTicketStatus;
use App\Enums\RepairType;
use App\Services\RepairTicketService;
use Database\Factories\RepairTicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Corrective repair of a damaged asset. Status changes go through
 * {@see RepairTicketService}, not through the edit form.
 */
class RepairTicket extends Model implements HasMedia
{
    /** @use HasFactory<RepairTicketFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    /**
     * Mirrored from the column defaults so a new ticket reads correctly before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'reported',
        'priority' => 'normal',
        'is_under_warranty' => false,
        'estimated_cost' => 0,
    ];

    protected $fillable = [
        'number', 'asset_id', 'title', 'description', 'priority', 'status',
        'reported_by_employee_id', 'reported_at', 'is_under_warranty',
        'repair_type', 'assigned_to', 'supplier_id', 'estimated_cost',
        'verified_at', 'verified_by', 'approved_at', 'approved_by',
        'started_at', 'repair_movement_id', 'completed_at', 'completed_by', 'actual_cost', 'resolution',
        'rejected_at', 'rejected_by', 'rejection_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'priority' => RepairPriority::class,
            'status' => RepairTicketStatus::class,
            'repair_type' => RepairType::class,
            'reported_at' => 'datetime',
            'is_under_warranty' => 'boolean',
            'estimated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'status', 'priority', 'repair_type', 'assigned_to', 'supplier_id', 'estimated_cost', 'actual_cost', 'rejection_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('maintenance');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_by_employee_id');
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

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
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
     * The movement that took the asset out of use, which also records where it
     * goes back to.
     *
     * @return BelongsTo<AssetMovement, $this>
     */
    public function repairMovement(): BelongsTo
    {
        return $this->belongsTo(AssetMovement::class, 'repair_movement_id');
    }

    /**
     * The report can still be corrected until the repair is approved.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [RepairTicketStatus::Reported, RepairTicketStatus::Verified], strict: true);
    }

    /**
     * How long the asset has been out of use for this repair, so far or in total.
     */
    public function downtimeMinutes(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInMinutes($this->completed_at ?? now(), absolute: true);
    }

    /** @param Builder<RepairTicket> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', RepairTicketStatus::openValues());
    }
}
