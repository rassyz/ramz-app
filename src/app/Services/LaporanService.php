<?php

namespace App\Services;

use App\Enums\StatusPembayaran;
use App\Models\Pembayaran;
use App\Models\Pengajuan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Penyusunan laporan periode untuk Admin (KF-13). Angka ringkasan
 * memakai kembali DashboardService (Fase 5) untuk pengguna Admin —
 * satu sumber kebenaran, sehingga angka laporan dan dashboard pada
 * periode yang sama selalu sama. Service ini menambahkan rincian
 * pembayaran per status dan daftar rinci pengajuan pada periode.
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
            'pembayaran_per_status' => $this->pembayaranPerStatus($mulai, $selesai),
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
            ->with(['user', 'layanan', 'pembayaran'])
            ->whereBetween('created_at', [$mulai, $selesai])
            ->oldest()
            ->get();
    }

    /**
     * Jumlah pembayaran dan total nominal per mata uang, dikelompokkan
     * per status pembayaran. Total tidak pernah dijumlah lintas mata
     * uang (konsisten dengan dashboard Fase 5).
     *
     * @return list<array{status: StatusPembayaran, jumlah: int, total_per_mata_uang: list<array{mata_uang: \App\Enums\MataUang, total: float}>}>
     */
    public function pembayaranPerStatus(CarbonInterface $mulai, CarbonInterface $selesai): array
    {
        $hasil = [];

        foreach (StatusPembayaran::cases() as $status) {
            $query = Pembayaran::query()
                ->where('status', $status)
                ->whereHas('pengajuan', fn(Builder $q) => $q->whereBetween('created_at', [$mulai, $selesai]));

            $totalPerMataUang = (clone $query)
                ->selectRaw('mata_uang, SUM(nominal) as total_nominal')
                ->groupBy('mata_uang')
                ->orderBy('mata_uang')
                ->get()
                ->map(fn($baris) => ['mata_uang' => $baris->mata_uang, 'total' => (float) $baris->total_nominal])
                ->all();

            $hasil[] = [
                'status' => $status,
                'jumlah' => $query->count(),
                'total_per_mata_uang' => $totalPerMataUang,
            ];
        }

        return $hasil;
    }
}
