<?php

namespace App\Services;

use App\Models\Pengajuan;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Penyusunan laporan periode untuk Admin (KF-13). Angka ringkasan
 * memakai kembali DashboardService (Fase 5) untuk pengguna Admin —
 * satu sumber kebenaran, sehingga angka laporan dan dashboard pada
 * periode yang sama selalu sama. Service ini menambahkan daftar
 * rinci pengajuan pada periode. (Fase 11: rincian pembayaran
 * pensiun mengikuti model LC — fokus sistem adalah dokumen.)
 */
class LaporanService
{
    public function __construct(
        private DashboardService $dashboard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buat(User $admin, CarbonInterface $mulai, CarbonInterface $selesai): array
    {
        return [
            'ringkasan' => $this->dashboard->ringkasan($admin, $mulai, $selesai),
            'distribusi' => $this->dashboard->distribusiStatus($admin, $mulai, $selesai),
            'per_layanan' => $this->dashboard->perLayanan($admin, $mulai, $selesai),
            'pemeriksaan' => $this->dashboard->ringkasanPemeriksaan($admin, $mulai, $selesai),
            'pengajuans' => $this->daftarPengajuan($mulai, $selesai),
        ];
    }

    /**
     * Daftar rinci pengajuan yang dibuat dalam periode, untuk
     * pratinjau laporan dan unduhan CSV (sumber yang sama).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Pengajuan>
     */
    public function daftarPengajuan(CarbonInterface $mulai, CarbonInterface $selesai)
    {
        return Pengajuan::query()
            ->with(['user', 'layanan'])
            ->whereBetween('created_at', [$mulai, $selesai])
            ->oldest()
            ->get();
    }

    /**
     * Varian terpaginasi dari daftarPengajuan() untuk tabel rinci
     * di halaman Laporan; kueri dan urutannya sama persis.
     */
    public function daftarPengajuanPaginasi(CarbonInterface $mulai, CarbonInterface $selesai)
    {
        return Pengajuan::query()
            ->with(['user', 'layanan'])
            ->whereBetween('created_at', [$mulai, $selesai])
            ->oldest()
            ->paginate(10);
    }
}
