<?php

namespace App\Models;

use App\Enums\AssetCondition;
use Database\Factories\AssetAssignmentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAssignmentItem extends Model
{
    /** @use HasFactory<AssetAssignmentItemFactory> */
    use HasFactory;

    protected $fillable = [
        'asset_assignment_id', 'asset_id', 'condition', 'notes',
    ];

    protected function casts(): array
    {
        return ['condition' => AssetCondition::class];
    }

    /** @return BelongsTo<AssetAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(AssetAssignment::class, 'asset_assignment_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
