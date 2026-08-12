<?php

declare(strict_types=1);

namespace Database\Seeders;

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
        // Deliberately empty. Every users row is a Filament operator with full
        // panel access, so a seeded account with a known password is a back
        // door. Operators are created one at a time with `quiz:create-admin`.
    }
}
