<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Plans & Pricing Seed (Production & Local)
        $this->call(PlanSeeder::class);

        // 2. Admin & Demo Users Seed (Production & Local)
        $this->call(UserSeeder::class);

        // Note: TemplateSeeder can be executed on-demand whenever needed via:
        // php artisan db:seed --class=TemplateSeeder
    }
}
