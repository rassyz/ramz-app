<?php

namespace App\Policies;

use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Models\User;

/**
 * Kebijakan akses pengajuan:
 * - Admin dan Operator dapat melihat seluruh pengajuan (pantauan).
 * - Nasabah hanya dapat melihat pengajuan miliknya sendiri.
 * - Pembuatan pengajuan oleh staf (Admin/Operator) untuk nasabah
 *   (Fase 11 — akun nasabah baru ada setelah LC turun).
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
     * Pengajuan dibuat oleh staf untuk nasabah (Fase 11).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    /**
     * Nasabah membatalkan pengajuannya sendiri yang masih
     * berstatus menunggu verifikasi.
     */
    /**
     * Hapus permanen hanya oleh Admin dan hanya untuk pengajuan yang
     * sudah Dibatalkan (Fase 11) — membersihkan data uji/salah input
     * yang telah diakhiri. Seluruh data anak (dokumen, hasil
     * pemeriksaan, verifikasi, riwayat, pembayaran lama) terhapus
     * mengikuti cascade basis data; log aktivitas & notifikasi
     * bertahan tanpa tautan (nullOnDelete) sebagai jejak audit.
     */
    public function delete(User $user, Pengajuan $pengajuan): bool
    {
        return $user->isAdmin()
            && $pengajuan->status === StatusPengajuan::Dibatalkan;
    }

    /**
     * Hanya Operator, dan hanya saat pengajuan menunggu verifikasi.
     * Admin tidak memverifikasi (Tabel 3.3 dokumen patokan).
     * (Dipulihkan pada Perbaikan 4 — method ini beserta updateStatus
     * dan resubmit sempat hilang dari policy Fase 11.)
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
     * Staf (Admin/Operator) mengisi & mengubah data acuan LC serta
     * snapshot persyaratan selama pengajuan belum berakhir — nomor
     * invoice acuan misalnya baru diketahui setelah LC turun dan
     * barang diserahkan, bisa jadi setelah verifikasi awal.
     */
    public function updateAcuan(User $user, Pengajuan $pengajuan): bool
    {
        return ($user->isAdmin() || $user->isOperator())
            && ! in_array($pengajuan->status, [StatusPengajuan::Selesai, StatusPengajuan::Dibatalkan], true);
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

    public function cancel(User $user, Pengajuan $pengajuan): bool
    {
        if ($pengajuan->status !== StatusPengajuan::MenungguVerifikasi) {
            return false;
        }

        // Nasabah membatalkan pengajuannya sendiri; staf (Admin/
        // Operator) membatalkan pengajuan yang salah input saat
        // pembuatan (Fase 11 — pengajuan dibuat oleh staf).
        return ($user->isNasabah() && $pengajuan->user_id === $user->id)
            || $user->isAdmin()
            || $user->isOperator();
    }
}
