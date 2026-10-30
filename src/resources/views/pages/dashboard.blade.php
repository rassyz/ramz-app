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

<div class="flex h-full w-full flex-1 flex-col gap-5 rounded-xl">
    {{-- Baris judul ala referensi: judul besar + tanggal, lonceng notifikasi, filter, personalisasi --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Dashboard</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ now()->translatedFormat('l, d F Y') }} · Selamat datang, {{ auth()->user()->name }}
                <flux:badge color="blue" class="ml-1">{{ auth()->user()->role->label() }}</flux:badge>
            </p>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <a href="{{ route('notifikasis.index') }}" class="relative mb-0.5 flex h-10 w-10 items-center justify-center rounded-xl bg-white text-zinc-600 shadow-sm hover:text-[#4353ff] dark:bg-zinc-900 dark:text-zinc-300" wire:navigate title="Notifikasi">
                <flux:icon name="bell" class="size-5" />
                @if ($this->jumlahBelumDibaca > 0)
                    <span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[11px] font-semibold text-white">{{ $this->jumlahBelumDibaca }}</span>
                @endif
            </a>
            <flux:select wire:model.live="periode" label="Periode">
                @foreach (PeriodeDashboard::cases() as $preset)
                    <option value="{{ $preset->value }}">{{ $preset->label() }}</option>
                @endforeach
                <option value="khusus">Rentang Khusus</option>
            </flux:select>
            @if ($periode === 'khusus')
                <flux:input wire:model.live="tanggalMulai" type="date" label="Mulai" />
                <flux:input wire:model.live="tanggalSelesai" type="date" label="Selesai" />
            @endif
            <flux:button wire:click="$toggle('panelPersonalisasi')">Personalisasi</flux:button>
        </div>
    </div>

    @if ($panelPersonalisasi)
        <form wire:submit="simpanPersonalisasi" class="space-y-4 rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:bg-zinc-900">
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

    {{-- Kartu KPI: kartu pertama biru solid ala referensi, badge delta dari periode sebelumnya --}}
    <div class="grid auto-rows-min gap-4 md:grid-cols-4">
        <div class="rounded-3xl bg-gradient-to-br from-[#4353ff] to-[#6d7cff] p-6 text-white shadow-[0_12px_32px_rgb(67,83,255,0.35)]">
            <div class="flex items-start justify-between">
                <p class="text-sm text-white/75">{{ auth()->user()->isNasabah() ? 'Total Pengajuan Saya' : 'Total Pengajuan' }}</p>
                <flux:icon name="document-text" class="size-5 text-white/75" />
            </div>
            <p class="mt-2 text-4xl font-bold">{{ $this->ringkasan['total'] }}</p>
            @if ($this->delta['total']['persen'] !== null)
                <span class="mt-3 inline-block rounded-full bg-white/20 px-2.5 py-1 text-xs font-medium">
                    {{ $this->delta['total']['persen'] >= 0 ? '+' : '' }}{{ $this->delta['total']['persen'] }}% dari periode sebelumnya
                </span>
            @elseif ($this->delta['total']['baru'])
                <span class="mt-3 inline-block rounded-full bg-white/20 px-2.5 py-1 text-xs font-medium">Aktivitas baru pada periode ini</span>
            @endif
        </div>

        <div class="rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:bg-zinc-900">
            <div class="flex items-start justify-between">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Menunggu Verifikasi</p>
                <flux:icon name="clock" class="size-5 text-zinc-400" />
            </div>
            <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-white">{{ $this->ringkasan['menunggu_verifikasi'] }}</p>
            <p class="mt-3 text-xs text-zinc-400">{{ auth()->user()->isNasabah() ? 'Pengajuan Anda yang sedang antre' : 'Antrean yang menunggu operator' }}</p>
        </div>

        <div class="rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:bg-zinc-900">
            <div class="flex items-start justify-between">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ auth()->user()->isNasabah() ? 'Perlu Perbaikan' : 'Selesai' }}</p>
                <flux:icon name="{{ auth()->user()->isNasabah() ? 'exclamation-circle' : 'check-circle' }}" class="size-5 text-zinc-400" />
            </div>
            <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-white">{{ auth()->user()->isNasabah() ? $this->ringkasan['perlu_perbaikan'] : $this->ringkasan['selesai'] }}</p>
            @unless (auth()->user()->isNasabah())
                @if ($this->delta['selesai']['persen'] !== null)
                    <span class="mt-3 inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $this->delta['selesai']['persen'] >= 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-400' }}">
                        {{ $this->delta['selesai']['persen'] >= 0 ? '+' : '' }}{{ $this->delta['selesai']['persen'] }}% dari periode sebelumnya
                    </span>
                @elseif ($this->delta['selesai']['baru'])
                    <span class="mt-3 inline-block rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">Penyelesaian pertama pada periode ini</span>
                @endif
            @endunless
        </div>

        <div class="rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:bg-zinc-900">
            <div class="flex items-start justify-between">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Total Pembayaran</p>
                <flux:icon name="banknotes" class="size-5 text-zinc-400" />
            </div>
            @forelse ($this->ringkasan['pembayaran_per_mata_uang'] as $baris)
                <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ $baris['mata_uang']->value }} {{ number_format($baris['total'], 2, ',', '.') }}</p>
            @empty
                <p class="mt-2 text-4xl font-bold text-zinc-900 dark:text-white">-</p>
            @endforelse
            <p class="mt-3 text-xs text-zinc-400">Dikelompokkan per mata uang</p>
        </div>
    </div>

    @if ($tampilkanTren)
        <div class="rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading level="2">Tren Pengajuan</flux:heading>
                <div class="flex items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-[#4353ff]"></span> Dibuat</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-[#b9c2ff]"></span> Selesai</span>
                </div>
            </div>
            <div class="mt-5 flex h-44 items-end gap-1">
                @foreach ($this->tren as $bucket)
                    <div class="flex flex-1 flex-col items-center justify-end gap-1 self-stretch" title="{{ $bucket['label'] }}: {{ $bucket['jumlah'] }} dibuat, {{ $bucket['jumlah_selesai'] }} selesai">
                        <div class="flex w-full items-end justify-center gap-[3px] self-stretch" style="height: 100%">
                            <div class="flex h-full w-2.5 flex-col justify-end">
                                <div class="w-2.5 rounded-full bg-[#4353ff]" style="height: {{ max($bucket['persen_tinggi'], $bucket['jumlah'] > 0 ? 3 : 0) }}%"></div>
                            </div>
                            <div class="flex h-full w-2.5 flex-col justify-end">
                                <div class="w-2.5 rounded-full bg-[#b9c2ff]" style="height: {{ max($bucket['persen_tinggi_selesai'], $bucket['jumlah_selesai'] > 0 ? 3 : 0) }}%"></div>
                            </div>
                        </div>
                        <span class="text-center text-[10px] text-zinc-400">{{ $bucket['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        @if ($tampilkanDistribusiStatus)
            <div class="rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:bg-zinc-900">
                <flux:heading level="2">Distribusi Status</flux:heading>
                @php
                    $totalDistribusi = array_sum(array_column($this->distribusi, 'jumlah'));
                    $warnaStatus = [
                        'menunggu_verifikasi' => '#f59e0b',
                        'perlu_perbaikan' => '#ef4444',
                        'terverifikasi' => '#3b82f6',
                        'diproses' => '#8b5cf6',
                        'selesai' => '#10b981',
                        'dibatalkan' => '#a1a1aa',
                    ];
                    $kelilingDonat = 2 * pi() * 84;
                    $offsetDonat = 0;
                @endphp
                @if ($totalDistribusi === 0)
                    <flux:text class="mt-4 block">Tidak ada pengajuan pada periode ini.</flux:text>
                @else
                    <div class="mt-4 flex flex-wrap items-center gap-6">
                        <div class="relative h-48 w-48 shrink-0">
                            <svg viewBox="0 0 200 200" class="h-48 w-48 -rotate-90">
                                <circle cx="100" cy="100" r="84" fill="none" stroke-width="22" class="stroke-zinc-100 dark:stroke-zinc-800" />
                                @foreach ($this->distribusi as $baris)
                                    @if ($baris['jumlah'] > 0)
                                        @php
                                            $panjangSegmen = $baris['jumlah'] / $totalDistribusi * $kelilingDonat;
                                        @endphp
                                        <circle cx="100" cy="100" r="84" fill="none" stroke-width="22"
                                            stroke="{{ $warnaStatus[$baris['status']->value] ?? '#71717a' }}"
                                            stroke-dasharray="{{ $panjangSegmen }} {{ $kelilingDonat - $panjangSegmen }}"
                                            stroke-dashoffset="{{ -$offsetDonat }}" />
                                        @php($offsetDonat += $panjangSegmen)
                                    @endif
                                @endforeach
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $totalDistribusi }}</span>
                                <span class="text-xs text-zinc-400">Pengajuan</span>
                            </div>
                        </div>
                        <div class="min-w-52 flex-1 space-y-2.5">
                            @foreach ($this->distribusi as $baris)
                                <div class="flex items-center justify-between gap-3">
                                    <span class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $warnaStatus[$baris['status']->value] ?? '#71717a' }}"></span>
                                        {{ $baris['status']->label() }}
                                    </span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $baris['jumlah'] }} <span class="text-xs font-normal text-zinc-400">({{ $baris['persen'] }}%)</span></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        @if ($tampilkanPerLayanan)
            <div class="rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:bg-zinc-900">
                <flux:heading level="2">Pengajuan per Layanan</flux:heading>
                @php($paletLayanan = ['#4353ff', '#f59e0b', '#10b981', '#8b5cf6', '#ec4899', '#06b6d4'])
                <div class="mt-4 space-y-3">
                    @forelse ($this->perLayanan as $baris)
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl text-xs font-bold text-white" style="background-color: {{ $paletLayanan[$loop->index % count($paletLayanan)] }}">{{ strtoupper(substr($baris['layanan']->kode, 0, 2)) }}</span>
                                <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ $baris['layanan']->nama }}</span>
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $baris['jumlah'] }}</span>
                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">{{ $baris['persen'] }}%</span>
                            </span>
                        </div>
                    @empty
                        <flux:text>Belum ada data layanan.</flux:text>
                    @endforelse
                </div>
            </div>
        @endif
    </div>

    @if ($tampilkanPemeriksaan)
        <div class="rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:bg-zinc-900">
            <flux:heading level="2">Ringkasan Pemeriksaan — Rule-Based Evidence Checker</flux:heading>
            <div class="mt-3 flex flex-wrap gap-6">
                <flux:text>Total pemeriksaan: <strong>{{ $this->pemeriksaan['total'] }}</strong></flux:text>
                <flux:text>Sesuai: <strong>{{ $this->pemeriksaan['sesuai'] }} ({{ $this->pemeriksaan['persen_sesuai'] }}%)</strong></flux:text>
                <flux:text>Tidak sesuai: <strong>{{ $this->pemeriksaan['tidak_sesuai'] }}</strong></flux:text>
                <flux:text>Belum dapat diperiksa: <strong>{{ $this->pemeriksaan['belum'] }}</strong></flux:text>
            </div>
        </div>
    @endif

    @unless (auth()->user()->isNasabah())
        @if ($tampilkanAntrean)
            <div class="rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:bg-zinc-900">
                <flux:heading level="2">Antrean Verifikasi</flux:heading>
                @if ($this->antrean->isEmpty())
                    <flux:text class="mt-3 block">Tidak ada pengajuan yang menunggu verifikasi.</flux:text>
                @else
                    <flux:table class="mt-3">
                        <flux:table.columns>
                            <flux:table.column>Nomor Pengajuan</flux:table.column>
                            <flux:table.column>Sejak</flux:table.column>
                            <flux:table.column>Nasabah</flux:table.column>
                            <flux:table.column>Layanan</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->antrean as $pengajuan)
                                <flux:table.row :key="$pengajuan->id">
                                    <flux:table.cell class="font-medium">{{ $pengajuan->nomor_pengajuan }}</flux:table.cell>
                                    <flux:table.cell>{{ $pengajuan->created_at->format('d M Y H:i') }}</flux:table.cell>
                                    <flux:table.cell>{{ $pengajuan->user->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $pengajuan->layanan->nama }}</flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex justify-end">
                                            <flux:button size="sm" :href="route('pengajuans.show', $pengajuan)">Periksa</flux:button>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        @endif
    @endunless

    <div class="rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:bg-zinc-900">
        <flux:heading level="2">Pengajuan Terbaru</flux:heading>
        @if ($this->terbaru->isEmpty())
            <flux:text class="mt-3 block">Tidak ada pengajuan pada periode ini.</flux:text>
        @else
            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>Nomor Pengajuan</flux:table.column>
                    @unless (auth()->user()->isNasabah())
                        <flux:table.column>Nasabah</flux:table.column>
                    @endunless
                    <flux:table.column>Layanan</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->terbaru as $pengajuan)
                        <flux:table.row :key="$pengajuan->id">
                            <flux:table.cell class="font-medium">{{ $pengajuan->nomor_pengajuan }}</flux:table.cell>
                            @unless (auth()->user()->isNasabah())
                                <flux:table.cell>{{ $pengajuan->user->name }}</flux:table.cell>
                            @endunless
                            <flux:table.cell>{{ $pengajuan->layanan->nama }}</flux:table.cell>
                            <flux:table.cell><flux:badge :color="$pengajuan->status->color()">{{ $pengajuan->status->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end">
                                    <flux:button size="sm" :href="route('pengajuans.show', $pengajuan)">Detail</flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>
