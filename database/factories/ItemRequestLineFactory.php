<?php

namespace Database\Factories;

use App\Models\ItemRequest;
use App\Models\ItemRequestLine;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemRequestLine>
 */
class ItemRequestLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_request_id' => ItemRequest::factory(),
            'stock_item_id' => StockItem::factory(),
            'quantity' => 1,
            'notes' => null,
        ];
    }
}
