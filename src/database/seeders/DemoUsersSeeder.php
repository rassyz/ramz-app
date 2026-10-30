<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Membuat satu akun contoh untuk setiap peran (Admin, Operator,
 * Nasabah) sebagai bahan pengujian hak akses (TC-01 dan TC-02)
 * dan demonstrasi sistem.
 *
 * Password seluruh akun contoh adalah "password" mengikuti konvensi
 * akun bawaan Laravel untuk lingkungan pengembangan. GANTI password
 * ini sebelum aplikasi digunakan di luar lingkungan lokal.
 *
 * Seeder ini idempoten: menjalankannya kembali tidak membuat
 * akun duplikat (dicocokkan berdasarkan email).
 */
class DemoUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Admin Demo', 'email' => 'admin@rama.test', 'role' => UserRole::Admin],
            ['name' => 'Operator Demo', 'email' => 'operator@rama.test', 'role' => UserRole::Operator],
            ['name' => 'Nasabah Demo', 'email' => 'nasabah@rama.test', 'role' => UserRole::Nasabah],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
