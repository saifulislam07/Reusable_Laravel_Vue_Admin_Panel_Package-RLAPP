<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Admin\RolePermissionSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->call(RolePermissionSeeder::class);

        $user->assignRole((string) config('admin-kit.roles.protected_role'));
    }
}
