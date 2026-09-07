<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Master Templates Seed (Production & Local)
        $this->call(TemplateSeeder::class);

        // 2. Plans & Pricing Seed (Production & Local)
        $this->call(PlanSeeder::class);

        // 3. Admin & Demo Users Seed (Production & Local)
        $this->call(UserSeeder::class);

        // 4. Demo & Acceptance Test Environment Seed (Local & Staging Only)
        if (!app()->environment('production')) {
            $this->call(DemoEnvironmentSeeder::class);
        }
    }
}
