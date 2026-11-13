<?php

namespace App\Services;

use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Titik tunggal pencatatan log aktivitas (KF-14). Pencatatan bersifat
 * eksplisit: setiap aksi bisnis memanggil catat() dengan deskripsi
 * yang bermakna dan konteks nilai sebelum–sesudah bila relevan —
 * bukan hasil observer otomatis atas perubahan model.
 */
class LogAktivitasService
{
    /**
     * @param  array<string, mixed>|null  $konteks
     */
    public function catat(
        string $aktivitas,
        string $deskripsi,
        ?User $pelaku = null,
        ?Pengajuan $pengajuan = null,
        ?array $konteks = null,
    ): LogAktivitas {
        return LogAktivitas::create([
            'user_id' => $pelaku?->getKey() ?? Auth::id(),
            'pengajuan_id' => $pengajuan?->id,
            'aktivitas' => $aktivitas,
            'deskripsi' => $deskripsi,
            'konteks' => $konteks,
        ]);
    }
}
