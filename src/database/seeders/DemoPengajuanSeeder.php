<?php

namespace Database\Seeders;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPengajuan;
use App\Models\Layanan;
use App\Models\Pengajuan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tiga pengajuan contoh untuk akun nasabah demo (nasabah@rama.test)
 * dengan status bervariasi, lengkap beserta pembayaran dan riwayat
 * status awalnya — bahan pengujian TC-03/TC-04/TC-08 dan demonstrasi.
 *
 * Idempoten: dilewati bila nasabah demo sudah memiliki pengajuan.
 * Bergantung pada DemoUsersSeeder dan LayananSeeder yang sudah jalan.
 */
class DemoPengajuanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $nasabah = User::where('email', 'nasabah@rama.test')->first();
        $operator = User::where('email', 'operator@rama.test')->first();

        if ($nasabah === null || $nasabah->pengajuans()->exists()) {
            return;
        }

        $lcImpor = Layanan::where('kode', 'LC-IMP')->first();
        $remittance = Layanan::where('kode', 'TRF-VALAS')->first();
        $openAccount = Layanan::where('kode', 'IMP-OA')->first();

        if ($lcImpor === null || $remittance === null || $openAccount === null) {
            return;
        }

        // 1) Pengajuan baru yang masih menunggu verifikasi.
        $this->buatPengajuan(
            $nasabah,
            $lcImpor,
            'INV-2026-001',
            12500,
            'USD',
            StatusPengajuan::MenungguVerifikasi,
            StatusPembayaran::SudahBayar,
            null,
            null
        );

        // 2) Pengajuan yang sudah terverifikasi oleh operator.
        $this->buatPengajuan(
            $nasabah,
            $remittance,
            'INV-2026-002',
            3200,
            'EUR',
            StatusPengajuan::Terverifikasi,
            StatusPembayaran::Terverifikasi,
            null,
            $operator
        );

        // 3) Pengajuan yang dikembalikan untuk perbaikan.
        $this->buatPengajuan(
            $nasabah,
            $openAccount,
            'INV-2026-003',
            8700,
            'SGD',
            StatusPengajuan::PerluPerbaikan,
            StatusPembayaran::MenungguPembayaran,
            'Nomor invoice pada dokumen belum sesuai dengan data pengajuan.',
            $operator
        );
    }

    private function buatPengajuan(
        User $nasabah,
        Layanan $layanan,
        string $nomorInvoice,
        float $nominal,
        string $mataUang,
        StatusPengajuan $status,
        StatusPembayaran $statusPembayaran,
        ?string $catatan,
        ?User $operator
    ): void {
        DB::transaction(function () use ($nasabah, $layanan, $nomorInvoice, $nominal, $mataUang, $status, $statusPembayaran, $catatan, $operator) {
            $pengajuan = Pengajuan::create([
                'nomor_pengajuan' => Pengajuan::generateNomorPengajuan(),
                'user_id' => $nasabah->id,
                'layanan_id' => $layanan->id,
                'nomor_invoice' => $nomorInvoice,
                'status' => $status,
                'catatan' => $catatan,
            ]);

            $pengajuan->pembayaran()->create([
                'nominal' => $nominal,
                'mata_uang' => $mataUang,
                'status' => $statusPembayaran,
            ]);

            $pengajuan->riwayatStatuses()->create([
                'status_dari' => null,
                'status_ke' => StatusPengajuan::MenungguVerifikasi,
                'catatan' => 'Pengajuan dibuat.',
                'changed_by' => $nasabah->id,
            ]);

            if ($status !== StatusPengajuan::MenungguVerifikasi) {
                $pengajuan->riwayatStatuses()->create([
                    'status_dari' => StatusPengajuan::MenungguVerifikasi,
                    'status_ke' => $status,
                    'catatan' => $catatan,
                    'changed_by' => $operator?->id,
                ]);
            }
        });
    }
}
