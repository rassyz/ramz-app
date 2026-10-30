<?php

namespace App\Models;

use App\Models\Pengajuan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu catatan aktivitas bisnis (KF-14). Ditulis eksplisit melalui
 * LogAktivitasService pada titik aksi; tidak pernah diperbarui.
 * Konteks JSON memuat nilai sebelum–sesudah bila relevan (mis.
 * status pembayaran dari → ke).
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $pengajuan_id
 * @property string $aktivitas
 * @property string $deskripsi
 * @property array<string, mixed>|null $konteks
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'pengajuan_id', 'aktivitas', 'deskripsi', 'konteks'])]
class LogAktivitas extends Model
{
    protected $table = 'log_aktivitas';

    /**
     * Log bersifat append-only: tidak ada updated_at.
     */
    const UPDATED_AT = null;

    // Kunci aktivitas per domain.
    const PENGAJUAN_DIBUAT = 'pengajuan.dibuat';

    const PENGAJUAN_DIAJUKAN_KEMBALI = 'pengajuan.diajukan_kembali';

    const PENGAJUAN_DIBATALKAN = 'pengajuan.dibatalkan';

    const PENGAJUAN_STATUS_BERUBAH = 'pengajuan.status_berubah';

    const VERIFIKASI_KEPUTUSAN = 'verifikasi.keputusan';

    const PEMBAYARAN_STATUS_BERUBAH = 'pembayaran.status_berubah';

    const DOKUMEN_DIUNGGAH = 'dokumen.diunggah';

    const DOKUMEN_DIGANTI = 'dokumen.diganti';

    const DOKUMEN_DIHAPUS = 'dokumen.dihapus';

    const PEMERIKSAAN_DIJALANKAN = 'pemeriksaan.dijalankan';

    const PENGGUNA_DIBUAT = 'pengguna.dibuat';

    const PENGGUNA_DIUBAH = 'pengguna.diubah';

    const PENGGUNA_DIHAPUS = 'pengguna.dihapus';

    const LAYANAN_DIBUAT = 'layanan.dibuat';

    const LAYANAN_DIUBAH = 'layanan.diubah';

    const LAYANAN_DIHAPUS = 'layanan.dihapus';

    const ATURAN_VALIDASI_DIUBAH = 'aturan_validasi.diubah';

    /**
     * Peta kunci aktivitas ke label terbaca untuk filter dan tampilan.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::PENGAJUAN_DIBUAT => 'Pengajuan Dibuat',
            self::PENGAJUAN_DIAJUKAN_KEMBALI => 'Pengajuan Diajukan Kembali',
            self::PENGAJUAN_DIBATALKAN => 'Pengajuan Dibatalkan',
            self::PENGAJUAN_STATUS_BERUBAH => 'Status Pengajuan Berubah',
            self::VERIFIKASI_KEPUTUSAN => 'Keputusan Verifikasi',
            self::PEMBAYARAN_STATUS_BERUBAH => 'Status Pembayaran Berubah',
            self::DOKUMEN_DIUNGGAH => 'Dokumen Diunggah',
            self::DOKUMEN_DIGANTI => 'Dokumen Diganti',
            self::DOKUMEN_DIHAPUS => 'Dokumen Dihapus',
            self::PEMERIKSAAN_DIJALANKAN => 'Pemeriksaan Dijalankan',
            self::PENGGUNA_DIBUAT => 'Pengguna Dibuat',
            self::PENGGUNA_DIUBAH => 'Pengguna Diubah',
            self::PENGGUNA_DIHAPUS => 'Pengguna Dihapus',
            self::LAYANAN_DIBUAT => 'Layanan Dibuat',
            self::LAYANAN_DIUBAH => 'Layanan Diubah',
            self::LAYANAN_DIHAPUS => 'Layanan Dihapus',
            self::ATURAN_VALIDASI_DIUBAH => 'Aturan Validasi Diubah',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->aktivitas] ?? $this->aktivitas;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'konteks' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Pengajuan, $this>
     */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }
}
