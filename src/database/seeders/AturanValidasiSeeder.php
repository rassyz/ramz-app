<?php

namespace Database\Seeders;

use App\Enums\KategoriAturan;
use App\Models\AturanValidasi;
use Illuminate\Database\Seeder;

/**
 * Aturan Rule-Based Evidence Checker v2 (Fase 11): satu aturan
 * kelengkapan umum + aturan pencocokan PER JENIS DOKUMEN WAJIB,
 * masing-masing dengan referensi data acuan LC yang menjadi
 * pembandingnya. Idempoten per kode aturan. Aturan pencocokan versi
 * lama (tanpa jenis dokumen) dinonaktifkan agar tidak lagi tampil
 * aktif di halaman Aturan Validasi.
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
                'jenis_dokumen' => null,
                'referensi' => null,
                'keterangan' => 'Setiap dokumen wajib pada snapshot persyaratan pengajuan harus sudah terunggah.',
            ],
            [
                'kode' => 'COCOK-INV-NOMOR-INVOICE',
                'nama' => 'Pencocokan Nomor Invoice (Invoice)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nomor_invoice',
                'jenis_dokumen' => 'Invoice',
                'referensi' => 'nomor_invoice',
                'keterangan' => 'Nomor invoice pada dokumen Invoice sama dengan nomor invoice pengajuan.',
            ],
            [
                'kode' => 'COCOK-INV-NOMOR-LC',
                'nama' => 'Pencocokan Nomor LC (Invoice)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nomor_lc',
                'jenis_dokumen' => 'Invoice',
                'referensi' => 'nomor_lc',
                'keterangan' => 'Nomor LC/SKBDN yang tercantum pada Invoice sama dengan nomor LC acuan.',
            ],
            [
                'kode' => 'COCOK-INV-NOMOR-PO',
                'nama' => 'Pencocokan Nomor PO (Invoice)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nomor_po',
                'jenis_dokumen' => 'Invoice',
                'referensi' => 'nomor_po',
                'keterangan' => 'Nomor purchase order pada Invoice sama dengan nomor PO acuan.',
            ],
            [
                'kode' => 'COCOK-INV-NOMINAL',
                'nama' => 'Pencocokan Nominal (Invoice)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nominal',
                'jenis_dokumen' => 'Invoice',
                'referensi' => 'nominal_acuan',
                'keterangan' => 'Nominal Invoice dibandingkan dengan nominal acuan dalam toleransi persen LC.',
            ],
            [
                'kode' => 'COCOK-INV-MATA-UANG',
                'nama' => 'Pencocokan Mata Uang (Invoice)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'mata_uang',
                'jenis_dokumen' => 'Invoice',
                'referensi' => 'mata_uang_acuan',
                'keterangan' => 'Mata uang Invoice sama dengan mata uang acuan.',
            ],
            [
                'kode' => 'COCOK-INV-IDENTITAS',
                'nama' => 'Pencocokan Identitas Nasabah (Invoice)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'identitas_nasabah',
                'jenis_dokumen' => 'Invoice',
                'referensi' => 'nasabah',
                'keterangan' => 'Identitas penjual/beneficiary pada Invoice sama dengan nama nasabah pengaju.',
            ],
            [
                'kode' => 'COCOK-DRAFT-NOMINAL',
                'nama' => 'Pencocokan Nominal (Draft)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nominal',
                'jenis_dokumen' => 'Draft',
                'referensi' => 'nominal_acuan',
                'keterangan' => 'Nominal Draft (wesel) dibandingkan dengan nominal acuan dalam toleransi persen LC.',
            ],
            [
                'kode' => 'COCOK-DRAFT-MATA-UANG',
                'nama' => 'Pencocokan Mata Uang (Draft)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'mata_uang',
                'jenis_dokumen' => 'Draft',
                'referensi' => 'mata_uang_acuan',
                'keterangan' => 'Mata uang Draft sama dengan mata uang acuan.',
            ],
            [
                'kode' => 'COCOK-SJ-NOMOR-LC',
                'nama' => 'Pencocokan Nomor LC (Surat Jalan)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nomor_lc',
                'jenis_dokumen' => 'Surat Jalan',
                'referensi' => 'nomor_lc',
                'keterangan' => 'Nomor LC/SKBDN pada Surat Jalan sama dengan nomor LC acuan.',
            ],
            [
                'kode' => 'COCOK-SJ-NOMOR-PO',
                'nama' => 'Pencocokan Nomor PO (Surat Jalan)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nomor_po',
                'jenis_dokumen' => 'Surat Jalan',
                'referensi' => 'nomor_po',
                'keterangan' => 'Nomor purchase order pada Surat Jalan sama dengan nomor PO acuan.',
            ],
            [
                'kode' => 'COCOK-SJ-TANGGAL',
                'nama' => 'Pemeriksaan Tanggal (Surat Jalan)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'tanggal_dokumen',
                'jenis_dokumen' => 'Surat Jalan',
                'referensi' => 'tanggal_kedaluwarsa_lc',
                'keterangan' => 'Tanggal Surat Jalan tidak melampaui tanggal kedaluwarsa LC.',
            ],
            [
                'kode' => 'COCOK-PL-KUANTITAS',
                'nama' => 'Pencocokan Kuantitas (Packing List)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'kuantitas',
                'jenis_dokumen' => 'Packing List',
                'referensi' => 'kuantitas_acuan',
                'keterangan' => 'Kuantitas pada Packing List dibandingkan dengan kuantitas acuan dalam toleransi persen LC.',
            ],
            [
                'kode' => 'COCOK-BL-PENGIRIM',
                'nama' => 'Pencocokan Pengirim (Bill of Lading)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'identitas_pengirim',
                'jenis_dokumen' => 'Bill of Lading',
                'referensi' => 'nasabah',
                'keterangan' => 'Identitas pengirim (shipper) pada Bill of Lading sama dengan nama nasabah pengaju.',
            ],
            [
                'kode' => 'COCOK-ASR-NOMINAL',
                'nama' => 'Pencocokan Nilai Pertanggungan (Asuransi)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nominal',
                'jenis_dokumen' => 'Asuransi',
                'referensi' => 'nominal_acuan',
                'keterangan' => 'Nilai pertanggungan Asuransi sekurang-kurangnya sebesar nominal acuan.',
            ],
            [
                'kode' => 'COCOK-FP-NOMOR-LC',
                'nama' => 'Pencocokan Nomor LC (Faktur Pajak)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nomor_lc',
                'jenis_dokumen' => 'Faktur Pajak',
                'referensi' => 'nomor_lc',
                'keterangan' => 'Nomor LC/SKBDN yang tercantum pada Faktur Pajak sama dengan nomor LC acuan.',
            ],
            [
                'kode' => 'COCOK-FP-NOMOR-INVOICE',
                'nama' => 'Pencocokan Nomor Invoice (Faktur Pajak)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nomor_invoice',
                'jenis_dokumen' => 'Faktur Pajak',
                'referensi' => 'nomor_invoice',
                'keterangan' => 'Nomor invoice yang dirujuk Faktur Pajak sama dengan nomor invoice pengajuan.',
            ],
            [
                'kode' => 'COCOK-FP-NOMINAL',
                'nama' => 'Pencocokan Nominal (Faktur Pajak)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'nominal',
                'jenis_dokumen' => 'Faktur Pajak',
                'referensi' => 'nominal_acuan',
                'keterangan' => 'Total Faktur Pajak (DPP + PPN) dan mata uangnya sesuai nominal serta mata uang acuan (nominal dalam toleransi LC).',
            ],
            [
                'kode' => 'COCOK-FP-IDENTITAS',
                'nama' => 'Pencocokan Identitas Penjual (Faktur Pajak)',
                'kategori' => KategoriAturan::Pencocokan,
                'field' => 'identitas_nasabah',
                'jenis_dokumen' => 'Faktur Pajak',
                'referensi' => 'nasabah',
                'keterangan' => 'Identitas penjual (PKP) pada Faktur Pajak sama dengan nama nasabah pengaju.',
            ],
        ];

        foreach ($aturan as $item) {
            AturanValidasi::updateOrCreate(
                ['kode' => $item['kode']],
                [
                    'nama' => $item['nama'],
                    'kategori' => $item['kategori'],
                    'field' => $item['field'],
                    'jenis_dokumen' => $item['jenis_dokumen'],
                    'referensi' => $item['referensi'],
                    'keterangan' => $item['keterangan'],
                    'is_active' => true,
                ]
            );
        }

        // Aturan mata uang Faktur Pajak yang sempat berdiri sendiri
        // digabungkan ke aturan nominal Faktur Pajak (Perbaikan 5).
        AturanValidasi::where('kode', 'COCOK-FP-MATA-UANG')
            ->update(['is_active' => false]);

        // Aturan pencocokan v1 (berbasis Invoice saja, tanpa jenis
        // dokumen) pensiun pada checker v2.
        AturanValidasi::where('kategori', KategoriAturan::Pencocokan)
            ->whereNull('jenis_dokumen')
            ->where('kode', '!=', 'KELENGKAPAN-DOKUMEN')
            ->update(['is_active' => false]);
    }
}
