<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\AturanValidasiSeeder;
use Database\Seeders\DemoPengajuanSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\LayananSeeder;
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

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            DemoUsersSeeder::class,
        ]);

        // Seeder untuk data awal layanan dan pengajuan demo.
        $this->call([
            LayananSeeder::class,
            DemoPengajuanSeeder::class,
        ]);

        // Seeder untuk aturan validasi checker.
        $this->call([
            AturanValidasiSeeder::class,
        ]);
    }
}
