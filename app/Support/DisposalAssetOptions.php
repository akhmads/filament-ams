<?php

namespace App\Support;

use App\Enums\AssetStatus;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;

/**
 * Searchable options for the assets a disposal may list: out of use, held by
 * nobody, and not already on another open disposal.
 */
class DisposalAssetOptions
{
    private const RESULT_LIMIT = 50;

    /**
     * @return array<int, string>
     */
    public static function search(string $search, ?int $exceptDisposalId = null): array
    {
        return Asset::query()
            ->whereIn('status', AssetStatus::disposableValues())
            ->whereNull('current_employee_id')
            ->whereDoesntHave('disposalLines', fn (Builder $query) => $query
                ->when($exceptDisposalId !== null, fn (Builder $query) => $query->where('asset_disposal_id', '!=', $exceptDisposalId))
                ->whereHas('assetDisposal', fn (Builder $query) => $query->open()))
            ->where(fn (Builder $query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%"))
            ->orderBy('code')
            ->limit(self::RESULT_LIMIT)
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Asset $asset): array => [$asset->id => "{$asset->code} — {$asset->name}"])
            ->all();
    }

    public static function label(mixed $assetId): ?string
    {
        return AssetOptions::label($assetId);
    }
}
