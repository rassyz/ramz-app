<?php

use App\Enums\PeriodeDashboard;
use App\Services\LaporanService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Laporan')] class extends Component
{
    use WithPagination;

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

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Daftar rinci pengajuan terpaginasi untuk periode terpilih;
     * isi dan urutannya sama dengan daftar pada laporan lengkap.
     */
    #[Computed]
    public function pengajuansRinci()
    {
        [$mulai, $selesai] = $this->rentang;

        return app(LaporanService::class)->daftarPengajuanPaginasi($mulai, $selesai);
    }
}; ?>
<section class="w-full">
    @php
        $pillStatusLap = [
            'menunggu_verifikasi' => 'pill-amber',
            'perlu_perbaikan' => 'pill-red',
            'terverifikasi' => 'pill-indigo',
            'diproses' => 'pill-blue',
            'selesai' => 'pill-green',
            'dibatalkan' => 'pill-zinc',
        ];
        $warnaStatusLap = [
            'menunggu_verifikasi' => '#D97706',
            'perlu_perbaikan' => '#DC2626',
            'terverifikasi' => '#4353FF',
            'diproses' => '#0284C7',
            'selesai' => '#059669',
            'dibatalkan' => '#A1A1AA',
        ];
    @endphp

    {{-- Baris judul + tombol ikon (selaras mockup) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Laporan Pengajuan</h1>
            <p class="mt-1 text-[13px] text-mist dark:text-zinc-400">Laporan periode {{ $this->rentang[0]->format('d M Y') }} — {{ $this->rentang[1]->format('d M Y') }}, dihasilkan dari data hidup. Angka ringkasan sama dengan dashboard untuk periode yang sama.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('notifikasis.index') }}" wire:navigate class="relative hidden size-10 items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Notifikasi" title="Notifikasi">
                <flux:icon name="bell" class="size-5" />
                @php
                    $belumDibacaLaporan = auth()->user()->notifikasis()->whereNull('dibaca_at')->count();
                @endphp
                @if ($belumDibacaLaporan > 0)
                    <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $belumDibacaLaporan }}</span>
                @endif
            </a>
            <button type="button" x-data x-on:click="$flux.dark = !$flux.dark" class="hidden size-10 cursor-pointer items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Ganti tema gelap atau terang" title="Mode gelap / terang">
                <flux:icon.moon class="size-5 dark:hidden" />
                <flux:icon.sun class="hidden size-5 dark:block" />
            </button>
        </div>
    </div>

    <div class="bento mt-5">
        <section class="tile md:col-span-2 xl:col-span-12">
            <div class="flex flex-wrap items-end gap-3">
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
                <a href="{{ route('admin.laporan.unduh', ['mulai' => $this->rentang[0]->format('Y-m-d'), 'selesai' => $this->rentang[1]->format('Y-m-d')]) }}" class="btn btn-primary sm:ml-auto">Unduh CSV</a>
            </div>
        </section>

        @foreach ([
            ['Total Pengajuan', $this->laporan['ringkasan']['total']],
            ['Menunggu Verifikasi', $this->laporan['ringkasan']['menunggu_verifikasi']],
            ['Selesai', $this->laporan['ringkasan']['selesai']],
            ['Dokumen Terunggah', $this->laporan['ringkasan']['total_dokumen']],
        ] as [$labelKpi, $nilaiKpi])
            <section class="tile md:col-span-1 xl:col-span-3">
                <p class="text-[11px] font-bold tracking-[0.07em] text-mist uppercase dark:text-zinc-400">{{ $labelKpi }}</p>
                <p class="mt-1.5 text-[29px] leading-none font-extrabold text-zinc-900 dark:text-white">{{ $nilaiKpi }}</p>
            </section>
        @endforeach

        <section class="tile md:col-span-1 xl:col-span-6">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Distribusi Status Pengajuan</h2>
            </div>
            @foreach ($this->laporan['distribusi'] as $baris)
                <div class="mb-3.5 last:mb-0">
                    <div class="flex items-center justify-between gap-3 text-[13px]">
                        <span class="pill {{ $pillStatusLap[$baris['status']->value] ?? 'pill-zinc' }}">{{ $baris['status']->label() }}</span>
                        <b class="font-bold text-zinc-900 dark:text-white">{{ $baris['jumlah'] }} ({{ $baris['persen'] }}%)</b>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-soft dark:bg-night-soft"><span class="block h-full rounded-full" style="width: {{ $baris['persen'] }}%; background: {{ $warnaStatusLap[$baris['status']->value] ?? '#A1A1AA' }}"></span></div>
                </div>
            @endforeach
        </section>

        <section class="tile md:col-span-1 xl:col-span-6">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Pengajuan per Layanan</h2>
            </div>
            @foreach ($this->laporan['per_layanan'] as $baris)
                <div class="mb-3.5 last:mb-0">
                    <div class="flex items-baseline justify-between gap-3 text-[13px]">
                        <span class="font-medium text-zinc-600 dark:text-zinc-300">{{ $baris['layanan']->nama }}</span>
                        <b class="font-bold text-zinc-900 dark:text-white">{{ $baris['jumlah'] }} pengajuan ({{ $baris['persen'] }}%)</b>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-soft dark:bg-night-soft"><span class="block h-full rounded-full" style="width: {{ $baris['persen'] }}%; background: {{ $baris['layanan']->kode === 'IMP' ? '#0284C7' : '#4353FF' }}"></span></div>
                </div>
            @endforeach
        </section>

        <section class="tile md:col-span-2 xl:col-span-12">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Ringkasan Pemeriksaan Checker</h2>
                <span class="text-[12.5px] text-mist dark:text-zinc-400">{{ $this->laporan['pemeriksaan']['total'] }} pemeriksaan</span>
            </div>
            @php
                $totalPeriksa = max(1, (int) $this->laporan['pemeriksaan']['total']);
                $lebarTidak = (int) round($this->laporan['pemeriksaan']['tidak_sesuai'] / $totalPeriksa * 100);
                $lebarBelum = (int) round($this->laporan['pemeriksaan']['belum'] / $totalPeriksa * 100);
            @endphp
            <div class="grid gap-x-8 gap-y-3.5 sm:grid-cols-3">
                <div>
                    <div class="flex items-baseline justify-between gap-3 text-[13px]">
                        <span class="font-medium text-zinc-600 dark:text-zinc-300">Sesuai</span>
                        <b class="font-bold text-zinc-900 dark:text-white">{{ $this->laporan['pemeriksaan']['sesuai'] }} ({{ $this->laporan['pemeriksaan']['persen_sesuai'] }}%)</b>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-soft dark:bg-night-soft"><span class="block h-full rounded-full" style="width: {{ $this->laporan['pemeriksaan']['persen_sesuai'] }}%; background: #059669"></span></div>
                </div>
                <div>
                    <div class="flex items-baseline justify-between gap-3 text-[13px]">
                        <span class="font-medium text-zinc-600 dark:text-zinc-300">Tidak Sesuai</span>
                        <b class="font-bold text-zinc-900 dark:text-white">{{ $this->laporan['pemeriksaan']['tidak_sesuai'] }} ({{ $lebarTidak }}%)</b>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-soft dark:bg-night-soft"><span class="block h-full rounded-full" style="width: {{ $lebarTidak }}%; background: #DC2626"></span></div>
                </div>
                <div>
                    <div class="flex items-baseline justify-between gap-3 text-[13px]">
                        <span class="font-medium text-zinc-600 dark:text-zinc-300">Belum Dapat Diperiksa</span>
                        <b class="font-bold text-zinc-900 dark:text-white">{{ $this->laporan['pemeriksaan']['belum'] }} ({{ $lebarBelum }}%)</b>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-soft dark:bg-night-soft"><span class="block h-full rounded-full" style="width: {{ $lebarBelum }}%; background: #A1A1AA"></span></div>
                </div>
            </div>
        </section>

        <section class="tile md:col-span-2 xl:col-span-12">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Daftar Rinci Pengajuan</h2>
            </div>
            @if ($this->pengajuansRinci->isEmpty())
                <p class="text-[13.5px] text-zinc-500 dark:text-zinc-400">Tidak ada pengajuan pada periode ini.</p>
            @else
                <div class="-mx-4.25 overflow-x-auto px-4.25 sm:-mx-5.5 sm:px-5.5">
                    <table class="tabel-kartu w-full text-sm">
                        <thead>
                            <tr class="border-b border-line text-left dark:border-night-line">
                                <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Nomor Pengajuan</th>
                                <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Tanggal</th>
                                <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Nasabah</th>
                                <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Layanan</th>
                                <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Status</th>
                                <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Nomor LC</th>
                                <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Nominal Acuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->pengajuansRinci as $pengajuan)
                                <tr class="border-b border-line last:border-b-0 dark:border-night-line" wire:key="laporan-{{ $pengajuan->id }}">
                                    <td class="py-3.25 pr-3 font-mono text-[13px] font-bold whitespace-nowrap text-zinc-900 dark:text-white" data-label="Nomor Pengajuan">{{ $pengajuan->nomor_pengajuan }}</td>
                                    <td class="py-3.25 pr-3 text-[13.5px] whitespace-nowrap text-zinc-600 dark:text-zinc-300" data-label="Tanggal">{{ $pengajuan->created_at->format('d M Y') }}</td>
                                    <td class="py-3.25 pr-3 text-[13.5px] text-zinc-600 dark:text-zinc-300" data-label="Nasabah">{{ $pengajuan->user->name }}</td>
                                    <td class="py-3.25 pr-3 text-[13.5px] whitespace-nowrap text-zinc-600 dark:text-zinc-300" data-label="Layanan">{{ $pengajuan->layanan->nama }}</td>
                                    <td class="py-3.25 pr-3" data-label="Status"><span class="pill {{ $pillStatusLap[$pengajuan->status->value] ?? 'pill-zinc' }}">{{ $pengajuan->status->label() }}</span></td>
                                    <td class="py-3.25 pr-3 font-mono text-[13px] whitespace-nowrap text-zinc-600 dark:text-zinc-300" data-label="Nomor LC">{{ $pengajuan->nomor_lc ?? '-' }}</td>
                                    <td class="py-3.25 pr-3 text-[13.5px] whitespace-nowrap text-zinc-600 dark:text-zinc-300" data-label="Nominal Acuan">{{ $pengajuan->nominal_acuan !== null ? ($pengajuan->mata_uang_acuan?->value.' '.number_format((float) $pengajuan->nominal_acuan, 2, ',', '.')) : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @if ($this->pengajuansRinci->total() > 0)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-mist dark:text-zinc-400">
                    <span>Menampilkan {{ $this->pengajuansRinci->firstItem() }}–{{ $this->pengajuansRinci->lastItem() }} dari {{ $this->pengajuansRinci->total() }} pengajuan</span>
                    @if ($this->pengajuansRinci->hasPages())
                        @php
                            $halamanIni = $this->pengajuansRinci->currentPage();
                            $halamanAkhir = $this->pengajuansRinci->lastPage();
                            $daftarHalaman = range(max(1, $halamanIni - 2), min($halamanAkhir, $halamanIni + 2));
                        @endphp
                        <div class="flex items-center gap-1.5">
                            <button type="button" wire:click="previousPage" class="chip {{ $this->pengajuansRinci->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman sebelumnya">‹</button>
                            @foreach ($daftarHalaman as $halaman)
                                <button type="button" wire:click="gotoPage({{ $halaman }})" class="chip {{ $halaman === $halamanIni ? 'chip-aktif' : '' }}">{{ $halaman }}</button>
                            @endforeach
                            <button type="button" wire:click="nextPage" class="chip {{ $halamanIni === $halamanAkhir ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman berikutnya">›</button>
                        </div>
                    @endif
                </div>
            @endif
            @endif
        </section>
    </div>
</section>
