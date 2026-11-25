<?php

use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pengajuan Saya')] class extends Component
{
    use WithPagination;

    public string $search = '';

    /** @var string '' | 'aktif' | 'perlu_perbaikan' | 'selesai' */
    public string $filter = '';

    /** Kartu aktif bawaan hanya 2 teratas; sisanya dibuka tombol. */
    public bool $tampilkanSemuaAktif = false;

    /**
     * Halaman ini hanya untuk Nasabah.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()->isNasabah(), 403);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * Pengajuan yang masih berjalan (belum selesai/dibatalkan) untuk
     * kartu-kartu aktif, lengkap dengan jumlah dokumen terunggah
     * untuk bilah progres kartu.
     */
    #[Computed]
    public function pengajuanAktif()
    {
        return auth()->user()->pengajuans()
            ->with(['layanan'])
            ->withCount('dokumens')
            ->whereNotIn('status', [StatusPengajuan::Selesai, StatusPengajuan::Dibatalkan])
            ->when($this->filter === 'perlu_perbaikan', fn ($query) => $query->where('status', StatusPengajuan::PerluPerbaikan))
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('nomor_pengajuan', 'like', '%'.$this->search.'%')
                        ->orWhere('nomor_lc', 'like', '%'.$this->search.'%');
                });
            })
            ->orderByRaw('case when status = ? then 0 else 1 end', [StatusPengajuan::PerluPerbaikan->value])
            ->oldest()
            ->get();
    }

    /**
     * Riwayat: pengajuan selesai/dibatalkan, terpaginasi, beserta
     * transisi status terakhirnya sebagai tanggal berakhir.
     */
    #[Computed]
    public function riwayat()
    {
        return auth()->user()->pengajuans()
            ->with(['layanan', 'riwayatStatuses' => fn ($query) => $query->whereIn('status_ke', [StatusPengajuan::Selesai, StatusPengajuan::Dibatalkan])->latest()])
            ->whereIn('status', [StatusPengajuan::Selesai, StatusPengajuan::Dibatalkan])
            ->when($this->filter === 'selesai', fn ($query) => $query->where('status', StatusPengajuan::Selesai))
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('nomor_pengajuan', 'like', '%'.$this->search.'%')
                        ->orWhere('nomor_lc', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate(10);
    }

}; ?>

