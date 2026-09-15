<?php

namespace App\Models;

use App\Enums\AssetAuditStatus;
use App\Enums\AuditResult;
use App\Services\AssetAuditService;
use Database\Factories\AssetAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A physical count of assets. Scanning and status changes go through
 * {@see AssetAuditService}.
 */
class AssetAudit extends Model
{
    /** @use HasFactory<AssetAuditFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Mirrored from the column default so a new audit reads correctly before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'in_progress',
    ];

    protected $fillable = [
        'number', 'title', 'status', 'location_id', 'department_id', 'asset_category_id', 'notes',
        'started_at', 'counted_at', 'counted_by', 'closed_at', 'closed_by', 'cancelled_at', 'cancellation_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetAuditStatus::class,
            'started_at' => 'datetime',
            'counted_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'title', 'status', 'location_id', 'department_id', 'asset_category_id', 'cancellation_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('audit');
    }

    /** @return HasMany<AssetAuditLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(AssetAuditLine::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<AssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Counts for the audit's progress and findings.
     *
     * @return array{expected: int, scanned: int, found: int, misplaced: int, missing: int, unlisted: int, condition_changed: int}
     */
    public function summary(): array
    {
        $lines = $this->lines()->get(['id', 'is_expected', 'result', 'scanned_at', 'expected_condition', 'observed_condition']);

        return [
            'expected' => $lines->filter(fn (AssetAuditLine $line): bool => $line->is_expected)->count(),
            'scanned' => $lines->filter(fn (AssetAuditLine $line): bool => $line->scanned_at !== null)->count(),
            'found' => $lines->filter(fn (AssetAuditLine $line): bool => $line->result === AuditResult::Found)->count(),
            'misplaced' => $lines->filter(fn (AssetAuditLine $line): bool => $line->result === AuditResult::Misplaced)->count(),
            'missing' => $lines->filter(fn (AssetAuditLine $line): bool => $line->result === AuditResult::Missing)->count(),
            'unlisted' => $lines->reject(fn (AssetAuditLine $line): bool => $line->is_expected)->count(),
            'condition_changed' => $lines->filter(fn (AssetAuditLine $line): bool => $line->hasConditionChanged())->count(),
        ];
    }
}
