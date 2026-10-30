<?php

namespace App\Models;

use App\Enums\HasilDetail;
use App\Enums\KategoriAturan;
use App\Models\AturanValidasi;
use App\Models\HasilPemeriksaan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil pemeriksaan per aturan dalam satu run checker, lengkap dengan
 * snapshot nilai dari sisi pengajuan dan sisi dokumen
 * (entitas DetailPemeriksaan pada dokumen patokan).
 *
 * @property int $id
 * @property int $hasil_pemeriksaan_id
 * @property int|null $aturan_id
 * @property KategoriAturan $kategori
 * @property string $nama_pemeriksaan
 * @property string|null $nilai_pengajuan
 * @property string|null $nilai_dokumen
 * @property HasilDetail $hasil
 * @property string|null $keterangan
 */
#[Fillable([
    'hasil_pemeriksaan_id',
    'aturan_id',
    'kategori',
    'nama_pemeriksaan',
    'nilai_pengajuan',
    'nilai_dokumen',
    'hasil',
    'keterangan',
])]
class DetailPemeriksaan extends Model
{
    protected $table = 'detail_pemeriksaans';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kategori' => KategoriAturan::class,
            'hasil' => HasilDetail::class,
        ];
    }

    /**
     * @return BelongsTo<HasilPemeriksaan, $this>
     */
    public function hasilPemeriksaan(): BelongsTo
    {
        return $this->belongsTo(HasilPemeriksaan::class, 'hasil_pemeriksaan_id');
    }

    /**
     * @return BelongsTo<AturanValidasi, $this>
     */
    public function aturan(): BelongsTo
    {
        return $this->belongsTo(AturanValidasi::class, 'aturan_id');
    }
}
