<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SettingSeeder::class,
            LabelTemplateSeeder::class,
            MasterDataSeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrator',
                'password' => 'password',
                'is_active' => true,
            ],
        );

        $admin->assignRole('super_admin');

        if (app()->environment('local')) {
            $this->call(DemoAssetSeeder::class);
        }
    }
}
