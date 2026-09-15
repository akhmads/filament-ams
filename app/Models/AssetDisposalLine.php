<?php

namespace App\Models;

use App\Enums\DisposalMethod;
use Database\Factories\AssetDisposalLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDisposalLine extends Model
{
    /** @use HasFactory<AssetDisposalLineFactory> */
    use HasFactory;

    /**
     * Mirrored from the column default so a new line reads correctly before it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'proceeds' => 0,
    ];

    protected $fillable = [
        'asset_disposal_id', 'asset_id', 'repair_ticket_id', 'method', 'proceeds', 'notes',
        'acquisition_cost', 'commercial_accumulated', 'commercial_book_value', 'commercial_gain_loss',
        'fiscal_accumulated', 'fiscal_book_value', 'fiscal_gain_loss', 'disposal_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'method' => DisposalMethod::class,
            'proceeds' => 'decimal:2',
            'acquisition_cost' => 'decimal:2',
            'commercial_accumulated' => 'decimal:2',
            'commercial_book_value' => 'decimal:2',
            'commercial_gain_loss' => 'decimal:2',
            'fiscal_accumulated' => 'decimal:2',
            'fiscal_book_value' => 'decimal:2',
            'fiscal_gain_loss' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<AssetDisposal, $this> */
    public function assetDisposal(): BelongsTo
    {
        return $this->belongsTo(AssetDisposal::class);
    }

    /**
     * Includes soft-deleted assets, so a disposal keeps showing what it wrote off.
     *
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    /** @return BelongsTo<RepairTicket, $this> */
    public function repairTicket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class);
    }

    /** @return BelongsTo<AssetMovement, $this> */
    public function disposalMovement(): BelongsTo
    {
        return $this->belongsTo(AssetMovement::class, 'disposal_movement_id');
    }
}
