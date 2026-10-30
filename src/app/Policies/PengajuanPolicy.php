<?php

namespace App\Policies;

use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Models\User;

/**
 * Kebijakan akses pengajuan:
 * - Admin dan Operator dapat melihat seluruh pengajuan (pantauan).
 * - Nasabah hanya dapat melihat pengajuan miliknya sendiri.
 * - Pembuatan pengajuan hanya oleh Nasabah.
 * - Pembatalan hanya oleh pemilik, selama masih menunggu verifikasi.
 * Penyaringan daftar per peran dilakukan pada query di halaman
 * (scoping), policy ini menegakkan akses per record.
 */
class PengajuanPolicy
{
    /**
     * Semua pengguna terotentikasi boleh membuka daftar yang relevan
     * baginya; isi daftar disaring per peran pada halaman.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Staf (admin/operator) boleh melihat semua; nasabah hanya miliknya.
     */
    public function view(User $user, Pengajuan $pengajuan): bool
    {
        return $user->isAdmin()
            || $user->isOperator()
            || $pengajuan->user_id === $user->id;
    }

    /**
     * Hanya nasabah yang mengajukan layanan.
     */
    public function create(User $user): bool
    {
        return $user->isNasabah();
    }

    /**
     * Nasabah membatalkan pengajuannya sendiri yang masih
     * berstatus menunggu verifikasi.
     */
    public function cancel(User $user, Pengajuan $pengajuan): bool
    {
        return $user->isNasabah()
            && $pengajuan->user_id === $user->id
            && $pengajuan->status === StatusPengajuan::MenungguVerifikasi;
    }
}
