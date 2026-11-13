<?php

use App\Models\Notifikasi;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notifikasi')] class extends Component
{
    public string $filterStatus = '';

    #[Computed]
    public function notifikasis()
    {
        return auth()->user()->notifikasis()
            ->with('pengajuan')
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function jumlahBelumDibaca(): int
    {
        return auth()->user()->notifikasis()->whereNull('dibaca_at')->count();
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
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading level="1" size="xl">Notifikasi</flux:heading>
            <flux:subheading>
                Pemberitahuan email dan WhatsApp milik Anda beserta status pengirimannya.
                @if ($this->jumlahBelumDibaca > 0)
                    <flux:badge color="red">{{ $this->jumlahBelumDibaca }} belum dibaca</flux:badge>
                @endif
            </flux:subheading>
        </div>
        <div class="flex items-end gap-2">
            <flux:select wire:model.live="filterStatus" label="Status">
                <option value="">Semua Status</option>
                <option value="menunggu">Menunggu</option>
                <option value="terkirim">Terkirim</option>
                <option value="gagal">Gagal</option>
            </flux:select>
            @if ($this->jumlahBelumDibaca > 0)
                <flux:button wire:click="tandaiSemuaDibaca">Tandai Semua Dibaca</flux:button>
            @endif
        </div>
    </div>

    @if ($this->notifikasis->isEmpty())
        <flux:text class="mt-6 block">Belum ada notifikasi.</flux:text>
    @else
        <div class="mt-6 space-y-3">
            @foreach ($this->notifikasis as $notifikasi)
                <div class="rounded-xl border p-4 {{ $notifikasi->sudahDibaca() ? 'border-neutral-200 dark:border-neutral-700' : 'border-blue-300 bg-blue-50 dark:border-blue-800 dark:bg-blue-950' }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading level="3">{{ $notifikasi->judul }}</flux:heading>
                        <flux:badge size="sm">{{ $notifikasi->kanal->label() }}</flux:badge>
                        <flux:badge size="sm" :color="$notifikasi->status->color()">{{ $notifikasi->status->label() }}</flux:badge>
                        @unless ($notifikasi->sudahDibaca())
                            <flux:badge size="sm" color="red">Baru</flux:badge>
                        @endunless
                    </div>
                    <flux:text class="mt-2 block">{{ $notifikasi->isi }}</flux:text>
                    <flux:text size="sm" class="mt-2 block">
                        {{ $notifikasi->created_at->format('d M Y H:i') }}
                        · Percobaan: {{ $notifikasi->jumlah_percobaan }}
                        @if ($notifikasi->terkirim_at)
                            · Terkirim: {{ $notifikasi->terkirim_at->format('d M Y H:i') }}
                        @endif
                        @if ($notifikasi->pengajuan)
                            · <flux:link :href="route('pengajuans.show', $notifikasi->pengajuan)">{{ $notifikasi->pengajuan->nomor_pengajuan }}</flux:link>
                        @endif
                    </flux:text>
                    @if ($notifikasi->status->value === 'gagal' && $notifikasi->keterangan)
                        <flux:text size="sm" class="mt-1 block text-red-600 dark:text-red-400">Keterangan gagal: {{ $notifikasi->keterangan }}</flux:text>
                    @endif
                    @unless ($notifikasi->sudahDibaca())
                        <div class="mt-3">
                            <flux:button size="sm" wire:click="tandaiDibaca({{ $notifikasi->id }})">Tandai Dibaca</flux:button>
                        </div>
                    @endunless
                </div>
            @endforeach
        </div>
    @endif
</section>
