<?php

namespace App\Models;

use App\Enums\KeputusanVerifikasi;
use App\Models\HasilPemeriksaan;
use App\Models\Pengajuan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keputusan verifikasi dokumen oleh Operator atas sebuah pengajuan
 * (KF-07; entitas VerifikasiDokumen pada dokumen patokan). Satu
 * pengajuan dapat memiliki banyak record — satu per putaran
 * verifikasi, termasuk putaran setelah perbaikan nasabah.
 *
 * @property int $id
 * @property int $pengajuan_id
 * @property int $operator_id
 * @property int|null $hasil_pemeriksaan_id
 * @property KeputusanVerifikasi $keputusan
 * @property string|null $catatan
 */
#[Fillable(['pengajuan_id', 'operator_id', 'hasil_pemeriksaan_id', 'keputusan', 'catatan'])]
class VerifikasiDokumen extends Model
{
    protected $table = 'verifikasi_dokumens';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'keputusan' => KeputusanVerifikasi::class,
        ];
    }

    /**
     * @return BelongsTo<Pengajuan, $this>
     */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class);
    }

    /**
     * Operator yang mengambil keputusan.
     *
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * Hasil pemeriksaan checker yang menjadi acuan saat keputusan
     * diambil (snapshot acuan; dapat null bila checker belum pernah
     * berjalan atau record hasil sudah terhapus).
     *
     * @return BelongsTo<HasilPemeriksaan, $this>
     */
    public function hasilPemeriksaan(): BelongsTo
    {
        return $this->belongsTo(HasilPemeriksaan::class, 'hasil_pemeriksaan_id');
    }
}
