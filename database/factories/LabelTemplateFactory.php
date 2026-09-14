<?php

namespace Database\Factories;

use App\Enums\LabelCodeType;
use App\Models\LabelTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabelTemplate>
 */
class LabelTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Template '.fake()->unique()->word(),
            'width_mm' => 50,
            'height_mm' => 25,
            'columns' => 1,
            'margin_mm' => 2,
            'font_size_pt' => 6,
            'code_type' => LabelCodeType::Qr,
            'show_logo' => false,
            'show_company_name' => true,
            'show_asset_name' => true,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function withBarcode(): static
    {
        return $this->state(fn (): array => ['code_type' => LabelCodeType::Both]);
    }
}
