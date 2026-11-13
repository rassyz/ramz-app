<?php

namespace App\Models;

use App\Enums\StatusHasilPemeriksaan;
use App\Models\DetailPemeriksaan;
use App\Models\Pengajuan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hasil satu kali pemeriksaan Rule-Based Evidence Checker terhadap
 * sebuah pengajuan (entitas HasilPemeriksaan pada dokumen patokan).
 * Setiap pemeriksaan membuat record baru; riwayat run tersimpan.
 *
 * @property int $id
 * @property int $pengajuan_id
 * @property StatusHasilPemeriksaan $status
 * @property int $total_pemeriksaan
 * @property int $total_sesuai
 * @property int $total_tidak_sesuai
 */
#[Fillable(['pengajuan_id', 'status', 'total_pemeriksaan', 'total_sesuai', 'total_tidak_sesuai'])]
class HasilPemeriksaan extends Model
{
    protected $table = 'hasil_pemeriksaans';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusHasilPemeriksaan::class,
            'total_pemeriksaan' => 'integer',
            'total_sesuai' => 'integer',
            'total_tidak_sesuai' => 'integer',
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
     * @return HasMany<DetailPemeriksaan, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(DetailPemeriksaan::class, 'hasil_pemeriksaan_id');
    }
}
