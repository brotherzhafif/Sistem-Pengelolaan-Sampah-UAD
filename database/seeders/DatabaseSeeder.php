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
        $this->call([
            CampusSeeder::class,
            RolePermissionSeeder::class,
            MasterDataSeeder::class,
            WeighingSeeder::class,
            SaleSeeder::class,
            PickupSeeder::class,
            ExpenseSeeder::class,
        ]);
    }
}
