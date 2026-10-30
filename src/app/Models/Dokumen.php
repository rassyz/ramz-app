<?php

namespace App\Models;

use App\Enums\MataUang;
use App\Models\Pengajuan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dokumen pendukung yang diunggah nasabah untuk sebuah pengajuan
 * (KF-05). Pada dokumen Invoice tersimpan pula data terstruktur yang
 * tertera pada dokumen, sebagai bahan pencocokan checker (KF-06).
 *
 * @property int $id
 * @property int $pengajuan_id
 * @property string $jenis_dokumen
 * @property string $nama_file_asli
 * @property string $path_file
 * @property string|null $mime
 * @property int|null $ukuran_file
 * @property int $diupload_oleh
 * @property string|null $doc_nomor_invoice
 * @property string|null $doc_identitas_nasabah
 * @property string|null $doc_nominal
 * @property MataUang|null $doc_mata_uang
 * @property string|null $doc_jenis_layanan
 */
#[Fillable([
    'pengajuan_id',
    'jenis_dokumen',
    'nama_file_asli',
    'path_file',
    'mime',
    'ukuran_file',
    'diupload_oleh',
    'doc_nomor_invoice',
    'doc_identitas_nasabah',
    'doc_nominal',
    'doc_mata_uang',
    'doc_jenis_layanan',
])]
class Dokumen extends Model
{
    protected $table = 'dokumens';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ukuran_file' => 'integer',
            'doc_nominal' => 'decimal:2',
            'doc_mata_uang' => MataUang::class,
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
     * @return BelongsTo<User, $this>
     */
    public function diuploadOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diupload_oleh');
    }

    /**
     * Apakah dokumen ini adalah dokumen bukti Invoice — penentu apakah
     * data terstruktur dokumen diisi dan dipakai untuk pencocokan.
     */
    public function isDokumenInvoice(): bool
    {
        return str_contains(strtolower($this->jenis_dokumen), 'invoice');
    }

    /**
     * Ukuran berkas terformat untuk tampilan.
     */
    public function ukuranFormatted(): string
    {
        if ($this->ukuran_file === null) {
            return '-';
        }

        if ($this->ukuran_file >= 1048576) {
            return number_format($this->ukuran_file / 1048576, 2, ',', '.') . ' MB';
        }

        return number_format($this->ukuran_file / 1024, 1, ',', '.') . ' KB';
    }
}
