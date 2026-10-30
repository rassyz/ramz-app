<?php

namespace Database\Seeders;

use App\Models\Layanan;
use Illuminate\Database\Seeder;

/**
 * Data awal jenis layanan beserta syarat dokumennya.
 *
 * CATATAN: daftar ini adalah data CONTOH agar sistem dapat langsung
 * digunakan dan diuji — dokumen patokan tidak merinci daftar layanan.
 * Admin dapat mengubah seluruhnya melalui halaman Master Layanan.
 * Idempoten berdasarkan kode layanan.
 */
class LayananSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $layanans = [
            [
                'kode' => 'LC-IMP',
                'nama' => 'Penerbitan L/C Impor',
                'deskripsi' => 'Penerbitan Letter of Credit untuk pembayaran transaksi impor.',
                'persyaratan_dokumen' => [
                    ['nama' => 'Invoice', 'wajib' => true],
                    ['nama' => 'Packing List', 'wajib' => true],
                    ['nama' => 'Purchase Order', 'wajib' => true],
                ],
            ],
            [
                'kode' => 'KOL-EKS',
                'nama' => 'Koleksi Dokumen Ekspor',
                'deskripsi' => 'Penanganan dokumen ekspor melalui documentary collection.',
                'persyaratan_dokumen' => [
                    ['nama' => 'Invoice', 'wajib' => true],
                    ['nama' => 'Packing List', 'wajib' => true],
                    ['nama' => 'Bill of Lading', 'wajib' => true],
                ],
            ],
            [
                'kode' => 'TRF-VALAS',
                'nama' => 'Transfer Valas / Remittance',
                'deskripsi' => 'Pengiriman dana valuta asing ke penerima di luar negeri.',
                'persyaratan_dokumen' => [
                    ['nama' => 'Invoice', 'wajib' => true],
                    ['nama' => 'Formulir Transfer', 'wajib' => true],
                ],
            ],
            [
                'kode' => 'IMP-OA',
                'nama' => 'Pembayaran Impor Non-L/C',
                'deskripsi' => 'Pembayaran impor secara open account tanpa Letter of Credit.',
                'persyaratan_dokumen' => [
                    ['nama' => 'Invoice', 'wajib' => true],
                    ['nama' => 'Bill of Lading', 'wajib' => true],
                    ['nama' => 'Packing List', 'wajib' => true],
                ],
            ],
        ];

        foreach ($layanans as $layanan) {
            Layanan::updateOrCreate(
                ['kode' => $layanan['kode']],
                [
                    'nama' => $layanan['nama'],
                    'deskripsi' => $layanan['deskripsi'],
                    'persyaratan_dokumen' => $layanan['persyaratan_dokumen'],
                    'is_active' => true,
                ]
            );
        }
    }
}
