<?php

namespace App\Models;

use App\Enums\StatusPengajuan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan setiap perubahan status pengajuan (KF-08), ditulis saat
 * pengajuan dibuat dan pada setiap perubahan status berikutnya.
 *
 * @property int $id
 * @property int $pengajuan_id
 * @property StatusPengajuan|null $status_dari
 * @property StatusPengajuan $status_ke
 * @property string|null $catatan
 * @property int|null $changed_by
 */
#[Fillable(['pengajuan_id', 'status_dari', 'status_ke', 'catatan', 'changed_by'])]
class RiwayatStatus extends Model
{
    protected $table = 'riwayat_statuses';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_dari' => StatusPengajuan::class,
            'status_ke' => StatusPengajuan::class,
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
     * Pengguna yang melakukan perubahan status (null = oleh sistem).
     *
     * @return BelongsTo<User, $this>
     */
    public function diubahOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
