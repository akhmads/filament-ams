<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'branch_id', 'department_id', 'user_id', 'employee_number', 'name',
        'position', 'email', 'phone', 'joined_at', 'resigned_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'joined_at' => 'date',
            'resigned_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Assets this employee is currently holding.
     *
     * @return HasMany<Asset, $this>
     */
    public function heldAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'current_employee_id');
    }

    /** @return HasMany<AssetAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class, 'to_employee_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->employee_number} — {$this->name}";
    }

    /** @param Builder<Employee> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EmployeeStatus::Active->value);
    }
}
