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

    /**
     * Hanya Operator, dan hanya saat pengajuan menunggu verifikasi.
     * Admin tidak memverifikasi (Tabel 3.3 dokumen patokan).
     */
    public function verify(User $user, Pengajuan $pengajuan): bool
    {
        return $user->isOperator()
            && $pengajuan->status === StatusPengajuan::MenungguVerifikasi;
    }

    /**
     * Operator memajukan status pengajuan yang sudah terverifikasi;
     * kesahihan langkahnya ditegakkan VerifikasiService.
     */
    public function updateStatus(User $user, Pengajuan $pengajuan): bool
    {
        return $user->isOperator()
            && ! in_array($pengajuan->status, [StatusPengajuan::Selesai, StatusPengajuan::Dibatalkan], true);
    }

    /**
     * Operator mengelola status pembayaran selama pengajuan
     * belum dibatalkan.
     */
    public function updatePembayaran(User $user, Pengajuan $pengajuan): bool
    {
        return $user->isOperator()
            && $pengajuan->status !== StatusPengajuan::Dibatalkan;
    }

    /**
     * Nasabah pemilik mengajukan kembali pengajuan yang sedang
     * berstatus perlu perbaikan.
     */
    public function resubmit(User $user, Pengajuan $pengajuan): bool
    {
        return $user->isNasabah()
            && $pengajuan->user_id === $user->id
            && $pengajuan->status === StatusPengajuan::PerluPerbaikan;
    }
}
