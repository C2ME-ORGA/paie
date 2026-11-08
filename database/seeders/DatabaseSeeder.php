<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only the technical RBAC referential is seeded. No default user,
     * administrator, password or business data is created.
     */
    public function run(): void
    {
        $this->call(RbacSeeder::class);
    }
}
