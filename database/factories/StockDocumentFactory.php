<?php

namespace Database\Factories;

use App\Enums\StockDocumentStatus;
use App\Enums\StockDocumentType;
use App\Models\Location;
use App\Models\StockDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockDocument>
 */
class StockDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => StockDocumentType::Receipt->prefix().'/'.now()->format('ym').'/'.fake()->unique()->numerify('####'),
            'type' => StockDocumentType::Receipt,
            'status' => StockDocumentStatus::Draft,
            'document_date' => now()->toDateString(),
            'location_id' => Location::factory()->warehouse(),
            'destination_location_id' => null,
            'supplier_id' => null,
            'reference_number' => null,
            'employee_id' => null,
            'department_id' => null,
            'notes' => null,
        ];
    }

    public function type(StockDocumentType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
            'number' => $type->prefix().'/'.now()->format('ym').'/'.fake()->unique()->numerify('####'),
        ]);
    }

    public function at(Location $warehouse): static
    {
        return $this->state(fn (): array => ['location_id' => $warehouse->id]);
    }

    public function transferTo(Location $destination): static
    {
        return $this->type(StockDocumentType::Transfer)
            ->state(fn (): array => ['destination_location_id' => $destination->id]);
    }

    public function posted(): static
    {
        return $this->state(fn (): array => [
            'status' => StockDocumentStatus::Posted,
            'posted_at' => now(),
        ]);
    }
}
