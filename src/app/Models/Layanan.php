<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jenis layanan perdagangan dan pembayaran internasional beserta
 * persyaratan dokumennya (entitas Layanan pada dokumen patokan).
 *
 * @property int $id
 * @property string $kode
 * @property string $nama
 * @property string|null $deskripsi
 * @property array<int, array{nama: string, wajib: bool}>|null $persyaratan_dokumen
 * @property bool $is_active
 */
#[Fillable(['kode', 'nama', 'deskripsi', 'persyaratan_dokumen', 'is_active'])]
class Layanan extends Model
{
    protected $table = 'layanans';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'persyaratan_dokumen' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Layanan>  $query
     * @return Builder<Layanan>
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function pengajuans(): HasMany
    {
        return $this->hasMany('App\\Models\\Pengajuan', 'layanan_id');
    }
}
