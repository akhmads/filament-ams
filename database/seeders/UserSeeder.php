<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
// use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Role::create([
        //     'name' => 'admin'
        // ]);

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('q1w2e3r4'),
            'is_active' => 1,
        ]);

        // $user->syncRoles(['admin']);
    }
}
