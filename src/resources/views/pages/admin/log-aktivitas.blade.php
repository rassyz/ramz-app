<?php

use App\Models\LogAktivitas;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Log Aktivitas')] class extends Component
{
    use WithPagination;

    public string $filterAktivitas = '';

    public string $tanggalMulai = '';

    public string $tanggalSelesai = '';

    public string $cariPengajuan = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function logs()
    {
        return LogAktivitas::query()
            ->with(['user', 'pengajuan'])
            ->when($this->filterAktivitas !== '', fn ($q) => $q->where('aktivitas', $this->filterAktivitas))
            ->when($this->tanggalMulai !== '', fn ($q) => $q->whereDate('created_at', '>=', $this->tanggalMulai))
            ->when($this->tanggalSelesai !== '', fn ($q) => $q->whereDate('created_at', '<=', $this->tanggalSelesai))
            ->when($this->cariPengajuan !== '', fn ($q) => $q->whereHas(
                'pengajuan',
                fn ($sub) => $sub->where('nomor_pengajuan', 'like', '%'.$this->cariPengajuan.'%')
            ))
            ->latest('id')
            ->paginate(20);
    }
}; ?>
<section class="w-full">
    @php
        $warnaLog = fn (string $jenis): string => match (true) {
            str_contains($jenis, 'pemeriksaan') => 'pill-indigo',
            str_contains($jenis, 'dihapus'), str_contains($jenis, 'dibatalkan') => 'pill-red',
            str_contains($jenis, 'verifikasi') => 'pill-amber',
            str_contains($jenis, 'dibuat') => 'pill-green',
            str_contains($jenis, 'dokumen') => 'pill-blue',
            str_contains($jenis, 'data'), str_contains($jenis, 'persyaratan'), str_contains($jenis, 'aturan') => 'pill-violet',
            default => 'pill-zinc',
        };
    @endphp

    {{-- Baris judul + tombol ikon (selaras mockup) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Log Aktivitas</h1>
            <p class="mt-1 text-[13px] text-mist dark:text-zinc-400">Catatan aktivitas bisnis seluruh pengguna: siapa melakukan apa, kapan, dan pada pengajuan mana.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('notifikasis.index') }}" wire:navigate class="relative hidden size-10 items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Notifikasi" title="Notifikasi">
                <flux:icon name="bell" class="size-5" />
                @php
                    $belumDibacaLog = auth()->user()->notifikasis()->whereNull('dibaca_at')->count();
                @endphp
                @if ($belumDibacaLog > 0)
                    <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $belumDibacaLog }}</span>
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
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:select wire:model.live="filterAktivitas" label="Aktivitas">
                    <option value="">Semua Aktivitas</option>
                    @foreach (LogAktivitas::labels() as $kunci => $label)
                        <option value="{{ $kunci }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model.live="tanggalMulai" type="date" label="Dari Tanggal" />
                <flux:input wire:model.live="tanggalSelesai" type="date" label="Sampai Tanggal" />
                <flux:input wire:model.live.debounce.400ms="cariPengajuan" type="search" label="Nomor Pengajuan" placeholder="PGJ-..." />
            </div>
        </section>

        <section class="tile md:col-span-2 xl:col-span-8">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Aktivitas Terakhir</h2>
                <span class="text-[12.5px] text-mist dark:text-zinc-400">{{ $this->logs->total() }} catatan</span>
            </div>
            @if ($this->logs->isEmpty())
                <p class="text-[13.5px] text-zinc-500 dark:text-zinc-400">Tidak ada catatan aktivitas yang cocok dengan filter.</p>
            @else
                <div class="relative pl-6.5">
                    <span class="absolute top-1.5 bottom-1.5 left-2 w-0.5 bg-line dark:bg-night-line"></span>
                    @foreach ($this->logs as $log)
                        <div class="relative pb-4.5 last:pb-0.5" wire:key="log-{{ $log->id }}">
                            <span class="absolute top-0.75 left-[-24.5px] size-3.25 rounded-full border-[3px] border-primary bg-white dark:bg-night-tile"></span>
                            <p><span class="pill {{ $warnaLog($log->aktivitas) }}">{{ $log->label() }}</span></p>
                            <p class="mt-1.5 text-[13px] leading-normal text-zinc-600 dark:text-zinc-300">{{ $log->deskripsi }}</p>
                            @if ($log->konteks)
                                <p class="mt-1 text-xs leading-normal text-zinc-500 dark:text-zinc-400">
                                    @foreach ($log->konteks as $kunci => $nilai)
                                        <span>{{ $kunci }}: {{ is_scalar($nilai) ? $nilai : json_encode($nilai) }}</span>@unless ($loop->last) · @endunless
                                    @endforeach
                                </p>
                            @endif
                            <p class="mt-1 text-xs leading-normal text-mist dark:text-zinc-400">{{ $log->created_at->format('d M Y H:i') }} — oleh {{ $log->user?->name ?? 'Sistem' }}@if ($log->pengajuan) · <a href="{{ route('pengajuans.show', $log->pengajuan) }}" wire:navigate class="font-semibold text-primary hover:underline">{{ $log->pengajuan->nomor_pengajuan }}</a>@endif</p>
                        </div>
                    @endforeach
                </div>
            @if ($this->logs->total() > 0)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-mist dark:text-zinc-400">
                    <span>Menampilkan {{ $this->logs->firstItem() }}–{{ $this->logs->lastItem() }} dari {{ $this->logs->total() }} catatan</span>
                    @if ($this->logs->hasPages())
                        @php
                            $halamanIni = $this->logs->currentPage();
                            $halamanAkhir = $this->logs->lastPage();
                            $daftarHalaman = range(max(1, $halamanIni - 2), min($halamanAkhir, $halamanIni + 2));
                        @endphp
                        <div class="flex items-center gap-1.5">
                            <button type="button" wire:click="previousPage" class="chip {{ $this->logs->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman sebelumnya">‹</button>
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

        <section class="tile md:col-span-2 xl:col-span-4">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Tentang Log Ini</h2>
            </div>
            <p class="text-[13.5px] leading-[1.7] text-mist dark:text-zinc-400">Setiap tindakan penting dicatat beserta pelaku dan waktunya: pembuatan pengajuan, perubahan Data LC dan persyaratan, unggah/hapus dokumen, pemeriksaan, keputusan verifikasi, sampai penghapusan pengajuan oleh Admin.</p>
            <p class="mt-3 text-[13.5px] leading-[1.7] text-mist dark:text-zinc-400">Baris log <b class="font-semibold text-zinc-900 dark:text-zinc-100">tetap tersimpan</b> walau pengajuan terkait dihapus — keterkaitannya dilepas, isinya tidak.</p>
        </section>
    </div>
</section>
