<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Registrasi mandiri DITUTUP (Fase 11, model bisnis klien):
     * aplikasi ini adalah alat bantu tim pemeriksa dokumen — akun
     * nasabah dibuat oleh Admin agar cocok dengan data nasabah pada
     * sistem inti perusahaan, lalu email dan kata sandinya dikirim ke
     * nasabah ketika LC terbit. Formulir registrasi publik karena itu
     * selalu ditolak dengan pesan ini; akun baru hanya lahir lewat
     * halaman Kelola Pengguna oleh Admin.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        throw ValidationException::withMessages([
            'email' => 'Pendaftaran mandiri ditutup. Akun dibuat oleh admin dan dikirimkan ke nasabah ketika LC terbit.',
        ]);
    }
}
