<?php

use App\Enums\PeriodeDashboard;
use App\Models\PreferensiDashboard;
use App\Services\DashboardService;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    // Filter periode aktif
    public string $periode = '30_hari';

    public string $tanggalMulai = '';

    public string $tanggalSelesai = '';

    // Panel personalisasi (KF-10)
    public bool $panelPersonalisasi = false;

    public bool $tampilkanTren = true;

    public bool $tampilkanDistribusiStatus = true;

    public bool $tampilkanPerLayanan = true;

    public bool $tampilkanPemeriksaan = true;

    public bool $tampilkanAntrean = true;

    public string $periodeDefault = '30_hari';

    public int $batasTerbaru = 5;

    public function mount(): void
    {
        $preferensi = auth()->user()->preferensiDashboardAtauBawaan();

        $this->tampilkanTren = $preferensi->tampilkan_tren;
        $this->tampilkanDistribusiStatus = $preferensi->tampilkan_distribusi_status;
        $this->tampilkanPerLayanan = $preferensi->tampilkan_per_layanan;
        $this->tampilkanPemeriksaan = $preferensi->tampilkan_pemeriksaan;
        $this->tampilkanAntrean = $preferensi->tampilkan_antrean;
        $this->periodeDefault = $preferensi->periode_default->value;
        $this->batasTerbaru = $preferensi->batas_terbaru;
        $this->periode = $this->periodeDefault;
    }

    /**
     * Rentang tanggal aktif dari filter periode (preset atau khusus).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    #[Computed]
    public function rentang(): array
    {
        if ($this->periode === 'khusus' && $this->tanggalMulai && $this->tanggalSelesai) {
            return [
                Carbon::parse($this->tanggalMulai)->startOfDay(),
                Carbon::parse($this->tanggalSelesai)->endOfDay(),
            ];
        }

        $preset = PeriodeDashboard::tryFrom($this->periode) ?? PeriodeDashboard::TigaPuluhHari;

        return $preset->rentang();
    }

    #[Computed]
    public function ringkasan()
    {
        [$mulai, $selesai] = $this->rentang;

        return app(DashboardService::class)->ringkasan(auth()->user(), $mulai, $selesai);
    }

    #[Computed]
    public function distribusi()
    {
        [$mulai, $selesai] = $this->rentang;

        return app(DashboardService::class)->distribusiStatus(auth()->user(), $mulai, $selesai);
    }

    #[Computed]
    public function tren()
    {
        [$mulai, $selesai] = $this->rentang;

        return app(DashboardService::class)->tren(auth()->user(), $mulai, $selesai);
    }

    #[Computed]
    public function perLayanan()
    {
        [$mulai, $selesai] = $this->rentang;

        return app(DashboardService::class)->perLayanan(auth()->user(), $mulai, $selesai);
    }

    #[Computed]
    public function pemeriksaan()
    {
        [$mulai, $selesai] = $this->rentang;

        return app(DashboardService::class)->ringkasanPemeriksaan(auth()->user(), $mulai, $selesai);
    }

    #[Computed]
    public function aturanGagal()
    {
        [$mulai, $selesai] = $this->rentang;

        return app(DashboardService::class)->aturanSeringGagal(auth()->user(), $mulai, $selesai);
    }

    #[Computed]
    public function lacak()
    {
        return app(DashboardService::class)->lacakTerbaru(auth()->user());
    }

    #[Computed]
    public function perluPerbaikan()
    {
        return app(DashboardService::class)->perluPerbaikan(auth()->user());
    }

    #[Computed]
    public function jumlahAktif()
    {
        return app(DashboardService::class)->jumlahAktif(auth()->user());
    }

    #[Computed]
    public function antrean()
    {
        return app(DashboardService::class)->antreanVerifikasi(auth()->user());
    }

    #[Computed]
    public function terbaru()
    {
        [$mulai, $selesai] = $this->rentang;

        return app(DashboardService::class)->pengajuanTerbaru(auth()->user(), $mulai, $selesai, $this->batasTerbaru);
    }

    public function simpanPersonalisasi(): void
    {
        $this->validate([
            'periodeDefault' => ['required', Rule::enum(PeriodeDashboard::class)],
            'batasTerbaru' => ['required', 'integer', Rule::in([5, 10, 15])],
        ]);

        auth()->user()->preferensiDashboard()->updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'tampilkan_tren' => $this->tampilkanTren,
                'tampilkan_distribusi_status' => $this->tampilkanDistribusiStatus,
                'tampilkan_per_layanan' => $this->tampilkanPerLayanan,
                'tampilkan_pemeriksaan' => $this->tampilkanPemeriksaan,
                'tampilkan_antrean' => $this->tampilkanAntrean,
                'periode_default' => $this->periodeDefault,
                'batas_terbaru' => $this->batasTerbaru,
            ]
        );

        $this->periode = $this->periodeDefault;
        $this->panelPersonalisasi = false;

        Flux::toast(variant: 'success', text: 'Personalisasi dashboard berhasil disimpan.');
    }

    public function kembalikanBawaan(): void
    {
        auth()->user()->preferensiDashboard()->delete();

        $bawaan = new PreferensiDashboard;
        $this->tampilkanTren = $bawaan->tampilkan_tren;
        $this->tampilkanDistribusiStatus = $bawaan->tampilkan_distribusi_status;
        $this->tampilkanPerLayanan = $bawaan->tampilkan_per_layanan;
        $this->tampilkanPemeriksaan = $bawaan->tampilkan_pemeriksaan;
        $this->tampilkanAntrean = $bawaan->tampilkan_antrean;
        $this->periodeDefault = $bawaan->periode_default->value;
        $this->batasTerbaru = $bawaan->batas_terbaru;
        $this->periode = $this->periodeDefault;
        $this->panelPersonalisasi = false;

        Flux::toast(variant: 'success', text: 'Personalisasi dikembalikan ke bawaan.');
    }

    #[Computed]
    public function delta()
    {
        [$mulai, $selesai] = $this->rentang;

        return app(DashboardService::class)->perubahanPeriode(auth()->user(), $mulai, $selesai);
    }

    #[Computed]
    public function jumlahBelumDibaca(): int
    {
        return auth()->user()->notifikasis()->whereNull('dibaca_at')->count();
    }
}; ?>

<div class="flex h-full w-full min-w-0 flex-1 flex-col gap-5 rounded-xl">
    {{-- Baris judul + kontrol periode berbentuk chip (selaras mockup) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">
                @unless (auth()->user()->isNasabah())
                    Dashboard
                @else
                    Selamat datang, {{ auth()->user()->name }}
                @endunless
            </h1>
            <p class="mt-1 text-[13px] text-mist dark:text-zinc-400">
                {{ now()->translatedFormat('l, d F Y') }} —
                @unless (auth()->user()->isNasabah())
                    Selamat bekerja, {{ auth()->user()->role->label() }}.
                @else
                    pantau pengajuan dan perbaikan dokumen Anda.
                @endunless
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="button" wire:click="$toggle('panelPersonalisasi')" class="btn">Personalisasi</button>
            @unless (auth()->user()->isNasabah())
                <a href="{{ route('pengajuans.index') }}" class="btn btn-primary" wire:navigate>+ Buat Pengajuan</a>
            @endunless
            <a href="{{ route('notifikasis.index') }}" wire:navigate class="relative hidden size-10 items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Notifikasi" title="Notifikasi">
                <flux:icon name="bell" class="size-5" />
                @if ($this->jumlahBelumDibaca > 0)
                    <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $this->jumlahBelumDibaca }}</span>
                @endif
            </a>
            <button type="button" x-data x-on:click="$flux.dark = !$flux.dark" class="hidden size-10 cursor-pointer items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Ganti tema gelap atau terang" title="Mode gelap / terang">
                <flux:icon.moon class="size-5 dark:hidden" />
                <flux:icon.sun class="hidden size-5 dark:block" />
            </button>
        </div>
    </div>

    {{-- Baris filter periode: chip preset + tanggal khusus (baris sendiri agar tidak berebut tempat dengan judul di lebar laptop) --}}
    <div class="flex flex-wrap items-end gap-2">
        <div class="flex max-w-full flex-nowrap items-center gap-0.5 overflow-x-auto rounded-full border border-line bg-white p-1 scrollbar-none dark:border-night-line dark:bg-night-tile [&::-webkit-scrollbar]:hidden">
            @foreach (PeriodeDashboard::cases() as $preset)
                <button type="button" wire:click="$set('periode', '{{ $preset->value }}')" class="shrink-0 rounded-full px-3.25 py-1.5 text-[13px] whitespace-nowrap {{ $periode === $preset->value ? 'bg-primary font-semibold text-white' : 'font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}">{{ $preset->label() }}</button>
            @endforeach
            <button type="button" wire:click="$set('periode', 'khusus')" class="shrink-0 rounded-full px-3.25 py-1.5 text-[13px] whitespace-nowrap {{ $periode === 'khusus' ? 'bg-primary font-semibold text-white' : 'font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' }}">Khusus</button>
        </div>
        @if ($periode === 'khusus')
            <flux:input wire:model.live="tanggalMulai" type="date" label="Mulai" class="w-full sm:w-44" />
            <flux:input wire:model.live="tanggalSelesai" type="date" label="Selesai" class="w-full sm:w-44" />
        @endif
    </div>

    @if ($panelPersonalisasi)
        <form wire:submit="simpanPersonalisasi" class="tile space-y-4">
            <flux:heading level="2">Personalisasi Dashboard</flux:heading>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <flux:checkbox wire:model="tampilkanTren" label="Grafik Tren Pengajuan" />
                <flux:checkbox wire:model="tampilkanDistribusiStatus" label="Distribusi Status" />
                <flux:checkbox wire:model="tampilkanPerLayanan" label="Pengajuan per Layanan" />
                <flux:checkbox wire:model="tampilkanPemeriksaan" label="Ringkasan Pemeriksaan Checker" />
                @unless (auth()->user()->isNasabah())
                    <flux:checkbox wire:model="tampilkanAntrean" label="Antrean Verifikasi" />
                @endunless
            </div>
            <div class="flex flex-wrap gap-4">
                <flux:select wire:model="periodeDefault" label="Periode Bawaan" class="max-w-56">
                    @foreach (PeriodeDashboard::cases() as $preset)
                        <option value="{{ $preset->value }}">{{ $preset->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="batasTerbaru" label="Jumlah Pengajuan Terbaru" class="max-w-56">
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="15">15</option>
                </flux:select>
            </div>
            <div class="flex gap-2">
                <flux:button variant="primary" type="submit">Simpan Personalisasi</flux:button>
                <flux:button wire:click="kembalikanBawaan">Kembalikan ke Bawaan</flux:button>
            </div>
        </form>
    @endif

    @php
        $labelPeriode = $periode === 'khusus'
            ? 'rentang khusus'
            : strtolower(PeriodeDashboard::tryFrom($periode)?->label() ?? '');
        $labelSingkat = match ($periode) {
            '7_hari' => '7 hari',
            '30_hari' => '30 hari',
            'bulan_ini' => 'bulan ini',
            'bulan_lalu' => 'bulan lalu',
            'semua' => 'semua waktu',
            default => 'rentang khusus',
        };
        $pillStatus = [
            'menunggu_verifikasi' => 'pill-amber',
            'perlu_perbaikan' => 'pill-red',
            'terverifikasi' => 'pill-indigo',
            'diproses' => 'pill-blue',
            'selesai' => 'pill-green',
            'dibatalkan' => 'pill-zinc',
        ];
        $warnaStatus = [
            'menunggu_verifikasi' => '#d97706',
            'perlu_perbaikan' => '#dc2626',
            'terverifikasi' => '#4353ff',
            'diproses' => '#0284c7',
            'selesai' => '#059669',
            'dibatalkan' => '#a1a1aa',
        ];
        $antreanTampil = ! auth()->user()->isNasabah() && $tampilkanAntrean;
        $trenSparkData = $this->tren;
        $jumlahBucketSpark = count($trenSparkData);
        $titikSparkTotal = collect($trenSparkData)->map(function ($bucket, $i) use ($jumlahBucketSpark) {
            $x = $jumlahBucketSpark > 1 ? round($i / ($jumlahBucketSpark - 1) * 72, 2) : 0;
            $y = round(23 - ($bucket['persen_tinggi'] / 100 * 20), 2);

            return $x . ',' . $y;
        })->implode(' ');
        $titikSparkSelesai = collect($trenSparkData)->map(function ($bucket, $i) use ($jumlahBucketSpark) {
            $x = $jumlahBucketSpark > 1 ? round($i / ($jumlahBucketSpark - 1) * 72, 2) : 0;
            $y = round(23 - ($bucket['persen_tinggi_selesai'] / 100 * 20), 2);

            return $x . ',' . $y;
        })->implode(' ');
    @endphp

    @unless (auth()->user()->isNasabah())
    {{-- Strip perhatian: item-item hal mendesak hari ini (selaras mockup) --}}
    <div class="mb-4.5 flex flex-wrap items-center gap-3.5 rounded-3xl bg-linear-to-r from-[#4353ff] to-[#5b6bff] p-4 text-white">
        <span class="mr-1 text-[14.5px] font-bold max-sm:w-full">Yang butuh perhatianmu hari ini:</span>
        @unless (auth()->user()->isNasabah())
            @if ($this->antrean->count() > 0)
                <div class="flex items-center gap-2.25 rounded-[14px] border border-white/18 bg-white/10 px-3 py-2 max-sm:w-full max-sm:[&>span]:flex-1">
                    <b class="text-base font-extrabold">{{ $this->antrean->count() }}</b>
                    <span class="text-[13.5px]">pengajuan menunggu verifikasi</span>
                    <a href="{{ route('pengajuans.index') }}" wire:navigate class="shrink-0 rounded-[9px] bg-white px-2.5 py-1.25 text-xs font-semibold text-primary hover:bg-white/90">Buka Antrean</a>
                </div>
            @endif
            @if ($this->pemeriksaan['tidak_sesuai'] > 0)
                <div class="flex items-center gap-2.25 rounded-[14px] border border-white/18 bg-white/10 px-3 py-2 max-sm:w-full max-sm:[&>span]:flex-1">
                    <b class="text-base font-extrabold">{{ $this->pemeriksaan['tidak_sesuai'] }}</b>
                    <span class="text-[13.5px]">hasil pemeriksaan tidak sesuai</span>
                    <a href="#pemeriksaan" class="shrink-0 rounded-[9px] bg-white px-2.5 py-1.25 text-xs font-semibold text-primary hover:bg-white/90">Lihat</a>
                </div>
            @endif
            @if ($this->ringkasan['perlu_perbaikan'] > 0)
                <div class="flex items-center gap-2.25 rounded-[14px] border border-white/18 bg-white/10 px-3 py-2 max-sm:w-full max-sm:[&>span]:flex-1">
                    <b class="text-base font-extrabold">{{ $this->ringkasan['perlu_perbaikan'] }}</b>
                    <span class="text-[13.5px]">perlu perbaikan belum ditanggapi nasabah</span>
                    <a href="{{ route('pengajuans.index') }}" wire:navigate class="shrink-0 rounded-[9px] bg-white px-2.5 py-1.25 text-xs font-semibold text-primary hover:bg-white/90">Lihat</a>
                </div>
            @endif
            @if ($this->antrean->count() === 0 && $this->pemeriksaan['tidak_sesuai'] === 0 && $this->ringkasan['perlu_perbaikan'] === 0)
                <span class="text-[13.5px] text-white/85">Tidak ada yang mendesak — semua terkendali.</span>
            @endif
        @else
            @if ($this->ringkasan['perlu_perbaikan'] > 0)
                <div class="flex items-center gap-2.25 rounded-[14px] border border-white/18 bg-white/10 px-3 py-2 max-sm:w-full max-sm:[&>span]:flex-1">
                    <b class="text-base font-extrabold">{{ $this->ringkasan['perlu_perbaikan'] }}</b>
                    <span class="text-[13.5px]">pengajuan perlu perbaikan</span>
                    <a href="{{ route('nasabah.pengajuan') }}" wire:navigate class="shrink-0 rounded-[9px] bg-white px-2.5 py-1.25 text-xs font-semibold text-primary hover:bg-white/90">Perbaiki</a>
                </div>
            @endif
            @if ($this->ringkasan['menunggu_verifikasi'] > 0)
                <div class="flex items-center gap-2.25 rounded-[14px] border border-white/18 bg-white/10 px-3 py-2 max-sm:w-full max-sm:[&>span]:flex-1">
                    <b class="text-base font-extrabold">{{ $this->ringkasan['menunggu_verifikasi'] }}</b>
                    <span class="text-[13.5px]">pengajuan menunggu verifikasi</span>
                </div>
            @endif
            @if ($this->ringkasan['perlu_perbaikan'] === 0 && $this->ringkasan['menunggu_verifikasi'] === 0)
                <span class="text-[13.5px] text-white/85">Semua pengajuanmu berjalan baik hari ini.</span>
            @endif
        @endunless
    </div>
    @endunless

    <div class="bento">
        {{-- Blok nasabah (selaras mockup dashboard-nasabah): lacak pengajuan, peringatan perbaikan, KPI ringkas, hasil pemeriksaan terakhir --}}
        @if (auth()->user()->isNasabah())
            @php
                $lacak = $this->lacak;
                $daftarPerbaikan = $this->perluPerbaikan;
                $hasilTerakhir = $lacak?->hasilPemeriksaans->first();
                $indeksStatus = match ($lacak?->status?->value) {
                    'menunggu_verifikasi', 'perlu_perbaikan' => 1,
                    'terverifikasi' => 2,
                    'diproses' => 3,
                    'selesai' => 4,
                    default => 0,
                };
                $langkahStepper = ['Dibuat', 'Menunggu Verifikasi', 'Terverifikasi', 'Diproses', 'Selesai'];
                $catatanStepper = match ($lacak?->status?->value) {
                    'menunggu_verifikasi' => 'Pengajuan sedang diperiksa tim layanan. Hasil pemeriksaan awal dapat dilihat di halaman detail.',
                    'perlu_perbaikan' => 'Ada dokumen yang perlu diperbaiki. Lihat catatan operator pada tile peringatan, atau buka halaman detail pengajuan.',
                    'terverifikasi' => 'Pengajuan sudah terverifikasi dan akan diproses oleh tim layanan.',
                    'diproses' => 'Pengajuan sedang diproses oleh tim layanan.',
                    'selesai' => 'Pengajuan selesai. Terima kasih.',
                    default => '',
                };
            @endphp
            @if ($lacak)
                <section class="tile md:col-span-2 {{ $daftarPerbaikan->isNotEmpty() ? 'xl:col-span-8' : 'xl:col-span-12' }}">
                    <div class="mb-4 flex items-center justify-between gap-2.5">
                        <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Lacak Pengajuan Terbaru</h2>
                        <a href="{{ route('nasabah.pengajuan') }}" wire:navigate class="text-[12.5px] font-semibold text-primary hover:underline">Semua pengajuan →</a>
                    </div>
                    <div class="mb-1.5 flex flex-wrap items-center gap-2.5">
                        <span class="font-mono text-[15px] font-bold text-zinc-900 dark:text-white">{{ $lacak->nomor_pengajuan }}</span>
                        @if ($lacak->layanan->kode === 'EKS')
                            <span class="pill pill-indigo">Ekspor</span>
                        @elseif ($lacak->layanan->kode === 'IMP')
                            <span class="pill pill-blue">Impor</span>
                        @else
                            <span class="pill pill-zinc">{{ $lacak->layanan->nama }}</span>
                        @endif
                        @if ($lacak->nomor_lc)
                            <span class="text-[12.5px] text-mist dark:text-zinc-400">LC {{ $lacak->nomor_lc }}</span>
                        @endif
                    </div>
                    <div class="mt-2 mb-1 flex items-start overflow-x-auto pb-1.5">
                        @foreach ($langkahStepper as $i => $labelLangkah)
                            @php
                                $keadaan = $lacak->status->value === 'selesai' || $i < $indeksStatus ? 'selesai' : ($i === $indeksStatus ? 'kini' : 'nanti');
                            @endphp
                            <div class="relative min-w-21.5 flex-1 text-center sm:min-w-0">
                                @if ($i > 0)
                                    <span class="absolute top-3.75 -left-1/2 h-[2.5px] w-full {{ $keadaan === 'selesai' ? 'bg-emerald-600' : 'bg-line dark:bg-night-line' }}"></span>
                                @endif
                                <div class="relative z-10 mx-auto mb-1.75 grid size-7.5 place-items-center rounded-full border-2 text-[13px] font-bold {{ $keadaan === 'selesai' ? 'border-emerald-600 bg-emerald-600 text-white' : ($keadaan === 'kini' ? 'border-primary bg-primary text-white shadow-[0_0_0_5px_#eef0ff] dark:shadow-[0_0_0_5px_#1e2242]' : 'border-line bg-white text-mist dark:border-night-line dark:bg-night-tile dark:text-zinc-500') }}">
                                    @if ($keadaan === 'selesai')
                                        <flux:icon.check class="size-4" />
                                    @elseif ($keadaan === 'kini' && $lacak->status->value === 'perlu_perbaikan')
                                        !
                                    @else
                                        {{ $i + 1 }}
                                    @endif
                                </div>
                                <div class="text-[11.5px] leading-[1.35] font-semibold {{ $keadaan === 'selesai' ? 'text-ink dark:text-zinc-100' : ($keadaan === 'kini' ? 'text-primary' : 'text-mist dark:text-zinc-400') }}">
                                    {{ $labelLangkah }}
                                    @if ($i === 0)
                                        <span class="block text-[11px] font-medium text-mist dark:text-zinc-500">{{ $lacak->created_at->translatedFormat('d M, H.i') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-2.5 text-[12.5px] text-mist dark:text-zinc-400">{{ $catatanStepper }}</p>
                </section>
            @endif
            @if ($daftarPerbaikan->isNotEmpty())
                @php
                    $perbaikanUtama = $daftarPerbaikan->first();
                @endphp
                <section class="tile border-transparent bg-amber-50 md:col-span-2 xl:col-span-4 dark:bg-[#2e2008]">
                    <div class="mb-4 flex items-center justify-between gap-2.5">
                        <h2 class="text-[15px] font-bold tracking-[-0.01em] text-amber-600 dark:text-amber-400">Menunggu Perbaikan dari Anda</h2>
                    </div>
                    <p class="text-[13.5px] leading-[1.6] text-zinc-700 dark:text-zinc-200">
                        Pengajuan <b class="font-bold">{{ $perbaikanUtama->nomor_pengajuan }}</b>: {{ $perbaikanUtama->catatan ?? 'ada dokumen yang perlu diperbaiki. Buka detail pengajuan untuk melihat catatan operator.' }}
                    </p>
                    @if ($daftarPerbaikan->count() > 1)
                        <p class="mt-2 text-[12.5px] text-zinc-500 dark:text-zinc-400">+{{ $daftarPerbaikan->count() - 1 }} pengajuan lain juga menunggu perbaikan.</p>
                    @endif
                    <a href="{{ route('pengajuans.show', $perbaikanUtama) }}" wire:navigate class="btn btn-primary mt-3.5">Perbaiki Sekarang</a>
                </section>
            @endif
            <section class="tile flex flex-col p-3.5 sm:p-4.5 md:col-span-1 xl:col-span-4">
                <div class="text-[11px] font-bold tracking-[0.07em] text-mist uppercase dark:text-zinc-400">Pengajuan Aktif</div>
                <div class="mt-1.5 mb-0.5 text-[24px] font-extrabold tracking-tight text-zinc-900 sm:text-[29px] dark:text-white">{{ $this->jumlahAktif['aktif'] }}</div>
                <div class="mt-auto flex items-end justify-between gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ $this->jumlahAktif['menunggu'] }} menunggu verifikasi</span>
                </div>
            </section>
            <section class="tile flex flex-col p-3.5 sm:p-4.5 md:col-span-1 xl:col-span-4">
                <div class="text-[11px] font-bold tracking-[0.07em] text-mist uppercase dark:text-zinc-400">Selesai ({{ $labelSingkat }})</div>
                <div class="mt-1.5 mb-0.5 text-[24px] font-extrabold tracking-tight text-zinc-900 sm:text-[29px] dark:text-white">{{ $this->ringkasan['selesai'] }}</div>
                <div class="mt-auto flex items-end justify-between gap-2">
                    @if ($this->delta['selesai']['persen'] !== null)
                        @if ($this->delta['selesai']['persen'] == 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">— sama</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap {{ $this->delta['selesai']['persen'] > 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-[#0b2b21] dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-[#331314] dark:text-red-400' }}">{{ $this->delta['selesai']['persen'] > 0 ? '▲' : '▼' }} {{ abs($this->delta['selesai']['persen']) }}%</span>
                        @endif
                    @elseif ($this->delta['selesai']['baru'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-emerald-600 dark:bg-[#0b2b21] dark:text-emerald-400">Aktivitas baru</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">— sama</span>
                    @endif
                </div>
            </section>
            @if ($tampilkanPemeriksaan)
                <section class="tile md:col-span-2 xl:col-span-4">
                    <div class="mb-4 flex items-center justify-between gap-2.5">
                        <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Hasil Pemeriksaan Terakhir</h2>
                    </div>
                    @if ($hasilTerakhir)
                        <p class="mb-1 text-[12.5px] text-mist dark:text-zinc-400">Pengajuan {{ $lacak->nomor_pengajuan }} — diperiksa {{ $hasilTerakhir->created_at->diffForHumans() }}.</p>
                        @foreach ($hasilTerakhir->details->take(5) as $detail)
                            <div class="flex items-center gap-2.5 border-b border-dashed border-line py-2.25 text-[13px] last:border-b-0 dark:border-night-line">
                                @if ($detail->hasil->value === 'sesuai')
                                    <span class="font-extrabold text-emerald-600">✓</span>
                                @elseif ($detail->hasil->value === 'tidak_sesuai')
                                    <span class="font-extrabold text-red-600">✕</span>
                                @else
                                    <span class="font-extrabold text-zinc-400">–</span>
                                @endif
                                <span class="flex-1 text-zinc-700 dark:text-zinc-200">{{ $detail->nama_pemeriksaan }}</span>
                                <span class="text-[12px] font-semibold {{ $detail->hasil->value === 'sesuai' ? 'text-emerald-600' : ($detail->hasil->value === 'tidak_sesuai' ? 'text-red-600' : 'text-zinc-400') }}">{{ $detail->hasil->label() }}</span>
                            </div>
                        @endforeach
                        <p class="mt-2.5 text-[12.5px] text-mist dark:text-zinc-400">Keputusan akhir tetap pada Operator.</p>
                    @elseif ($lacak)
                        <flux:text class="block">Pengajuan terbaru belum pernah diperiksa.</flux:text>
                    @else
                        <flux:text class="block">Belum ada pengajuan untuk diperiksa.</flux:text>
                    @endif
                </section>
            @endif
        @endif
        {{-- Antrean Verifikasi: tile hero untuk staf (selaras mockup) --}}
        @unless (auth()->user()->isNasabah())
            @if ($tampilkanAntrean)
                <section class="tile md:col-span-2 xl:col-span-8">
                    <div class="mb-4 flex items-center justify-between gap-2.5">
                        <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Antrean Verifikasi</h2>
                        <a href="{{ route('pengajuans.index') }}" wire:navigate class="text-[12.5px] font-semibold text-primary hover:underline">Lihat semua →</a>
                    </div>
                    @if ($this->antrean->isEmpty())
                        <flux:text class="block">Tidak ada pengajuan yang menunggu verifikasi.</flux:text>
                    @else
                        <div>
                            @foreach ($this->antrean->take(4) as $pengajuan)
                                <a href="{{ route('pengajuans.show', $pengajuan) }}" wire:navigate class="flex flex-wrap items-center gap-x-3.5 rounded-2xl px-3 py-3.25 transition hover:bg-soft dark:hover:bg-night-soft">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[13px] bg-primary-tint text-primary dark:bg-primary/20">
                                        <flux:icon name="document-text" class="size-5" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[13.5px] font-bold text-zinc-900 dark:text-white">{{ $pengajuan->nomor_pengajuan }}</span>
                                        <span class="block truncate text-xs text-mist dark:text-zinc-400">{{ $pengajuan->user->name }}{{ $pengajuan->nomor_lc ? ' — LC ' . $pengajuan->nomor_lc : '' }}</span>
                                    </span>
                                    <span class="shrink-0 text-right text-xs text-mist dark:text-zinc-400" title="{{ $pengajuan->created_at->format('d M Y H:i') }}">{{ $pengajuan->created_at->diffForHumans(short: true) }}</span>
                                    <span class="flex shrink-0 flex-wrap items-center gap-2.5 max-sm:mt-2.5 max-sm:flex-[1_1_100%]">
                                        <span class="pill {{ $pengajuan->layanan->kode === 'IMP' ? 'pill-blue' : 'pill-indigo' }}">{{ $pengajuan->layanan->nama }}</span>
                                        <span class="pill {{ $pillStatus[$pengajuan->status->value] ?? 'pill-zinc' }}">{{ $pengajuan->status->label() }}</span>
                                        <span class="btn btn-primary btn-small max-sm:ml-auto">Periksa</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        @endunless

        {{-- Kelompok KPI 2x2 di kanan hero (selaras mockup) --}}
        @unless (auth()->user()->isNasabah())
        <div class="grid h-full grid-cols-2 gap-3 sm:gap-4.5 md:col-span-2 {{ $antreanTampil ? 'xl:col-span-4 xl:grid-rows-2' : 'xl:col-span-12 xl:grid-cols-4' }}">
            <section class="tile flex flex-col p-3.5 sm:p-4.5">
                <div class="text-[11px] font-bold tracking-[0.07em] text-mist uppercase dark:text-zinc-400">Total Pengajuan</div>
                <div class="mt-1.5 mb-0.5 text-[24px] font-extrabold sm:text-[29px] tracking-tight text-zinc-900 dark:text-white">{{ $this->ringkasan['total'] }}</div>
                <div class="mt-auto flex items-end justify-between gap-2">
                    @if ($this->delta['total']['persen'] !== null)
                        @if ($this->delta['total']['persen'] == 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">— sama</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap {{ $this->delta['total']['persen'] > 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-[#0b2b21] dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-[#331314] dark:text-red-400' }}">{{ $this->delta['total']['persen'] > 0 ? '▲' : '▼' }} {{ abs($this->delta['total']['persen']) }}%</span>
                        @endif
                    @elseif ($this->delta['total']['baru'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-emerald-600 dark:bg-[#0b2b21] dark:text-emerald-400">Aktivitas baru</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">— sama</span>
                    @endif
                    @if ($jumlahBucketSpark > 1)
                        <svg width="72" height="26" viewBox="0 0 72 26" class="h-auto w-13 min-w-0 sm:w-18" aria-hidden="true">
                            <polyline points="{{ $titikSparkTotal }}" fill="none" stroke="#4353ff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    @endif
                </div>
            </section>

            <section class="tile flex flex-col p-3.5 sm:p-4.5">
                <div class="text-[11px] font-bold tracking-[0.07em] text-mist uppercase dark:text-zinc-400">Menunggu Verifikasi</div>
                <div class="mt-1.5 mb-0.5 text-[24px] font-extrabold sm:text-[29px] tracking-tight text-zinc-900 dark:text-white">{{ $this->ringkasan['menunggu_verifikasi'] }}</div>
                <div class="mt-auto flex items-end justify-between gap-2">
                    @if ($this->delta['menunggu']['persen'] !== null)
                        @if ($this->delta['menunggu']['persen'] == 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">— sama</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap {{ $this->delta['menunggu']['persen'] > 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-[#0b2b21] dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-[#331314] dark:text-red-400' }}">{{ $this->delta['menunggu']['persen'] > 0 ? '▲' : '▼' }} {{ abs($this->delta['menunggu']['persen']) }}%</span>
                        @endif
                    @elseif ($this->delta['menunggu']['baru'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-emerald-600 dark:bg-[#0b2b21] dark:text-emerald-400">Aktivitas baru</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">— sama</span>
                    @endif
                </div>
            </section>

            <section class="tile flex flex-col p-3.5 sm:p-4.5">
                <div class="text-[11px] font-bold tracking-[0.07em] text-mist uppercase dark:text-zinc-400">Selesai ({{ $labelSingkat }})</div>
                <div class="mt-1.5 mb-0.5 text-[24px] font-extrabold sm:text-[29px] tracking-tight text-zinc-900 dark:text-white">{{ $this->ringkasan['selesai'] }}</div>
                <div class="mt-auto flex items-end justify-between gap-2">
                    @if ($this->delta['selesai']['persen'] !== null)
                        @if ($this->delta['selesai']['persen'] == 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">— sama</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap {{ $this->delta['selesai']['persen'] > 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-[#0b2b21] dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-[#331314] dark:text-red-400' }}">{{ $this->delta['selesai']['persen'] > 0 ? '▲' : '▼' }} {{ abs($this->delta['selesai']['persen']) }}%</span>
                        @endif
                    @elseif ($this->delta['selesai']['baru'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-emerald-600 dark:bg-[#0b2b21] dark:text-emerald-400">Aktivitas baru</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">— sama</span>
                    @endif
                    @if ($jumlahBucketSpark > 1)
                        <svg width="72" height="26" viewBox="0 0 72 26" class="h-auto w-13 min-w-0 sm:w-18" aria-hidden="true">
                            <polyline points="{{ $titikSparkSelesai }}" fill="none" stroke="#059669" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    @endif
                </div>
            </section>

            @if ($tampilkanPemeriksaan)
                <section class="tile flex flex-col p-3.5 sm:p-4.5">
                    <div class="text-[11px] font-bold tracking-[0.07em] text-mist uppercase dark:text-zinc-400">Kesesuaian Checker</div>
                    <div class="mt-1.5 mb-0.5 text-[24px] font-extrabold sm:text-[29px] tracking-tight text-zinc-900 dark:text-white">{{ $this->pemeriksaan['persen_sesuai'] }}%</div>
                    <div class="mt-auto flex items-end justify-between gap-2">
                        @if ($this->delta['kesesuaian']['persen'] !== null)
                            @if ($this->delta['kesesuaian']['persen'] == 0)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">— sama</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap {{ $this->delta['kesesuaian']['persen'] > 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-[#0b2b21] dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-[#331314] dark:text-red-400' }}">{{ $this->delta['kesesuaian']['persen'] > 0 ? '▲' : '▼' }} {{ abs($this->delta['kesesuaian']['persen']) }}%</span>
                            @endif
                        @elseif ($this->delta['kesesuaian']['baru'])
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-emerald-600 dark:bg-[#0b2b21] dark:text-emerald-400">Aktivitas baru</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">— sama</span>
                        @endif
                    </div>
                </section>
            @else
                <section class="tile flex flex-col p-3.5 sm:p-4.5">
                    <div class="text-[11px] font-bold tracking-[0.07em] text-mist uppercase dark:text-zinc-400">Dokumen Terunggah</div>
                    <div class="mt-1.5 mb-0.5 text-[24px] font-extrabold sm:text-[29px] tracking-tight text-zinc-900 dark:text-white">{{ $this->ringkasan['total_dokumen'] }}</div>
                    <p class="mt-auto text-xs text-zinc-400">Dokumen pada pengajuan periode ini</p>
                </section>
            @endif
        </div>
        @endunless

        {{-- Tren Pengajuan: grafik area satu seri, kanvas 720x190 (selaras mockup) --}}
        @if ($tampilkanTren)
            <section class="tile md:col-span-2 {{ $tampilkanDistribusiStatus ? 'xl:col-span-8' : 'xl:col-span-12' }}">
                @php
                    $trenData = $this->tren;
                    $nTren = count($trenData);
                    $buatPathMulus = function (array $daftarPoin): string {
                        $jumlahPoin = count($daftarPoin);
                        if ($jumlahPoin < 2) {
                            return '';
                        }
                        $path = 'M' . $daftarPoin[0][0] . ',' . $daftarPoin[0][1];
                        for ($i = 0; $i < $jumlahPoin - 1; $i++) {
                            $p0 = $daftarPoin[max($i - 1, 0)];
                            $p1 = $daftarPoin[$i];
                            $p2 = $daftarPoin[$i + 1];
                            $p3 = $daftarPoin[min($i + 2, $jumlahPoin - 1)];
                            $c1x = round($p1[0] + ($p2[0] - $p0[0]) / 6, 2);
                            $c1y = round(max(5, min(185, $p1[1] + ($p2[1] - $p0[1]) / 6)), 2);
                            $c2x = round($p2[0] - ($p3[0] - $p1[0]) / 6, 2);
                            $c2y = round(max(5, min(185, $p2[1] - ($p3[1] - $p1[1]) / 6)), 2);
                            $path .= ' C' . $c1x . ',' . $c1y . ' ' . $c2x . ',' . $c2y . ' ' . round($p2[0], 2) . ',' . round($p2[1], 2);
                        }

                        return $path;
                    };
                    $poinDibuat = collect($trenData)->map(function ($bucket, $i) use ($nTren) {
                        $x = $nTren > 1 ? round($i / ($nTren - 1) * 720, 2) : 0;
                        $y = round(160 - ($bucket['persen_tinggi'] / 100 * 135), 2);

                        return [$x, $y];
                    })->all();
                    $pathDibuat = $buatPathMulus($poinDibuat);
                    $areaDibuat = $pathDibuat ? $pathDibuat . ' L720,190 L0,190 Z' : '';
                    $indeksPuncak = 0;
                    foreach ($trenData as $i => $bucket) {
                        if ($bucket['jumlah'] > ($trenData[$indeksPuncak]['jumlah'] ?? 0)) {
                            $indeksPuncak = $i;
                        }
                    }
                    $titikPuncak = $poinDibuat[$indeksPuncak] ?? null;
                @endphp
                <div class="mb-4 flex items-center justify-between gap-2.5">
                    <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Tren Pengajuan — {{ $labelPeriode }}</h2>
                    @if ($this->delta['total']['persen'] !== null && $this->delta['total']['persen'] != 0)
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-[2.5px] text-[11.5px] font-bold whitespace-nowrap {{ $this->delta['total']['persen'] > 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-[#0b2b21] dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-[#331314] dark:text-red-400' }}">{{ $this->delta['total']['persen'] > 0 ? '▲' : '▼' }} {{ abs($this->delta['total']['persen']) }}% vs periode sebelumnya</span>
                    @endif
                </div>
                @if ($nTren > 1)
                    <svg viewBox="0 0 720 190" class="block h-auto w-full" aria-hidden="true">
                        <defs>
                            <linearGradient id="gradienTren" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#4353ff" stop-opacity="0.28" />
                                <stop offset="100%" stop-color="#4353ff" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <g class="stroke-line dark:stroke-night-line" stroke-width="1">
                            <line x1="0" y1="45" x2="720" y2="45" />
                            <line x1="0" y1="95" x2="720" y2="95" />
                            <line x1="0" y1="145" x2="720" y2="145" />
                        </g>
                        @if ($areaDibuat)
                            <path d="{{ $areaDibuat }}" fill="url(#gradienTren)" />
                        @endif
                        <path d="{{ $pathDibuat }}" fill="none" stroke="#4353ff" stroke-width="2.6" stroke-linecap="round" />
                        @if ($titikPuncak && $trenData[$indeksPuncak]['jumlah'] > 0)
                            <circle cx="{{ $titikPuncak[0] }}" cy="{{ $titikPuncak[1] }}" r="4.5" fill="#4353ff" stroke="#fff" stroke-width="2" />
                        @endif
                    </svg>
                    @if ($nTren >= 5)
                        <div class="mt-2 flex justify-between text-[11.5px] text-mist dark:text-zinc-400">
                            <span>{{ $trenData[0]['label'] }}</span>
                            <span>{{ $trenData[intdiv($nTren, 4)]['label'] }}</span>
                            <span>{{ $trenData[intdiv($nTren, 2)]['label'] }}</span>
                            <span>{{ $trenData[intdiv($nTren * 3, 4)]['label'] }}</span>
                            <span>{{ $trenData[$nTren - 1]['label'] }}</span>
                        </div>
                    @else
                        <div class="mt-2 flex justify-between text-[11.5px] text-mist dark:text-zinc-400">
                            <span>{{ $trenData[0]['label'] }}</span>
                            <span>{{ $trenData[$nTren - 1]['label'] }}</span>
                        </div>
                    @endif
                @else
                    <flux:text class="block">Belum ada data tren pada periode ini.</flux:text>
                @endif
            </section>
        @endif

        {{-- Distribusi Status: donat 132px + legenda (selaras mockup) --}}
        @if ($tampilkanDistribusiStatus)
            <section class="tile md:col-span-1 {{ $tampilkanTren ? 'xl:col-span-4' : 'md:col-span-2 xl:col-span-12' }}">
                <div class="mb-4 flex items-center justify-between gap-2.5">
                    <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Distribusi Status</h2>
                </div>
                @php
                    $totalDistribusi = array_sum(array_column($this->distribusi, 'jumlah'));
                    $kelilingDonat = 2 * pi() * 52;
                    $offsetDonat = 0;
                @endphp
                @if ($totalDistribusi === 0)
                    <flux:text class="block">Tidak ada pengajuan pada periode ini.</flux:text>
                @else
                    <div class="flex items-center gap-5 max-sm:flex-col max-sm:items-start">
                        <svg width="132" height="132" viewBox="0 0 132 132" class="shrink-0" aria-hidden="true">
                            <circle cx="66" cy="66" r="52" fill="none" stroke-width="17" class="stroke-line dark:stroke-night-line" />
                            @foreach ($this->distribusi as $baris)
                                @if ($baris['jumlah'] > 0)
                                    @php
                                        $panjangSegmen = $baris['jumlah'] / $totalDistribusi * $kelilingDonat;
                                    @endphp
                                    <circle cx="66" cy="66" r="52" fill="none" stroke-width="17"
                                        stroke="{{ $warnaStatus[$baris['status']->value] ?? '#71717a' }}"
                                        stroke-dasharray="{{ round($panjangSegmen, 1) }} {{ round($kelilingDonat - $panjangSegmen, 1) }}"
                                        stroke-dashoffset="{{ round(-$offsetDonat, 1) }}"
                                        transform="rotate(-90 66 66)" />
                                    @php
                                        $offsetDonat += $panjangSegmen;
                                    @endphp
                                @endif
                            @endforeach
                            <text x="66" y="62" text-anchor="middle" font-size="23" font-weight="800" class="fill-zinc-900 dark:fill-white">{{ $totalDistribusi }}</text>
                            <text x="66" y="80" text-anchor="middle" font-size="10.5" class="fill-zinc-400">pengajuan</text>
                        </svg>
                        <div class="flex flex-1 flex-col gap-2.25 self-stretch text-[13px] max-sm:w-full">
                            @foreach ($this->distribusi as $baris)
                                @if ($baris['jumlah'] > 0)
                                    <div class="flex items-center gap-2 text-mist dark:text-zinc-400">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-[3.5px]" style="background-color: {{ $warnaStatus[$baris['status']->value] ?? '#71717a' }}"></span>
                                        <span>{{ $baris['status']->value === 'menunggu_verifikasi' ? 'Menunggu' : $baris['status']->label() }}</span>
                                        <b class="ml-auto font-semibold text-zinc-900 dark:text-white">{{ $baris['jumlah'] }}</b>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endif

        {{-- Ringkasan Pemeriksaan: bar progres + catatan aturan gagal (selaras mockup) --}}
        @if ($tampilkanPemeriksaan && ! auth()->user()->isNasabah())
            <section id="pemeriksaan" class="tile md:col-span-1 xl:col-span-4">
                <div class="mb-4 flex items-center justify-between gap-2.5">
                    <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Ringkasan Pemeriksaan</h2>
                </div>
                @php
                    $totalPemeriksaan = max($this->pemeriksaan['total'], 1);
                @endphp
                <div class="mb-3.5">
                    <div class="mb-1.5 flex items-center justify-between text-[13px] font-medium text-zinc-700 dark:text-zinc-200">
                        <span>Sesuai</span>
                        <b class="font-bold text-zinc-900 dark:text-white">{{ $this->pemeriksaan['sesuai'] }}</b>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-soft dark:bg-night-soft">
                        <div class="h-full rounded-full bg-emerald-600" style="width: {{ round($this->pemeriksaan['sesuai'] / $totalPemeriksaan * 100, 1) }}%"></div>
                    </div>
                </div>
                <div class="mb-3.5">
                    <div class="mb-1.5 flex items-center justify-between text-[13px] font-medium text-zinc-700 dark:text-zinc-200">
                        <span>Tidak Sesuai</span>
                        <b class="font-bold text-zinc-900 dark:text-white">{{ $this->pemeriksaan['tidak_sesuai'] }}</b>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-soft dark:bg-night-soft">
                        <div class="h-full rounded-full bg-red-600" style="width: {{ round($this->pemeriksaan['tidak_sesuai'] / $totalPemeriksaan * 100, 1) }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="mb-1.5 flex items-center justify-between text-[13px] font-medium text-zinc-700 dark:text-zinc-200">
                        <span>Belum Dapat Diperiksa</span>
                        <b class="font-bold text-zinc-900 dark:text-white">{{ $this->pemeriksaan['belum'] }}</b>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-soft dark:bg-night-soft">
                        <div class="h-full rounded-full bg-zinc-400" style="width: {{ round($this->pemeriksaan['belum'] / $totalPemeriksaan * 100, 1) }}%"></div>
                    </div>
                </div>
                @if (count($this->aturanGagal) > 0)
                    <div class="mt-4 rounded-[14px] bg-red-50 px-3.25 py-2.75 text-[12.5px] leading-normal text-red-700 dark:bg-red-950/50 dark:text-red-300">
                        Aturan paling sering gagal periode ini:
                        @if ($this->aturanGagal[0]['kode'])
                            <code class="font-mono font-bold">{{ $this->aturanGagal[0]['kode'] }}</code> —
                        @endif
                        {{ $this->aturanGagal[0]['nama'] }} ({{ $this->aturanGagal[0]['jumlah_gagal'] }} kali).
                    </div>
                @else
                    <p class="mt-4 text-[12.5px] text-zinc-500 dark:text-zinc-400">Belum ada aturan yang gagal pada periode ini.</p>
                @endif
            </section>
        @endif

        {{-- Pengajuan Terbaru: tabel (selaras mockup) --}}
        <section class="tile md:col-span-2 {{ $tampilkanPemeriksaan && ! auth()->user()->isNasabah() ? 'xl:col-span-8' : 'xl:col-span-12' }}">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Pengajuan Terbaru</h2>
                @unless (auth()->user()->isNasabah())
                    <a href="{{ route('pengajuans.index') }}" wire:navigate class="text-[12.5px] font-semibold text-primary hover:underline">Pantau semua →</a>
                @else
                    <a href="{{ route('nasabah.pengajuan') }}" wire:navigate class="text-[12.5px] font-semibold text-primary hover:underline">Lihat semua →</a>
                @endunless
            </div>
            @if ($this->terbaru->isEmpty())
                <flux:text class="block">Tidak ada pengajuan pada periode ini.</flux:text>
            @else
                <div class="-mx-4.25 overflow-x-auto px-4.25 sm:-mx-5.5 sm:px-5.5">
                    <div class="min-w-140">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">
                                    <th class="pb-2.5">Nomor</th>
                                    @unless (auth()->user()->isNasabah())
                                        <th class="pb-2.5">Nasabah</th>
                                    @endunless
                                    <th class="pb-2.5">Layanan</th>
                                    @if (auth()->user()->isNasabah())
                                        <th class="pb-2.5">Nomor LC</th>
                                    @endif
                                    <th class="pb-2.5">Status</th>
                                    <th class="pb-2.5">Dibuat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->terbaru as $pengajuan)
                                    <tr class="border-t border-line dark:border-night-line">
                                        <td class="py-3.25 pr-3">
                                            <a href="{{ route('pengajuans.show', $pengajuan) }}" wire:navigate class="font-mono text-[13px] font-bold whitespace-nowrap text-zinc-900 hover:text-primary dark:text-white">{{ $pengajuan->nomor_pengajuan }}</a>
                                        </td>
                                        @unless (auth()->user()->isNasabah())
                                            <td class="py-3.25 pr-3 text-[13.5px] text-zinc-600 dark:text-zinc-300">{{ $pengajuan->user->name }}</td>
                                        @endunless
                                        <td class="py-3.25 pr-3 text-[13.5px] whitespace-nowrap text-zinc-600 dark:text-zinc-300">{{ $pengajuan->layanan->nama }}</td>
                                        @if (auth()->user()->isNasabah())
                                            <td class="py-3.25 pr-3 font-mono text-[13px] whitespace-nowrap text-zinc-600 dark:text-zinc-300">{{ $pengajuan->nomor_lc ?? '—' }}</td>
                                        @endif
                                        <td class="py-3.25 pr-3"><span class="pill {{ $pillStatus[$pengajuan->status->value] ?? 'pill-zinc' }}">{{ $pengajuan->status->label() }}</span></td>
                                        <td class="py-3.25 text-[13.5px] whitespace-nowrap text-zinc-500 dark:text-zinc-400" title="{{ $pengajuan->created_at->format('d M Y H:i') }}">{{ $pengajuan->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

        {{-- Pengajuan per Layanan (tidak ada di mockup; dipertahankan demi toggle personalisasi D4) --}}
        @if ($tampilkanPerLayanan)
            <section class="tile md:col-span-2 xl:col-span-12">
                <div class="mb-4 flex items-center justify-between gap-2.5">
                    <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Pengajuan per Layanan</h2>
                </div>
                @php
                    $paletLayanan = ['#4353ff', '#f59e0b', '#10b981', '#8b5cf6', '#ec4899', '#06b6d4'];
                @endphp
                <div class="grid gap-x-10 gap-y-4 md:grid-cols-2">
                    @forelse ($this->perLayanan as $baris)
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-xs font-bold text-white" style="background-color: {{ $paletLayanan[$loop->index % count($paletLayanan)] }}">{{ strtoupper(substr($baris['layanan']->kode, 0, 2)) }}</span>
                                    <span class="truncate text-sm text-zinc-600 dark:text-zinc-300">{{ $baris['layanan']->nama }}</span>
                                </span>
                                <span class="flex shrink-0 items-center gap-2">
                                    <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $baris['jumlah'] }}</span>
                                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ $baris['persen'] }}%</span>
                                </span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-soft dark:bg-night-soft">
                                <div class="h-full rounded-full" style="width: {{ $baris['persen'] }}%; background-color: {{ $paletLayanan[$loop->index % count($paletLayanan)] }}"></div>
                            </div>
                        </div>
                    @empty
                        <flux:text>Belum ada data layanan.</flux:text>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
</div>
