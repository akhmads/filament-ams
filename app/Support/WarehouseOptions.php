<?php

namespace App\Support;

use App\Enums\LocationType;
use App\Models\Location;

/**
 * Active warehouses for selects. Stock is only ever kept in a location of type
 * Warehouse.
 */
class WarehouseOptions
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return Location::query()
            ->active()
            ->where('type', LocationType::Warehouse->value)
            ->defaultOrder()
            ->get()
            ->mapWithKeys(fn (Location $location): array => [$location->id => $location->full_name])
            ->all();
    }
}
