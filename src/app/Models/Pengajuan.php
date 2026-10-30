<?php

namespace App\Models;

use App\Enums\StatusPengajuan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Pengajuan layanan oleh nasabah — entitas pusat pada rancangan
 * basis data dokumen patokan, menghubungkan nasabah (Pengguna),
 * Layanan, Pembayaran, dan kelak Dokumen serta hasil pemeriksaan.
 *
 * @property int $id
 * @property string $nomor_pengajuan
 * @property int $user_id
 * @property int $layanan_id
 * @property string $nomor_invoice
 * @property StatusPengajuan $status
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nomor_pengajuan', 'user_id', 'layanan_id', 'nomor_invoice', 'status', 'catatan'])]
class Pengajuan extends Model
{
    protected $table = 'pengajuans';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusPengajuan::class,
        ];
    }

    /**
     * Nasabah pemilik pengajuan.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alias semantik untuk user(): nasabah pengaju.
     *
     * @return BelongsTo<User, $this>
     */
    public function nasabah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Layanan, $this>
     */
    public function layanan(): BelongsTo
    {
        return $this->belongsTo(Layanan::class);
    }

    /**
     * @return HasOne<Pembayaran, $this>
     */
    public function pembayaran(): HasOne
    {
        return $this->hasOne(Pembayaran::class);
    }

    /**
     * @return HasMany<RiwayatStatus, $this>
     */
    public function riwayatStatuses(): HasMany
    {
        return $this->hasMany(RiwayatStatus::class)->orderBy('created_at');
    }

    /**
     * Membuat nomor pengajuan unik dengan format PGJ-YYYYMMDD-XXXX,
     * XXXX adalah nomor urut pada hari berjalan. Wajib dipanggil di
     * dalam transaksi database agar nomor tidak bentrok.
     */
    public static function generateNomorPengajuan(): string
    {
        $tanggal = now()->format('Ymd');

        $jumlahHariIni = static::whereDate('created_at', now()->toDateString())
            ->lockForUpdate()
            ->count();

        return 'PGJ-'.$tanggal.'-'.str_pad((string) ($jumlahHariIni + 1), 4, '0', STR_PAD_LEFT);
    }
}
