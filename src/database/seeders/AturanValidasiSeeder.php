<?php

namespace Database\Seeders;

use App\Enums\KategoriAturan;
use App\Models\AturanValidasi;
use Illuminate\Database\Seeder;

/**
 * Aturan awal Rule-Based Evidence Checker: satu aturan kelengkapan
 * dokumen dan lima aturan pencocokan field terstruktur, sesuai
 * spesifikasi checker pada dokumen patokan. Idempoten per kode aturan;
 * Admin dapat mengaktifkan/menonaktifkan aturan dari halaman
 * Aturan Validasi tanpa mengubah isi seeder ini.
 */
class AturanValidasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $aturan = [
            [
                'kode' => 'KELENGKAPAN-DOKUMEN',
                'nama' => 'Kelengkapan Dokumen Wajib',
                'kategori' => KategoriAturan::Kelengkapan,
                'field' => null,
                'keterangan' => 'Setiap dokumen wajib pada persyaratan jenis layanan harus sudah terunggah oleh nasabah.',
            ],
            [
                'kode' => 'COCOK-NOMOR-INVOICE',
                'nama' => 'Pencocokan Nomor Invoice',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nomor_invoice',
                'keterangan' => 'Nomor invoice pada dokumen harus sama dengan nomor invoice pada pengajuan.',
            ],
            [
                'kode' => 'COCOK-IDENTITAS',
                'nama' => 'Pencocokan Identitas Nasabah',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'identitas_nasabah',
                'keterangan' => 'Identitas nasabah pada dokumen harus sama dengan nama nasabah pengaju.',
            ],
            [
                'kode' => 'COCOK-NOMINAL',
                'nama' => 'Pencocokan Nominal Pembayaran',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nominal',
                'keterangan' => 'Nominal pada dokumen harus sama dengan nominal pembayaran pengajuan (toleransi selisih 0,01).',
            ],
            [
                'kode' => 'COCOK-MATA-UANG',
                'nama' => 'Pencocokan Mata Uang',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'mata_uang',
                'keterangan' => 'Mata uang pada dokumen harus sama dengan mata uang pembayaran pengajuan.',
            ],
            [
                'kode' => 'COCOK-JENIS-LAYANAN',
                'nama' => 'Pencocokan Jenis Layanan',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'jenis_layanan',
                'keterangan' => 'Jenis layanan pada dokumen harus sama dengan jenis layanan yang dipilih pada pengajuan.',
            ],
        ];

        foreach ($aturan as $item) {
            AturanValidasi::updateOrCreate(
                ['kode' => $item['kode']],
                [
                    'nama' => $item['nama'],
                    'kategori' => $item['kategori'],
                    'field' => $item['field'],
                    'keterangan' => $item['keterangan'],
                    'is_active' => true,
                ]
            );
        }
    }
}
