<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Makes the seeder safe to run on every container start (docker/entrypoint.sh).
        if (User::where('email', 'demo@example.com')->exists()) {
            $this->command->info('Demo data already present, skipping seeding.');

            return;
        }

        User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'password', // hashed by the User model's "hashed" cast
        ]);

        $this->call([
            CategorySeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
