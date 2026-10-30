<?php

use App\Enums\PeriodeDashboard;
use App\Services\LaporanService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Laporan')] class extends Component
{
    public string $periode = '30_hari';

    public string $tanggalMulai = '';

    public string $tanggalSelesai = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    /**
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
    public function laporan()
    {
        [$mulai, $selesai] = $this->rentang;

        return app(LaporanService::class)->buat(auth()->user(), $mulai, $selesai);
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading level="1" size="xl">Laporan Pengajuan</flux:heading>
            <flux:subheading>
                Laporan periode {{ $this->rentang[0]->format('d M Y') }} — {{ $this->rentang[1]->format('d M Y') }},
                dihasilkan dari data hidup. Angka ringkasan sama dengan dashboard untuk periode yang sama.
            </flux:subheading>
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
            <flux:button variant="primary" :href="route('admin.laporan.unduh', ['mulai' => $this->rentang[0]->format('Y-m-d'), 'selesai' => $this->rentang[1]->format('Y-m-d')])">Unduh CSV</flux:button>
        </div>
    </div>

    <div class="mt-6 grid auto-rows-min gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>Total Pengajuan</flux:text>
            <flux:heading level="2" size="xl" class="mt-1">{{ $this->laporan['ringkasan']['total'] }}</flux:heading>
        </div>
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>Menunggu Verifikasi</flux:text>
            <flux:heading level="2" size="xl" class="mt-1">{{ $this->laporan['ringkasan']['menunggu_verifikasi'] }}</flux:heading>
        </div>
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>Selesai</flux:text>
            <flux:heading level="2" size="xl" class="mt-1">{{ $this->laporan['ringkasan']['selesai'] }}</flux:heading>
        </div>
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>Total Pembayaran (per Mata Uang)</flux:text>
            @forelse ($this->laporan['ringkasan']['pembayaran_per_mata_uang'] as $baris)
                <flux:heading level="2" size="lg" class="mt-1">{{ $baris['mata_uang']->value }} {{ number_format($baris['total'], 2, ',', '.') }}</flux:heading>
            @empty
                <flux:heading level="2" size="xl" class="mt-1">-</flux:heading>
            @endforelse
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading level="2">Distribusi Status Pengajuan</flux:heading>
            <flux:table class="mt-3">
                <flux:table.rows>
                    @foreach ($this->laporan['distribusi'] as $baris)
                        <flux:table.row>
                            <flux:table.cell><flux:badge :color="$baris['status']->color()">{{ $baris['status']->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="text-right">{{ $baris['jumlah'] }} ({{ $baris['persen'] }}%)</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading level="2">Pengajuan per Layanan</flux:heading>
            <flux:table class="mt-3">
                <flux:table.rows>
                    @foreach ($this->laporan['per_layanan'] as $baris)
                        <flux:table.row>
                            <flux:table.cell>{{ $baris['layanan']->nama }}</flux:table.cell>
                            <flux:table.cell class="text-right">{{ $baris['jumlah'] }} ({{ $baris['persen'] }}%)</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading level="2">Pembayaran per Status</flux:heading>
            <flux:table class="mt-3">
                <flux:table.rows>
                    @foreach ($this->laporan['pembayaran_per_status'] as $baris)
                        <flux:table.row>
                            <flux:table.cell><flux:badge :color="$baris['status']->color()">{{ $baris['status']->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ $baris['jumlah'] }} pembayaran</flux:table.cell>
                            <flux:table.cell class="text-right">
                                @forelse ($baris['total_per_mata_uang'] as $total)
                                    <div>{{ $total['mata_uang']->value }} {{ number_format($total['total'], 2, ',', '.') }}</div>
                                @empty
                                    -
                                @endforelse
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading level="2">Ringkasan Pemeriksaan Checker</flux:heading>
            <div class="mt-3 space-y-2">
                <flux:text class="block">Total pemeriksaan: <strong>{{ $this->laporan['pemeriksaan']['total'] }}</strong></flux:text>
                <flux:text class="block">Sesuai: <strong>{{ $this->laporan['pemeriksaan']['sesuai'] }} ({{ $this->laporan['pemeriksaan']['persen_sesuai'] }}%)</strong></flux:text>
                <flux:text class="block">Tidak sesuai: <strong>{{ $this->laporan['pemeriksaan']['tidak_sesuai'] }}</strong></flux:text>
                <flux:text class="block">Belum dapat diperiksa: <strong>{{ $this->laporan['pemeriksaan']['belum'] }}</strong></flux:text>
            </div>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
        <flux:heading level="2">Daftar Rinci Pengajuan</flux:heading>
        @if ($this->laporan['pengajuans']->isEmpty())
            <flux:text class="mt-3 block">Tidak ada pengajuan pada periode ini.</flux:text>
        @else
            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>Nomor Pengajuan</flux:table.column>
                    <flux:table.column>Tanggal</flux:table.column>
                    <flux:table.column>Nasabah</flux:table.column>
                    <flux:table.column>Layanan</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Pembayaran</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->laporan['pengajuans'] as $pengajuan)
                        <flux:table.row :key="$pengajuan->id">
                            <flux:table.cell class="font-medium">{{ $pengajuan->nomor_pengajuan }}</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->created_at->format('d M Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->user->name }}</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->layanan->nama }}</flux:table.cell>
                            <flux:table.cell><flux:badge :color="$pengajuan->status->color()">{{ $pengajuan->status->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell>
                                @if ($pengajuan->pembayaran)
                                    {{ $pengajuan->pembayaran->nominalFormatted() }} — {{ $pengajuan->pembayaran->status->label() }}
                                @else
                                    -
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</section>
