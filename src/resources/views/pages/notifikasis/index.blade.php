<?php

use App\Models\Notifikasi;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifikasi')] class extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function notifikasis()
    {
        return auth()->user()->notifikasis()
            ->with('pengajuan')
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(10);
    }

    #[Computed]
    public function jumlahBelumDibaca(): int
    {
        return auth()->user()->notifikasis()->whereNull('dibaca_at')->count();
    }

    /**
     * Hitungan notifikasi milik pengguna per status pengiriman,
     * untuk tile ringkasan di samping daftar.
     *
     * @return array{terkirim: int, menunggu: int, gagal: int}
     */
    #[Computed]
    public function ringkasanStatus(): array
    {
        $hitung = auth()->user()->notifikasis()
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return [
            'terkirim' => (int) ($hitung['terkirim'] ?? 0),
            'menunggu' => (int) ($hitung['menunggu'] ?? 0),
            'gagal' => (int) ($hitung['gagal'] ?? 0),
        ];
    }

    public function tandaiDibaca(Notifikasi $notifikasi): void
    {
        abort_unless($notifikasi->user_id === auth()->id(), 403);

        $notifikasi->update(['dibaca_at' => now()]);
    }

    public function tandaiSemuaDibaca(): void
    {
        auth()->user()->notifikasis()->whereNull('dibaca_at')->update(['dibaca_at' => now()]);

        Flux::toast(variant: 'success', text: 'Seluruh notifikasi ditandai sudah dibaca.');
    }
}; ?>
<section class="w-full">
    @php
        $pillStatusNotif = [
            'menunggu' => 'pill-amber',
            'terkirim' => 'pill-green',
            'gagal' => 'pill-red',
        ];
        $tintStatusNotif = [
            'menunggu' => 'bg-amber-50 text-amber-600 dark:bg-[#2e2008] dark:text-amber-400',
            'terkirim' => 'bg-emerald-50 text-emerald-600 dark:bg-[#0b2b21] dark:text-emerald-400',
            'gagal' => 'bg-red-50 text-red-600 dark:bg-[#331314] dark:text-red-400',
        ];
    @endphp

    {{-- Kepala halaman: judul + saringan + tandai semua (selaras mockup) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Notifikasi</h1>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-[13px] text-mist dark:text-zinc-400">
                Pemberitahuan email dan WhatsApp milik Anda beserta status pengirimannya.
                @if ($this->jumlahBelumDibaca > 0)
                    <span class="pill pill-red">{{ $this->jumlahBelumDibaca }} belum dibaca</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            <flux:select wire:model.live="filterStatus" label="Status">
                <option value="">Semua Status</option>
                <option value="menunggu">Menunggu</option>
                <option value="terkirim">Terkirim</option>
                <option value="gagal">Gagal</option>
            </flux:select>
            @if ($this->jumlahBelumDibaca > 0)
                <button type="button" class="btn" wire:click="tandaiSemuaDibaca">Tandai Semua Dibaca</button>
            @endif
        </div>
    </div>

    <div class="bento mt-5">
        <section class="tile md:col-span-2 xl:col-span-8">
            @if ($this->notifikasis->isEmpty())
                <p class="text-[13.5px] text-zinc-500 dark:text-zinc-400">Belum ada notifikasi.</p>
            @else
                @foreach ($this->notifikasis as $notifikasi)
                    <div class="flex items-start gap-3 border-b border-line py-3.5 last:border-b-0 dark:border-night-line {{ $notifikasi->sudahDibaca() ? '' : '-mx-4.25 bg-primary-tint/50 px-4.25 sm:-mx-5.5 sm:px-5.5 dark:bg-primary/10' }}" wire:key="notifikasi-{{ $notifikasi->id }}">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-[13px] {{ $tintStatusNotif[$notifikasi->status->value] ?? 'bg-soft text-zinc-500 dark:bg-night-soft dark:text-zinc-400' }}">
                            @if ($notifikasi->kanal->value === 'whatsapp')
                                <flux:icon.chat-bubble-left-ellipsis class="size-4.25" />
                            @else
                                <flux:icon.envelope class="size-4.25" />
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[13.5px] font-bold text-zinc-900 dark:text-white">{{ $notifikasi->judul }}</span>
                                <span class="pill pill-zinc">{{ $notifikasi->kanal->label() }}</span>
                                <span class="pill {{ $pillStatusNotif[$notifikasi->status->value] ?? 'pill-zinc' }}">{{ $notifikasi->status->label() }}</span>
                                @unless ($notifikasi->sudahDibaca())
                                    <span class="pill pill-red">Baru</span>
                                @endunless
                            </div>
                            <p class="mt-1 text-[13px] leading-normal text-zinc-600 dark:text-zinc-300">{{ $notifikasi->isi }}</p>
                            <p class="mt-1.5 text-xs leading-normal text-mist dark:text-zinc-400">
                                {{ $notifikasi->created_at->format('d M Y H:i') }}
                                · Percobaan: {{ $notifikasi->jumlah_percobaan }}
                                @if ($notifikasi->terkirim_at)
                                    · Terkirim: {{ $notifikasi->terkirim_at->format('d M Y H:i') }}
                                @endif
                                @if ($notifikasi->pengajuan)
                                    · <a href="{{ route('pengajuans.show', $notifikasi->pengajuan) }}" wire:navigate class="font-semibold text-primary hover:underline">{{ $notifikasi->pengajuan->nomor_pengajuan }}</a>
                                @endif
                            </p>
                            @if ($notifikasi->status->value === 'gagal' && $notifikasi->keterangan)
                                <p class="mt-1 text-xs leading-normal text-red-600 dark:text-red-400">Keterangan gagal: {{ $notifikasi->keterangan }}</p>
                            @endif
                            @unless ($notifikasi->sudahDibaca())
                                <div class="mt-2.5">
                                    <button type="button" class="btn btn-small" wire:click="tandaiDibaca({{ $notifikasi->id }})">Tandai Dibaca</button>
                                </div>
                            @endunless
                        </div>
                    </div>
                @endforeach
            @if ($this->notifikasis->total() > 0)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-mist dark:text-zinc-400">
                    <span>Menampilkan {{ $this->notifikasis->firstItem() }}–{{ $this->notifikasis->lastItem() }} dari {{ $this->notifikasis->total() }} notifikasi</span>
                    @if ($this->notifikasis->hasPages())
                        @php
                            $halamanIni = $this->notifikasis->currentPage();
                            $halamanAkhir = $this->notifikasis->lastPage();
                            $daftarHalaman = range(max(1, $halamanIni - 2), min($halamanAkhir, $halamanIni + 2));
                        @endphp
                        <div class="flex items-center gap-1.5">
                            <button type="button" wire:click="previousPage" class="chip {{ $this->notifikasis->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman sebelumnya">‹</button>
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
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Status Pengiriman</h2>
            </div>
            <div class="flex items-center gap-2.5 border-b border-dashed border-line py-2.25 text-[13px] dark:border-night-line">
                <span class="pill pill-green">Terkirim</span>
                <span class="text-zinc-600 dark:text-zinc-300">Notifikasi terkirim</span>
                <b class="ml-auto font-bold text-zinc-900 dark:text-white">{{ $this->ringkasanStatus['terkirim'] }}</b>
            </div>
            <div class="flex items-center gap-2.5 border-b border-dashed border-line py-2.25 text-[13px] dark:border-night-line">
                <span class="pill pill-amber">Menunggu</span>
                <span class="text-zinc-600 dark:text-zinc-300">Dalam antrean / dicoba lagi</span>
                <b class="ml-auto font-bold text-zinc-900 dark:text-white">{{ $this->ringkasanStatus['menunggu'] }}</b>
            </div>
            <div class="flex items-center gap-2.5 border-b border-dashed border-line py-2.25 text-[13px] dark:border-night-line">
                <span class="pill pill-red">Gagal</span>
                <span class="text-zinc-600 dark:text-zinc-300">Gagal setelah semua percobaan</span>
                <b class="ml-auto font-bold text-zinc-900 dark:text-white">{{ $this->ringkasanStatus['gagal'] }}</b>
            </div>
            <div class="flex items-center gap-2.5 py-2.25 text-[13px]">
                <span class="pill pill-red">Baru</span>
                <span class="text-zinc-600 dark:text-zinc-300">Belum dibaca</span>
                <b class="ml-auto font-bold text-zinc-900 dark:text-white">{{ $this->jumlahBelumDibaca }}</b>
            </div>
            <p class="mt-3 text-[12.5px] leading-[1.55] text-mist dark:text-zinc-400">Pengiriman gagal dicoba ulang otomatis dengan jeda bertingkat (1 mnt, 5 mnt, 30 mnt).</p>
        </section>
    </div>
</section>
