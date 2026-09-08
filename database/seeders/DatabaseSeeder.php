<?php

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
        $this->call([
            AdminSeeder::class,
            OrganizationSeeder::class,
            SbaAccountSeeder::class,
            MemberDataSeeder::class,
            ArticleSeeder::class,
            PageSeeder::class,
            LibrarySeeder::class,
            CourseContentSeeder::class,
        ]);
    }
}
