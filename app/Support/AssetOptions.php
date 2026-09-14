<?php

namespace App\Support;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;

/**
 * Searchable asset options for selects that may span every branch, where loading
 * all assets up front would be too many.
 */
class AssetOptions
{
    private const RESULT_LIMIT = 50;

    /**
     * Assets still in service whose code, name or serial number matches.
     *
     * @return array<int, string>
     */
    public static function search(string $search): array
    {
        return Asset::query()
            ->active()
            ->where(fn (Builder $query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%"))
            ->orderBy('code')
            ->limit(self::RESULT_LIMIT)
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Asset $asset): array => [$asset->id => self::format($asset)])
            ->all();
    }

    public static function label(mixed $assetId): ?string
    {
        $asset = Asset::withTrashed()->find($assetId, ['id', 'code', 'name']);

        return $asset === null ? null : self::format($asset);
    }

    private static function format(Asset $asset): string
    {
        return "{$asset->code} — {$asset->name}";
    }
}
