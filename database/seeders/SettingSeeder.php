<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\Appearance;
use App\Support\Theme;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::setMany([
            'company_name' => 'PT Contoh Sejahtera',
            'company_address' => 'Jl. Merdeka No. 1, Jakarta Pusat',
            'company_city' => 'Jakarta',
            'company_logo_path' => null,
            'asset_code_format' => '{CATEGORY}-{BRANCH}-{YY}{MM}-{SEQ}',
            'asset_code_sequence_length' => 4,
            'fiscal_year_start_month' => 1,
        ]);

        Setting::setMany([...Appearance::defaults(), ...Theme::DEFAULTS], group: Appearance::GROUP);
    }
}
