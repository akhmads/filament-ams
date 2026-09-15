<?php

namespace App\Models;

use App\Enums\StockDocumentStatus;
use App\Enums\StockDocumentType;
use App\Services\StockLedger;
use Database\Factories\StockDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A goods receipt, issue, transfer, adjustment or stock count. Posting goes
 * through {@see StockLedger}; a posted document is never edited.
 */
class StockDocument extends Model
{
    /** @use HasFactory<StockDocumentFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Mirrored from the column default so a new document reads correctly before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected $fillable = [
        'number', 'type', 'status', 'document_date', 'location_id', 'destination_location_id',
        'supplier_id', 'reference_number', 'employee_id', 'department_id', 'source_type', 'source_id',
        'notes', 'posted_at', 'posted_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockDocumentType::class,
            'status' => StockDocumentStatus::class,
            'document_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'type', 'status', 'document_date', 'location_id', 'destination_location_id', 'reference_number'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('stock');
    }

    /** @return HasMany<StockDocumentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockDocumentLine::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    /**
     * The work order, repair ticket or item request the goods were issued for.
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return $this->status === StockDocumentStatus::Draft;
    }
}
