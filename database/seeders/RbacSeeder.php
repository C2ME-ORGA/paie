<?php

namespace Database\Seeders;

use App\Support\Rbac\RbacSynchronizer;
use Illuminate\Database\Seeder;

/**
 * Seeds the technical RBAC referential only.
 *
 * This seeder never creates a user, an administrator, a password or a secret.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app(RbacSynchronizer::class)->synchronize();
    }
}
