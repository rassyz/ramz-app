<?php

namespace App\Policies;

use App\Enums\StatusPengajuan;
use App\Models\Dokumen;
use App\Models\Pengajuan;
use App\Models\User;

/**
 * Kebijakan akses dokumen pendukung pengajuan:
 * - Melihat/mengunduh: nasabah pemilik, Admin, dan Operator.
 * - Mengunggah/mengganti/menghapus: hanya nasabah pemilik, dan hanya
 *   selama pengajuan berada pada status yang masih dapat diubah
 *   (menunggu verifikasi atau perlu perbaikan).
 */
class DokumenPolicy
{
    public function view(User $user, Dokumen $dokumen): bool
    {
        return $user->isAdmin()
            || $user->isOperator()
            || $dokumen->pengajuan->user_id === $user->id;
    }

    public function create(User $user, Pengajuan $pengajuan): bool
    {
        return $user->isNasabah()
            && $pengajuan->user_id === $user->id
            && $this->dalamJendelaUbah($pengajuan);
    }

    public function delete(User $user, Dokumen $dokumen): bool
    {
        return $user->isNasabah()
            && $dokumen->pengajuan->user_id === $user->id
            && $this->dalamJendelaUbah($dokumen->pengajuan);
    }

    /**
     * Jendela perubahan dokumen mengikuti status pengajuan.
     */
    private function dalamJendelaUbah(Pengajuan $pengajuan): bool
    {
        return in_array($pengajuan->status, [
            StatusPengajuan::MenungguVerifikasi,
            StatusPengajuan::PerluPerbaikan,
        ], true);
    }
}
