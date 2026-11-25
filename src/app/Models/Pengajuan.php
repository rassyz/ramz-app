<?php

namespace App\Models;

use App\Enums\MataUang;
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
 * @property Carbon|null $dokumen_fisik_diterima_at
 * @property int|null $disetarakan_oleh
 * @property string|null $nomor_lc
 * @property string|null $nama_applicant
 * @property string|null $nomor_po
 * @property string|null $nominal_acuan
 * @property MataUang|null $mata_uang_acuan
 * @property string|null $toleransi_persen
 * @property string|null $kuantitas_acuan
 * @property string|null $satuan_acuan
 * @property Carbon|null $tanggal_terbit_lc
 * @property Carbon|null $tanggal_kedaluwarsa_lc
 * @property array<int, array{nama: string, wajib: bool}>|null $persyaratan_dokumen
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nomor_pengajuan', 'user_id', 'layanan_id', 'nomor_invoice', 'status', 'catatan', 'dokumen_fisik_diterima_at', 'disetarakan_oleh', 'nomor_lc', 'nama_applicant', 'nomor_po', 'nominal_acuan', 'mata_uang_acuan', 'toleransi_persen', 'kuantitas_acuan', 'satuan_acuan', 'tanggal_terbit_lc', 'tanggal_kedaluwarsa_lc', 'path_file_lc', 'nama_file_lc', 'persyaratan_dokumen'])]
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
            'dokumen_fisik_diterima_at' => 'datetime',
            'nominal_acuan' => 'decimal:2',
            'mata_uang_acuan' => MataUang::class,
            'toleransi_persen' => 'decimal:2',
            'kuantitas_acuan' => 'decimal:3',
            'tanggal_terbit_lc' => 'date',
            'tanggal_kedaluwarsa_lc' => 'date',
            'persyaratan_dokumen' => 'array',
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
     * Dokumen pendukung yang terunggah untuk pengajuan ini (Fase 3).
     *
     * @return HasMany<Dokumen, $this>
     */
    public function dokumens(): HasMany
    {
        return $this->hasMany(Dokumen::class);
    }

    /**
     * Seluruh riwayat pemeriksaan checker, terbaru di atas (Fase 3).
     *
     * @return HasMany<HasilPemeriksaan, $this>
     */
    public function hasilPemeriksaans(): HasMany
    {
        return $this->hasMany(HasilPemeriksaan::class)->latest();
    }

    /**
     * Pemeriksaan checker paling terakhir (Fase 3).
     *
     * @return HasOne<HasilPemeriksaan, $this>
     */
    public function hasilPemeriksaanTerakhir(): HasOne
    {
        return $this->hasOne(HasilPemeriksaan::class)->latestOfMany();
    }

    /**
     * Keputusan verifikasi operator atas pengajuan ini (Fase 4).
     *
     * @return HasMany<VerifikasiDokumen, $this>
     */
    public function verifikasiDokumens(): HasMany
    {
        return $this->hasMany(VerifikasiDokumen::class)->orderBy('created_at');
    }

    /**
     * Staf yang menyetarakan dokumen fisik dengan data masukan
     * nasabah (Fase 10) — null selama fisik belum diterima.
     *
     * @return BelongsTo<User, $this>
     */
    public function disetarakanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetarakan_oleh');
    }

    /**
     * Persyaratan dokumen yang berlaku untuk pengajuan ini: snapshot
     * per pengajuan (Fase 11, wujud klausul dokumen LC) bila sudah
     * tersimpan, atau templat dari layanan sebagai cadangan untuk
     * pengajuan lama.
     *
     * @return array<int, array{nama: string, wajib: bool}>
     */
    public function persyaratanBerlaku(): array
    {
        return $this->persyaratan_dokumen ?? $this->layanan?->persyaratan_dokumen ?? [];
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

        return 'PGJ-' . $tanggal . '-' . str_pad((string) ($jumlahHariIni + 1), 4, '0', STR_PAD_LEFT);
    }
}