<section class="w-full">
    {{-- Baris judul + tombol ikon (selaras mockup) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Pengajuan Saya</h1>
            <p class="mt-1 text-[13px] text-mist dark:text-zinc-400">Pengajuan dibuat oleh tim layanan setelah LC terbit — Anda memantau dan melengkapi dokumennya.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('notifikasis.index') }}" wire:navigate class="relative hidden size-10 items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Notifikasi" title="Notifikasi">
                <flux:icon name="bell" class="size-5" />
                @php
                    $belumDibacaSaya = auth()->user()->notifikasis()->whereNull('dibaca_at')->count();
                @endphp
                @if ($belumDibacaSaya > 0)
                    <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $belumDibacaSaya }}</span>
                @endif
            </a>
            <button type="button" x-data x-on:click="$flux.dark = !$flux.dark" class="hidden size-10 cursor-pointer items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Ganti tema gelap atau terang" title="Mode gelap / terang">
                <flux:icon.moon class="size-5 dark:hidden" />
                <flux:icon.sun class="hidden size-5 dark:block" />
            </button>
        </div>
    </div>

    @php
        $pillStatus = [
            'menunggu_verifikasi' => 'pill-amber',
            'perlu_perbaikan' => 'pill-red',
            'terverifikasi' => 'pill-indigo',
            'diproses' => 'pill-blue',
            'selesai' => 'pill-green',
            'dibatalkan' => 'pill-zinc',
        ];
    @endphp

    <div class="bento mt-5">
        {{-- Bilah saring + cari --}}
        <section class="tile py-4 md:col-span-2 xl:col-span-12">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex flex-nowrap items-center gap-1.5 overflow-x-auto scrollbar-none [&::-webkit-scrollbar]:hidden">
                    <button type="button" wire:click="$set('filter', '')" class="chip {{ $filter === '' ? 'chip-aktif' : '' }}">Semua</button>
                    <button type="button" wire:click="$set('filter', 'aktif')" class="chip {{ $filter === 'aktif' ? 'chip-aktif' : '' }}">Aktif</button>
                    <button type="button" wire:click="$set('filter', 'perlu_perbaikan')" class="chip {{ $filter === 'perlu_perbaikan' ? 'chip-aktif' : '' }}">Perlu Perbaikan</button>
                    <button type="button" wire:click="$set('filter', 'selesai')" class="chip {{ $filter === 'selesai' ? 'chip-aktif' : '' }}">Selesai</button>
                </div>
                <div class="min-w-52.5 flex-1">
                    <flux:input wire:model.live="search" type="search" placeholder="Cari nomor pengajuan atau nomor LC…" class="w-full" />
                </div>
            </div>
        </section>

        {{-- Kartu pengajuan aktif --}}
        @if ($filter !== 'selesai')
            <div class="-mb-1.5 text-[13px] font-bold tracking-[0.02em] text-mist md:col-span-2 xl:col-span-12 dark:text-zinc-400">PENGAJUAN AKTIF — {{ $this->pengajuanAktif->count() }}</div>
            @php
                $kartuAktif = $tampilkanSemuaAktif ? $this->pengajuanAktif : $this->pengajuanAktif->take(2);
            @endphp
            @forelse ($kartuAktif as $pengajuan)
                <section class="tile md:col-span-1 xl:col-span-6" wire:key="aktif-{{ $pengajuan->id }}">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span class="font-mono text-[15px] font-bold text-zinc-900 dark:text-white">{{ $pengajuan->nomor_pengajuan }}</span>
                        @if ($pengajuan->layanan->kode === 'EKS')
                            <span class="pill pill-indigo">Ekspor</span>
                        @elseif ($pengajuan->layanan->kode === 'IMP')
                            <span class="pill pill-blue">Impor</span>
                        @else
                            <span class="pill pill-zinc">{{ $pengajuan->layanan->nama }}</span>
                        @endif
                        <span class="pill {{ $pillStatus[$pengajuan->status->value] ?? 'pill-zinc' }} ml-auto">{{ $pengajuan->status->label() }}</span>
                    </div>
                    <p class="mt-2 text-[12.5px] text-mist dark:text-zinc-400">{{ $pengajuan->layanan->nama }}@if ($pengajuan->nomor_lc) — LC {{ $pengajuan->nomor_lc }}@endif • {{ $pengajuan->created_at->diffForHumans() }}</p>
                    @php
                        $totalSyarat = count($pengajuan->persyaratanBerlaku());
                        $persenDokumen = $totalSyarat > 0 ? min(100, (int) round($pengajuan->dokumens_count / $totalSyarat * 100)) : 0;
                    @endphp
                    @if ($totalSyarat > 0)
                        <div class="mt-3.75 flex items-center justify-between text-[12.5px] text-mist dark:text-zinc-400">
                            <span>Dokumen terunggah</span>
                            <b class="font-bold text-ink dark:text-zinc-100">{{ $pengajuan->dokumens_count }} dari {{ $totalSyarat }}</b>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-soft dark:bg-night-soft">
                            <div class="h-full rounded-full {{ $pengajuan->status->value === 'perlu_perbaikan' ? 'bg-amber-600' : 'bg-primary' }}" style="width: {{ $persenDokumen }}%"></div>
                        </div>
                    @endif
                    @if ($pengajuan->status->value === 'perlu_perbaikan')
                        <div class="mt-3.25 rounded-xl bg-amber-50 px-3 py-2.5 text-[12.5px] leading-normal text-amber-600 dark:bg-[#2e2008] dark:text-amber-400">{{ $pengajuan->catatan ?? 'Ada dokumen yang perlu diperbaiki. Buka detail untuk melihat catatan operator.' }}</div>
                        <div class="mt-3.5">
                            <a href="{{ route('pengajuans.show', $pengajuan) }}" wire:navigate class="btn btn-primary btn-small">Perbaiki Sekarang</a>
                        </div>
                    @else
                        <div class="mt-3.5 text-right">
                            <a href="{{ route('pengajuans.show', $pengajuan) }}" wire:navigate class="text-[12.5px] font-semibold text-primary hover:underline">Lihat Detail →</a>
                        </div>
                    @endif
                </section>
            @empty
                <div class="text-[13.5px] text-zinc-500 md:col-span-2 xl:col-span-12 dark:text-zinc-400">Tidak ada pengajuan aktif.</div>
            @endforelse
            @if ($this->pengajuanAktif->count() > 2)
                <div class="md:col-span-2 xl:col-span-12">
                    <button type="button" wire:click="$toggle('tampilkanSemuaAktif')" class="btn w-full sm:w-auto">{{ $tampilkanSemuaAktif ? 'Tampilkan lebih sedikit' : 'Tampilkan semua ('.$this->pengajuanAktif->count().' pengajuan aktif)' }}</button>
                </div>
            @endif
        @endif

        {{-- Riwayat pengajuan --}}
        @if ($filter !== 'aktif' && $filter !== 'perlu_perbaikan')
            <section class="tile md:col-span-2 xl:col-span-12">
                <div class="mb-4 flex items-center justify-between gap-2.5">
                    <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Riwayat Pengajuan</h2>
                    <span class="text-[12.5px] text-mist dark:text-zinc-400">{{ $this->riwayat->total() }} pengajuan</span>
                </div>
                <div class="-mx-4.25 overflow-x-auto px-4.25 sm:-mx-5.5 sm:px-5.5">
                    <table class="tabel-kartu w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">
                                <th class="pb-2.5">Nomor</th>
                                <th class="pb-2.5">Layanan</th>
                                <th class="pb-2.5">Nomor LC</th>
                                <th class="pb-2.5">Status</th>
                                <th class="pb-2.5">Selesai</th>
                                <th class="pb-2.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->riwayat as $pengajuan)
                                @php
                                    $tanggalAkhir = $pengajuan->riwayatStatuses->first()?->created_at ?? $pengajuan->updated_at;
                                @endphp
                                <tr class="group relative cursor-pointer border-t border-line transition-colors hover:bg-soft dark:border-night-line dark:hover:bg-night-soft" wire:key="riwayat-{{ $pengajuan->id }}">
                                    <td class="py-3.25 pr-3" data-label="Nomor">
                                        <a href="{{ route('pengajuans.show', $pengajuan) }}" wire:navigate class="font-mono text-[13px] font-bold whitespace-nowrap text-zinc-900 after:absolute after:inset-0 after:content-[''] hover:text-primary dark:text-white">{{ $pengajuan->nomor_pengajuan }}</a>
                                    </td>
                                    <td class="py-3.25 pr-3 text-[13.5px] whitespace-nowrap text-zinc-600 dark:text-zinc-300" data-label="Layanan">{{ $pengajuan->layanan->nama }}</td>
                                    <td class="py-3.25 pr-3 font-mono text-[13px] whitespace-nowrap text-zinc-600 dark:text-zinc-300" data-label="Nomor LC">{{ $pengajuan->nomor_lc ?? '—' }}</td>
                                    <td class="py-3.25 pr-3" data-label="Status"><span class="pill {{ $pillStatus[$pengajuan->status->value] ?? 'pill-zinc' }}">{{ $pengajuan->status->label() }}</span></td>
                                    <td class="py-3.25 pr-3 text-[13.5px] whitespace-nowrap text-zinc-500 dark:text-zinc-400" data-label="Selesai">{{ $tanggalAkhir->format('d M Y') }}</td>
                                    <td class="aksi py-3.25" data-label="">
                                        <span class="text-lg text-zinc-400 group-hover:text-primary" aria-hidden="true">→</span>
                                    </td>
                                </tr>
                            @empty
                                <tr class="border-t border-line dark:border-night-line">
                                    <td class="py-6 text-center text-[13.5px] text-zinc-500 dark:text-zinc-400" colspan="6">Belum ada riwayat pengajuan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($this->riwayat->total() > 0)
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-mist dark:text-zinc-400">
                        <span>Menampilkan {{ $this->riwayat->firstItem() }}–{{ $this->riwayat->lastItem() }} dari {{ $this->riwayat->total() }} riwayat</span>
                        @if ($this->riwayat->hasPages())
                            @php
                                $halamanIni = $this->riwayat->currentPage();
                                $halamanAkhir = $this->riwayat->lastPage();
                                $daftarHalaman = range(max(1, $halamanIni - 2), min($halamanAkhir, $halamanIni + 2));
                            @endphp
                            <div class="flex items-center gap-1.5">
                                <button type="button" wire:click="previousPage" class="chip {{ $this->riwayat->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman sebelumnya">‹</button>
                                @foreach ($daftarHalaman as $halaman)
                                    <button type="button" wire:click="gotoPage({{ $halaman }})" class="chip {{ $halaman === $halamanIni ? 'chip-aktif' : '' }}">{{ $halaman }}</button>
                                @endforeach
                                <button type="button" wire:click="nextPage" class="chip {{ $halamanIni === $halamanAkhir ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman berikutnya">›</button>
                            </div>
                        @endif
                    </div>
                @endif
            </section>
        @endif

        {{-- Penutup: pengajuan baru diterbitkan staf --}}
        <section class="tile p-7.5 text-center md:col-span-2 xl:col-span-12">
            <div class="text-sm font-bold text-zinc-900 dark:text-white">Butuh pengajuan baru?</div>
            <p class="mt-1.5 text-[13px] text-mist dark:text-zinc-400">Hubungi tim layanan — pengajuan diterbitkan oleh staf setelah LC Anda terbit.</p>
        </section>
    </div>
</section>
