<?php

namespace App\Models;

use App\Enums\MataUang;
use App\Enums\StatusPembayaran;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Informasi pembayaran yang terhubung dengan satu pengajuan (KF-04).
 *
 * @property int $id
 * @property int $pengajuan_id
 * @property string $nominal
 * @property MataUang $mata_uang
 * @property Carbon|null $tanggal_pembayaran
 * @property StatusPembayaran $status
 */
#[Fillable(['pengajuan_id', 'nominal', 'mata_uang', 'tanggal_pembayaran', 'status'])]
class Pembayaran extends Model
{
    protected $table = 'pembayarans';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'mata_uang' => MataUang::class,
            'tanggal_pembayaran' => 'date',
            'status' => StatusPembayaran::class,
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
     * Nominal terformat beserta mata uang, untuk tampilan.
     */
    public function nominalFormatted(): string
    {
        return $this->mata_uang->value.' '.number_format((float) $this->nominal, 2, ',', '.');
    }
}
