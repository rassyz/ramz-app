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
        // Fase 11 (info langsung Rama 14 Nov 2026): layanan hanya dua —
        // Ekspor & Impor, set dokumennya sama; yang berbeda asal
        // dokumennya (Ekspor dari nasabah, Impor dari jasa pengiriman).
        $layanans = [
            [
                'kode' => 'EKS',
                'nama' => 'Layanan Ekspor',
                'deskripsi' => 'Penanganan dokumen ekspor nasabah; LC yang terbit menjadi patokan pemeriksaan dokumen.',
                'persyaratan_dokumen' => [
                    ['nama' => 'Draft', 'wajib' => true],
                    ['nama' => 'Invoice', 'wajib' => true],
                    ['nama' => 'Packing List', 'wajib' => true],
                    ['nama' => 'Bill of Lading', 'wajib' => true],
                    ['nama' => 'Asuransi', 'wajib' => true],
                    ['nama' => 'Sertifikat', 'wajib' => false],
                    ['nama' => 'Pre Order', 'wajib' => false],
                    ['nama' => 'Surat Jalan', 'wajib' => false],
                ],
            ],
            [
                'kode' => 'IMP',
                'nama' => 'Layanan Impor',
                'deskripsi' => 'Penanganan dokumen impor yang diterima dari jasa pengiriman untuk diteruskan ke nasabah.',
                'persyaratan_dokumen' => [
                    ['nama' => 'Draft', 'wajib' => true],
                    ['nama' => 'Invoice', 'wajib' => true],
                    ['nama' => 'Packing List', 'wajib' => true],
                    ['nama' => 'Bill of Lading', 'wajib' => true],
                    ['nama' => 'Asuransi', 'wajib' => true],
                    ['nama' => 'Sertifikat', 'wajib' => false],
                    ['nama' => 'Pre Order', 'wajib' => false],
                    ['nama' => 'Surat Jalan', 'wajib' => false],
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
