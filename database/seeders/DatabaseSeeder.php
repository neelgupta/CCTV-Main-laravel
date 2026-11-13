<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            IndiaStateCitySeeder::class,
            SubscriptionPlanSeeder::class,
            SuperAdminSeeder::class,
            AdminSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
