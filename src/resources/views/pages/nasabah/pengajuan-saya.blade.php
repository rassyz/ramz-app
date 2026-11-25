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

    /**
     * Halaman ini hanya untuk Nasabah.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()->isNasabah(), 403);
    }

    #[Computed]
    public function pengajuans()
    {
        return auth()->user()->pengajuans()
            ->with(['layanan'])
            ->latest()
            ->paginate(10);
    }

}; ?>

<section class="w-full">
    <flux:heading level="1" size="xl">Pengajuan Saya</flux:heading>
    <flux:subheading>Pantau pengajuan layanan Anda di sini. Pengajuan baru dibuat oleh staf bank untuk Anda — silakan hubungi staf bila memerlukan pengajuan baru.</flux:subheading>

    <flux:separator class="mt-4 mb-6" />

    <div>
        <div>
            <flux:heading level="2">Daftar Pengajuan</flux:heading>


            {{-- Daftar kartu (mobile) — Fase 8.2: seluruh field terbaca tanpa geser menyamping --}}
            <div class="mt-4 space-y-3 lg:hidden">
                @forelse ($this->pengajuans as $pengajuan)
                    <div class="rounded-2xl bg-white p-4 shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-zinc-950/5 dark:bg-zinc-900" wire:key="kartu-pengajuan-{{ $pengajuan->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <span class="font-semibold">{{ $pengajuan->nomor_pengajuan }}</span>
                            <flux:badge :color="$pengajuan->status->color()">{{ $pengajuan->status->label() }}</flux:badge>
                        </div>
                        <dl class="mt-3 space-y-1.5 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Tanggal</dt><dd class="text-right font-medium">{{ $pengajuan->created_at->format('d M Y') }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Layanan</dt><dd class="text-right font-medium">{{ $pengajuan->layanan->nama }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Invoice</dt><dd class="text-right font-medium">{{ $pengajuan->nomor_invoice }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Nominal Acuan</dt><dd class="text-right font-medium">{{ $pengajuan->nominal_acuan !== null ? ($pengajuan->mata_uang_acuan?->value.' '.number_format((float) $pengajuan->nominal_acuan, 2, ',', '.')) : '-' }}</dd></div>
                        </dl>
                        <flux:button :href="route('pengajuans.show', $pengajuan)" class="mt-4 w-full">Detail</flux:button>
                    </div>
                @empty
                    <flux:text>Belum ada pengajuan.</flux:text>
                @endforelse

                <div>{{ $this->pengajuans->links() }}</div>
            </div>

            <div class="mt-4 hidden lg:block">
            <flux:table :paginate="$this->pengajuans" >
                <flux:table.columns>
                    <flux:table.column>Nomor Pengajuan</flux:table.column>
                    <flux:table.column>Tanggal</flux:table.column>
                    <flux:table.column>Layanan</flux:table.column>
                    <flux:table.column>Invoice</flux:table.column>
                    <flux:table.column>Nominal</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->pengajuans as $pengajuan)
                        <flux:table.row :key="$pengajuan->id">
                            <flux:table.cell class="font-medium">{{ $pengajuan->nomor_pengajuan }}</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->created_at->format('d M Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->layanan->nama }}</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->nomor_invoice }}</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->nominal_acuan !== null ? ($pengajuan->mata_uang_acuan?->value.' '.number_format((float) $pengajuan->nominal_acuan, 2, ',', '.')) : '-' }}</flux:table.cell>
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
            </div>
        </div>
    </div>
</section>
