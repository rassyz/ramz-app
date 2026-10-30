<?php

namespace App\Models;

use App\Enums\KategoriAturan;
use App\Models\DetailPemeriksaan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aturan pemeriksaan IF–THEN yang dibaca Rule-Based Evidence Checker.
 * Aturan disimpan sebagai data agar dapat diaktifkan/dinonaktifkan
 * tanpa mengubah kode (entitas AturanValidasi pada dokumen patokan).
 *
 * @property int $id
 * @property string $kode
 * @property string $nama
 * @property KategoriAturan $kategori
 * @property string|null $field
 * @property string|null $keterangan
 * @property bool $is_active
 */
#[Fillable(['kode', 'nama', 'kategori', 'field', 'keterangan', 'is_active'])]
class AturanValidasi extends Model
{
    protected $table = 'aturan_validasis';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kategori' => KategoriAturan::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<DetailPemeriksaan, $this>
     */
    public function detailPemeriksaans(): HasMany
    {
        return $this->hasMany(DetailPemeriksaan::class, 'aturan_id');
    }
}
