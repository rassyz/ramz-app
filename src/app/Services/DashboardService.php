<?php

namespace App\Services;

use App\Enums\MataUang;
use App\Enums\StatusHasilPemeriksaan;
use App\Enums\StatusPengajuan;
use App\Models\HasilPemeriksaan;
use App\Models\Layanan;
use App\Models\Pembayaran;
use App\Models\Pengajuan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Agregasi data dashboard monitoring (KF-09). Seluruh method menerima
 * pengguna dan rentang periode; cakupan data ditegakkan di sini —
 * nasabah hanya memperoleh agregat pengajuannya sendiri, admin dan
 * operator memperoleh seluruh data. Komponen dashboard hanya
 * menampilkan hasil method-method ini.
 */
class DashboardService
{
    /**
     * Query dasar pengajuan dalam periode, tercakup per peran.
     */
    private function queryPengajuan(User $user, CarbonInterface $mulai, CarbonInterface $selesai): Builder
    {
        return Pengajuan::query()
            ->whereBetween('created_at', [$mulai, $selesai])
            ->when($user->isNasabah(), fn(Builder $q) => $q->where('user_id', $user->id));
    }

    /**
     * Kartu ringkasan + total pembayaran yang dikelompokkan per mata
     * uang (tidak pernah dijumlahkan lintas mata uang).
     *
     * @return array{total: int, menunggu_verifikasi: int, perlu_perbaikan: int, selesai: int, pembayaran_per_mata_uang: list<array{mata_uang: MataUang, total: float}>}
     */
    public function ringkasan(User $user, CarbonInterface $mulai, CarbonInterface $selesai): array
    {
        $dasar = $this->queryPengajuan($user, $mulai, $selesai);

        $pembayaran = Pembayaran::query()
            ->whereHas('pengajuan', function (Builder $q) use ($user, $mulai, $selesai) {
                $q->whereBetween('created_at', [$mulai, $selesai])
                    ->when($user->isNasabah(), fn(Builder $sub) => $sub->where('user_id', $user->id));
            })
            ->selectRaw('mata_uang, SUM(nominal) as total_nominal')
            ->groupBy('mata_uang')
            ->orderBy('mata_uang')
            ->get()
            ->map(fn($baris) => [
                'mata_uang' => $baris->mata_uang,
                'total' => (float) $baris->total_nominal,
            ])
            ->all();

        return [
            'total' => (clone $dasar)->count(),
            'menunggu_verifikasi' => (clone $dasar)->where('status', StatusPengajuan::MenungguVerifikasi)->count(),
            'perlu_perbaikan' => (clone $dasar)->where('status', StatusPengajuan::PerluPerbaikan)->count(),
            'selesai' => (clone $dasar)->where('status', StatusPengajuan::Selesai)->count(),
            'pembayaran_per_mata_uang' => $pembayaran,
        ];
    }

    /**
     * Jumlah pengajuan untuk setiap status beserta persentasenya.
     *
     * @return list<array{status: StatusPengajuan, jumlah: int, persen: float}>
     */
    public function distribusiStatus(User $user, CarbonInterface $mulai, CarbonInterface $selesai): array
    {
        $dasar = $this->queryPengajuan($user, $mulai, $selesai);
        $total = (clone $dasar)->count();

        return collect(StatusPengajuan::cases())->map(function (StatusPengajuan $status) use ($dasar, $total) {
            $jumlah = (clone $dasar)->where('status', $status)->count();

            return [
                'status' => $status,
                'jumlah' => $jumlah,
                'persen' => $total > 0 ? round($jumlah / $total * 100, 1) : 0.0,
            ];
        })->all();
    }

