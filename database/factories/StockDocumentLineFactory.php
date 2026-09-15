<?php

namespace Database\Factories;

use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockDocumentLine>
 */
class StockDocumentLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_document_id' => StockDocument::factory(),
            'stock_item_id' => StockItem::factory(),
            'quantity' => 1,
            'unit_cost' => null,
            'notes' => null,
        ];
    }

    public function costing(string|int $unitCost): static
    {
        return $this->state(fn (): array => ['unit_cost' => $unitCost]);
    }
}
