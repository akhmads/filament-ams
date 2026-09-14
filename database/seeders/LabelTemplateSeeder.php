<?php

namespace Database\Seeders;

use App\Enums\LabelCodeType;
use App\Models\LabelTemplate;
use Illuminate\Database\Seeder;

class LabelTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Roll Label 50 × 25 mm',
                'width_mm' => 50,
                'height_mm' => 25,
                'columns' => 1,
                'margin_mm' => 2,
                'font_size_pt' => 6,
                'code_type' => LabelCodeType::Qr,
                'show_logo' => false,
                'show_company_name' => true,
                'show_asset_name' => true,
                'is_default' => true,
            ],
            [
                'name' => 'Roll Label 38 × 25 mm (QR only)',
                'width_mm' => 38,
                'height_mm' => 25,
                'columns' => 1,
                'margin_mm' => 1.5,
                'font_size_pt' => 5.5,
                'code_type' => LabelCodeType::Qr,
                'show_logo' => false,
                'show_company_name' => false,
                'show_asset_name' => false,
            ],
            [
                'name' => 'A4 Sheet — 3 columns',
                'width_mm' => 63,
                'height_mm' => 35,
                'columns' => 3,
                'margin_mm' => 3,
                'font_size_pt' => 7,
                'code_type' => LabelCodeType::Both,
                'show_logo' => true,
                'show_company_name' => true,
                'show_asset_name' => true,
                'show_branch' => true,
            ],
        ];

        foreach ($templates as $template) {
            LabelTemplate::firstOrCreate(['name' => $template['name']], $template);
        }
    }
}