    /**
     * Tren jumlah pengajuan per bucket waktu dalam rentang. Rentang
     * sampai 62 hari digambar per hari; lebih panjang dari itu
     * diagregasi per minggu agar tetap terbaca.
     *
     * @return list<array{label: string, jumlah: int, persen_tinggi: float}>
     */
    public function tren(User $user, CarbonInterface $mulai, CarbonInterface $selesai): array
    {
        $perMinggu = $mulai->diffInDays($selesai) > 62;

        $perBucket = $this->queryPengajuan($user, $mulai, $selesai)
            ->get(['created_at'])
            ->groupBy(fn(Pengajuan $p) => $perMinggu
                ? $p->created_at->copy()->startOfWeek()->format('Y-m-d')
                : $p->created_at->format('Y-m-d'))
            ->map->count();

        $buckets = [];
        $kursor = $perMinggu ? $mulai->copy()->startOfWeek() : $mulai->copy()->startOfDay();
        $akhir = $selesai->copy()->startOfDay();

        while ($kursor->lte($akhir)) {
            $kunci = $kursor->format('Y-m-d');
            $buckets[] = [
                'label' => $kursor->format('d M'),
                'jumlah' => $perBucket[$kunci] ?? 0,
            ];
            $kursor = $kursor->addDays($perMinggu ? 7 : 1);
        }

        $maks = max(array_column($buckets, 'jumlah') ?: [0]);

        return collect($buckets)->map(fn($bucket) => [
            ...$bucket,
            'persen_tinggi' => $maks > 0 ? round($bucket['jumlah'] / $maks * 100, 1) : 0.0,
        ])->all();
    }

    /**
     * Jumlah pengajuan per jenis layanan dalam periode.
     *
     * @return list<array{layanan: Layanan, jumlah: int, persen: float}>
     */
    public function perLayanan(User $user, CarbonInterface $mulai, CarbonInterface $selesai): array
    {
        $layanans = Layanan::query()
            ->withCount(['pengajuans' => function (Builder $q) use ($user, $mulai, $selesai) {
                $q->whereBetween('created_at', [$mulai, $selesai])
                    ->when($user->isNasabah(), fn(Builder $sub) => $sub->where('user_id', $user->id));
            }])
            ->orderByDesc('pengajuans_count')
            ->orderBy('nama')
            ->get();

        $total = $layanans->sum('pengajuans_count');

        return $layanans->map(fn(Layanan $layanan) => [
            'layanan' => $layanan,
            'jumlah' => $layanan->pengajuans_count,
            'persen' => $total > 0 ? round($layanan->pengajuans_count / $total * 100, 1) : 0.0,
        ])->all();
    }

    /**
     * Ringkasan pemeriksaan Rule-Based Evidence Checker dalam periode.
     *
     * @return array{total: int, sesuai: int, tidak_sesuai: int, belum: int, persen_sesuai: float}
     */
    public function ringkasanPemeriksaan(User $user, CarbonInterface $mulai, CarbonInterface $selesai): array
    {
        $query = HasilPemeriksaan::query()
            ->whereBetween('created_at', [$mulai, $selesai])
            ->when($user->isNasabah(), fn(Builder $q) => $q->whereHas(
                'pengajuan',
                fn(Builder $sub) => $sub->where('user_id', $user->id)
            ));

        $total = (clone $query)->count();
        $sesuai = (clone $query)->where('status', StatusHasilPemeriksaan::Sesuai)->count();

        return [
            'total' => $total,
            'sesuai' => $sesuai,
            'tidak_sesuai' => (clone $query)->where('status', StatusHasilPemeriksaan::TidakSesuai)->count(),
            'belum' => (clone $query)->where('status', StatusHasilPemeriksaan::BelumDapatDiperiksa)->count(),
            'persen_sesuai' => $total > 0 ? round($sesuai / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Antrean pengajuan menunggu verifikasi untuk staf, terlama di
     * atas. Nasabah selalu memperoleh daftar kosong.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Pengajuan>
     */
    public function antreanVerifikasi(User $user)
    {
        if ($user->isNasabah()) {
            return Pengajuan::query()->whereRaw('1 = 0')->get();
        }

        return Pengajuan::query()
            ->with(['user', 'layanan'])
            ->where('status', StatusPengajuan::MenungguVerifikasi)
            ->oldest()
            ->limit(10)
            ->get();
    }

    /**
     * Pengajuan terbaru dalam periode sesuai batas tampil pengguna.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Pengajuan>
     */
    public function pengajuanTerbaru(User $user, CarbonInterface $mulai, CarbonInterface $selesai, int $batas)
    {
        return $this->queryPengajuan($user, $mulai, $selesai)
            ->with(['user', 'layanan', 'pembayaran'])
            ->latest()
            ->limit($batas)
            ->get();
    }
}
