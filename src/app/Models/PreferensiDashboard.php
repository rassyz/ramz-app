<?php

namespace App\Models;

use App\Enums\PeriodeDashboard;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preferensi personalisasi dashboard milik seorang pengguna (KF-10).
 * Nilai bawaan dinyatakan sebagai atribut default model, sehingga
 * instance baru tanpa record tersimpan pun membawa nilai bawaan.
 *
 * @property int $id
 * @property int $user_id
 * @property bool $tampilkan_tren
 * @property bool $tampilkan_distribusi_status
 * @property bool $tampilkan_per_layanan
 * @property bool $tampilkan_pemeriksaan
 * @property bool $tampilkan_antrean
 * @property PeriodeDashboard $periode_default
 * @property int $batas_terbaru
 */
#[Fillable([
    'user_id',
    'tampilkan_tren',
    'tampilkan_distribusi_status',
    'tampilkan_per_layanan',
    'tampilkan_pemeriksaan',
    'tampilkan_antrean',
    'periode_default',
    'batas_terbaru',
])]
class PreferensiDashboard extends Model
{
    protected $table = 'preferensi_dashboards';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tampilkan_tren' => true,
        'tampilkan_distribusi_status' => true,
        'tampilkan_per_layanan' => true,
        'tampilkan_pemeriksaan' => true,
        'tampilkan_antrean' => true,
        'periode_default' => PeriodeDashboard::TigaPuluhHari,
        'batas_terbaru' => 5,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tampilkan_tren' => 'boolean',
            'tampilkan_distribusi_status' => 'boolean',
            'tampilkan_per_layanan' => 'boolean',
            'tampilkan_pemeriksaan' => 'boolean',
            'tampilkan_antrean' => 'boolean',
            'periode_default' => PeriodeDashboard::class,
            'batas_terbaru' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
