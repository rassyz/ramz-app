<?php

namespace App\Models;

use App\Enums\KanalNotifikasi;
use App\Enums\StatusNotifikasi;
use App\Models\Pengajuan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu pemberitahuan untuk satu penerima pada satu kanal (KF-11).
 * Siklus status dan jumlah percobaan dikelola oleh KirimNotifikasiJob
 * (KF-12); dibaca_at menandai keterbacaan di kotak masuk aplikasi.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $pengajuan_id
 * @property KanalNotifikasi $kanal
 * @property string $kejadian
 * @property string $judul
 * @property string $isi
 * @property StatusNotifikasi $status
 * @property int $jumlah_percobaan
 * @property Carbon|null $terkirim_at
 * @property string|null $keterangan
 * @property Carbon|null $dibaca_at
 */
#[Fillable([
    'user_id',
    'pengajuan_id',
    'kanal',
    'kejadian',
    'judul',
    'isi',
    'status',
    'jumlah_percobaan',
    'terkirim_at',
    'keterangan',
    'dibaca_at',
])]
class Notifikasi extends Model
{
    protected $table = 'notifikasis';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kanal' => KanalNotifikasi::class,
            'status' => StatusNotifikasi::class,
            'jumlah_percobaan' => 'integer',
            'terkirim_at' => 'datetime',
            'dibaca_at' => 'datetime',
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

    public function sudahDibaca(): bool
    {
        return $this->dibaca_at !== null;
    }
}
