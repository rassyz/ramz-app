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
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading level="1" size="xl">Dashboard</flux:heading>
            <flux:subheading>
                Selamat datang, {{ auth()->user()->name }}.
                <flux:badge color="blue">{{ auth()->user()->role->label() }}</flux:badge>
            </flux:subheading>
            <flux:text class="mt-1">
                @if (auth()->user()->isAdmin())
                    Pantau seluruh pengajuan, hasil pemeriksaan, dan antrean verifikasi dari sini.
                @elseif (auth()->user()->isOperator())
                    Pantau antrean verifikasi dan perkembangan pengajuan dari sini.
                @else
                    Pantau perkembangan pengajuan Anda dari sini.
                @endif
            </flux:text>
        </div>

        <div class="flex flex-wrap items-end gap-2">
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
        <form wire:submit="simpanPersonalisasi" class="space-y-4 rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
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

    <div class="grid auto-rows-min gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>{{ auth()->user()->isNasabah() ? 'Total Pengajuan Saya' : 'Total Pengajuan' }}</flux:text>
            <flux:heading level="2" size="xl" class="mt-1">{{ $this->ringkasan['total'] }}</flux:heading>
        </div>
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>Menunggu Verifikasi</flux:text>
            <flux:heading level="2" size="xl" class="mt-1">{{ $this->ringkasan['menunggu_verifikasi'] }}</flux:heading>
        </div>
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>{{ auth()->user()->isNasabah() ? 'Perlu Perbaikan' : 'Selesai' }}</flux:text>
            <flux:heading level="2" size="xl" class="mt-1">{{ auth()->user()->isNasabah() ? $this->ringkasan['perlu_perbaikan'] : $this->ringkasan['selesai'] }}</flux:heading>
        </div>
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>Total Pembayaran (per Mata Uang)</flux:text>
            @forelse ($this->ringkasan['pembayaran_per_mata_uang'] as $baris)
                <flux:heading level="2" size="lg" class="mt-1">{{ $baris['mata_uang']->value }} {{ number_format($baris['total'], 2, ',', '.') }}</flux:heading>
            @empty
                <flux:heading level="2" size="xl" class="mt-1">-</flux:heading>
            @endforelse
        </div>
    </div>

    @if ($tampilkanTren)
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading level="2">Tren Pengajuan</flux:heading>
            <div class="mt-4 flex h-40 items-end gap-1">
                @foreach ($this->tren as $bucket)
                    <div class="flex flex-1 flex-col items-center justify-end gap-1 self-stretch">
                        <flux:text size="sm">{{ $bucket['jumlah'] > 0 ? $bucket['jumlah'] : '' }}</flux:text>
                        <div class="w-full rounded-t bg-blue-500" style="height: {{ max($bucket['persen_tinggi'], $bucket['jumlah'] > 0 ? 4 : 0) }}%" title="{{ $bucket['label'] }}: {{ $bucket['jumlah'] }} pengajuan"></div>
                        <flux:text size="sm" class="text-center">{{ $bucket['label'] }}</flux:text>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        @if ($tampilkanDistribusiStatus)
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
                <flux:heading level="2">Distribusi Status</flux:heading>
                <div class="mt-4 space-y-3">
                    @foreach ($this->distribusi as $baris)
                        <div>
                            <div class="flex items-center justify-between">
                                <flux:badge :color="$baris['status']->color()">{{ $baris['status']->label() }}</flux:badge>
                                <flux:text size="sm">{{ $baris['jumlah'] }} ({{ $baris['persen'] }}%)</flux:text>
                            </div>
                            <div class="mt-1 h-2 w-full rounded bg-zinc-200 dark:bg-zinc-700">
                                <div class="h-2 rounded bg-blue-500" style="width: {{ $baris['persen'] }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($tampilkanPerLayanan)
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
                <flux:heading level="2">Pengajuan per Layanan</flux:heading>
                <div class="mt-4 space-y-3">
                    @forelse ($this->perLayanan as $baris)
                        <div>
                            <div class="flex items-center justify-between">
                                <flux:text>{{ $baris['layanan']->nama }}</flux:text>
                                <flux:text size="sm">{{ $baris['jumlah'] }} ({{ $baris['persen'] }}%)</flux:text>
                            </div>
                            <div class="mt-1 h-2 w-full rounded bg-zinc-200 dark:bg-zinc-700">
                                <div class="h-2 rounded bg-emerald-500" style="width: {{ $baris['persen'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <flux:text>Belum ada data layanan.</flux:text>
                    @endforelse
                </div>
            </div>
        @endif
    </div>

    @if ($tampilkanPemeriksaan)
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
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
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
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

    <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
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
