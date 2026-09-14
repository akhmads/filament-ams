<?php

namespace App\Models;

use Database\Factories\WorkOrderChecklistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One task on a work order.
 */
class WorkOrderChecklistItem extends Model
{
    /** @use HasFactory<WorkOrderChecklistItemFactory> */
    use HasFactory;

    protected $fillable = [
        'work_order_id', 'sort_order', 'task', 'is_done', 'done_at', 'done_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_done' => 'boolean',
            'done_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
