<?php

use App\Models\AturanValidasi;
use App\Models\LogAktivitas;
use App\Services\LogAktivitasService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Aturan Validasi')] class extends Component
{
    use WithPagination;

    /** Saringan kategori dari chip di kepala tile: '' | 'kelengkapan' | 'pencocokan'. */
    public string $filterKategori = '';

    public function updatedFilterKategori(): void
    {
        $this->resetPage();
    }
    /**
     * Halaman ini hanya untuk Admin (route juga dilindungi
     * middleware role:admin).
     */
    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    #[Computed]
    public function aturan()
    {
        return AturanValidasi::query()
            ->when($this->filterKategori, function ($query) {
                $query->where('kategori', $this->filterKategori);
            })
            ->orderBy('kategori')
            ->orderBy('kode')
            ->paginate(10);
    }

    public function toggleAktif(int $id): void
    {
        $aturan = AturanValidasi::findOrFail($id);
        $aturan->update(['is_active' => ! $aturan->is_active]);

        app(LogAktivitasService::class)->catat(
            LogAktivitas::ATURAN_VALIDASI_DIUBAH,
            'Admin '.auth()->user()->name." mengubah aturan {$aturan->kode} menjadi ".($aturan->is_active ? 'aktif' : 'nonaktif').'.',
            auth()->user(),
            null,
            ['kode_aturan' => $aturan->kode, 'is_active' => $aturan->is_active]
        );

        unset($this->aturan);

        Flux::toast(
            variant: 'success',
            text: $aturan->is_active
                ? 'Aturan '.$aturan->kode.' diaktifkan.'
                : 'Aturan '.$aturan->kode.' dinonaktifkan. Pemeriksaan berikutnya tidak akan menilainya.'
        );
    }
}; ?>
<section class="w-full">
    {{-- Baris judul + tombol ikon (selaras mockup) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Aturan Validasi</h1>
            <p class="mt-1 text-[13px] text-mist dark:text-zinc-400">Aturan IF–THEN yang dibaca Rule-Based Evidence Checker dari basis data. Nonaktifkan aturan untuk menghentikan penilaiannya pada pemeriksaan berikutnya, tanpa mengubah kode.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('notifikasis.index') }}" wire:navigate class="relative hidden size-10 items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Notifikasi" title="Notifikasi">
                <flux:icon name="bell" class="size-5" />
                @php
                    $belumDibacaAturan = auth()->user()->notifikasis()->whereNull('dibaca_at')->count();
                @endphp
                @if ($belumDibacaAturan > 0)
                    <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $belumDibacaAturan }}</span>
                @endif
            </a>
            <button type="button" x-data x-on:click="$flux.dark = !$flux.dark" class="hidden size-10 cursor-pointer items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Ganti tema gelap atau terang" title="Mode gelap / terang">
                <flux:icon.moon class="size-5 dark:hidden" />
                <flux:icon.sun class="hidden size-5 dark:block" />
            </button>
        </div>
    </div>

    <div class="bento mt-5">
        <section class="tile md:col-span-2 xl:col-span-8">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Daftar Aturan</h2>
                <div class="flex gap-1.5">
                    <button type="button" wire:click="$set('filterKategori', '')" class="chip {{ $filterKategori === '' ? 'chip-aktif' : '' }}">Semua</button>
                    <button type="button" wire:click="$set('filterKategori', 'kelengkapan')" class="chip {{ $filterKategori === 'kelengkapan' ? 'chip-aktif' : '' }}">Kelengkapan</button>
                    <button type="button" wire:click="$set('filterKategori', 'pencocokan')" class="chip {{ $filterKategori === 'pencocokan' ? 'chip-aktif' : '' }}">Pencocokan</button>
                </div>
            </div>
            <div class="-mx-4.25 overflow-x-auto px-4.25 sm:-mx-5.5 sm:px-5.5">
                <table class="tabel-kartu w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left dark:border-night-line">
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Kode Aturan</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Nama Aturan</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Kategori</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Jenis Dokumen</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Referensi</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Aktif</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->aturan as $aturan)
                            <tr class="border-b border-line last:border-b-0 dark:border-night-line" wire:key="aturan-{{ $aturan->id }}">
                                <td class="py-3.25 pr-3 font-mono text-[13px] font-bold whitespace-nowrap text-zinc-900 dark:text-white" data-label="Kode Aturan">{{ $aturan->kode }}</td>
                                <td class="py-3.25 pr-3" data-label="Nama Aturan">
                                    <span class="block text-[13.5px] font-semibold text-zinc-900 dark:text-white">{{ $aturan->nama }}</span>
                                    @if ($aturan->keterangan)
                                        <span class="mt-0.5 block text-[12.5px] leading-normal text-mist dark:text-zinc-400">{{ $aturan->keterangan }}</span>
                                    @endif
                                </td>
                                <td class="py-3.25 pr-3" data-label="Kategori"><span class="pill {{ $aturan->kategori->value === 'kelengkapan' ? 'pill-blue' : 'pill-indigo' }}">{{ $aturan->kategori->label() }}</span></td>
                                <td class="py-3.25 pr-3 text-[13.5px] whitespace-nowrap text-zinc-600 dark:text-zinc-300" data-label="Jenis Dokumen">{{ $aturan->jenis_dokumen ?? 'Umum' }}</td>
                                <td class="py-3.25 pr-3 text-[13.5px] text-zinc-600 dark:text-zinc-300" data-label="Referensi">
                                    {{ $aturan->referensi ?? '-' }}
                                    @if ($aturan->field)
                                        <span class="mt-0.5 block text-[12.5px] text-mist dark:text-zinc-400">Field: {{ $aturan->field }}</span>
                                    @endif
                                </td>
                                <td class="py-3.25 pr-3" data-label="Aktif"><span class="pill {{ $aturan->is_active ? 'pill-green' : 'pill-zinc' }}">{{ $aturan->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="aksi py-3.25" data-label="Aksi">
                                    <button type="button" class="btn btn-small" wire:click="toggleAktif({{ $aturan->id }})">{{ $aturan->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-6 text-center text-[13.5px] text-zinc-500 dark:text-zinc-400" colspan="7">Tidak ada aturan pada kategori ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($this->aturan->total() > 0)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-mist dark:text-zinc-400">
                    <span>Menampilkan {{ $this->aturan->firstItem() }}–{{ $this->aturan->lastItem() }} dari {{ $this->aturan->total() }} aturan</span>
                    @if ($this->aturan->hasPages())
                        @php
                            $halamanIni = $this->aturan->currentPage();
                            $halamanAkhir = $this->aturan->lastPage();
                            $daftarHalaman = range(max(1, $halamanIni - 2), min($halamanAkhir, $halamanIni + 2));
                        @endphp
                        <div class="flex items-center gap-1.5">
                            <button type="button" wire:click="previousPage" class="chip {{ $this->aturan->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman sebelumnya">‹</button>
                            @foreach ($daftarHalaman as $halaman)
                                <button type="button" wire:click="gotoPage({{ $halaman }})" class="chip {{ $halaman === $halamanIni ? 'chip-aktif' : '' }}">{{ $halaman }}</button>
                            @endforeach
                            <button type="button" wire:click="nextPage" class="chip {{ $halamanIni === $halamanAkhir ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman berikutnya">›</button>
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <section class="tile md:col-span-2 xl:col-span-4">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Cara Kerja</h2>
            </div>
            <p class="text-[13.5px] leading-[1.7] text-mist dark:text-zinc-400">Checker membandingkan <b class="font-semibold text-zinc-900 dark:text-zinc-100">data terstruktur</b> yang diketik saat unggah dokumen terhadap <b class="font-semibold text-zinc-900 dark:text-zinc-100">Data LC</b> pada pengajuan yang sama.</p>
            <p class="mt-3 text-[13.5px] leading-[1.7] text-mist dark:text-zinc-400">Aturan pencocokan hanya berjalan untuk jenis dokumen yang ada di <b class="font-semibold text-zinc-900 dark:text-zinc-100">snapshot persyaratan</b> pengajuan tersebut.</p>
            <p class="mt-3 text-[13.5px] leading-[1.7] text-mist dark:text-zinc-400">Checker <b class="font-semibold text-zinc-900 dark:text-zinc-100">tidak pernah mengubah status</b> pengajuan — keputusan akhir selalu pada Operator.</p>
            <div class="mt-4 rounded-[14px] bg-primary-tint px-3.25 py-2.75 text-[12.5px] leading-normal font-medium text-primary-strong dark:bg-primary/20 dark:text-indigo-200">Hasil yang mungkin: Sesuai, Tidak Sesuai, atau Belum Dapat Diperiksa (dokumen/data acuan belum ada).</div>
        </section>
    </div>
</section>
