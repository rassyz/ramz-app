<?php

use App\Enums\StatusPengajuan;
use App\Models\Layanan;
use App\Models\Pengajuan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pantau Pengajuan')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $layananFilter = '';

    /**
     * Pantauan menyeluruh hanya untuk Admin dan Operator.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isOperator(), 403);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function daftarLayanan()
    {
        return Layanan::orderBy('nama')->get();
    }

    #[Computed]
    public function pengajuans()
    {
        return Pengajuan::query()
            ->with(['user', 'layanan', 'pembayaran'])
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('nomor_pengajuan', 'like', '%'.$this->search.'%')
                        ->orWhere('nomor_invoice', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->layananFilter, fn ($query) => $query->where('layanan_id', $this->layananFilter))
            ->latest()
            ->paginate(10);
    }
}; ?>

<section class="w-full">
    <flux:heading level="1" size="xl">Pantau Pengajuan</flux:heading>
    <flux:subheading>Seluruh pengajuan layanan dari nasabah beserta status dan pembayaran terhubungnya.</flux:subheading>

    <flux:separator class="mt-4 mb-6" />

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-center">
        <flux:input wire:model.live="search" type="search" placeholder="Cari nomor pengajuan, invoice, atau nasabah..." class="w-full sm:col-span-2 lg:col-span-1 lg:max-w-sm" />
        <flux:select wire:model.live="statusFilter" class="w-full lg:max-w-52">
            <option value="">Semua Status</option>
            @foreach (StatusPengajuan::cases() as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="layananFilter" class="w-full lg:max-w-64">
            <option value="">Semua Layanan</option>
            @foreach ($this->daftarLayanan as $layanan)
                <option value="{{ $layanan->id }}">{{ $layanan->nama }}</option>
            @endforeach
        </flux:select>
    </div>


    {{-- Daftar kartu (mobile) — Fase 8.2 --}}
    <div class="space-y-3 lg:hidden">
        @forelse ($this->pengajuans as $pengajuan)
            <div class="rounded-2xl bg-white p-4 shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-zinc-950/5 dark:bg-zinc-900" wire:key="kartu-pengajuan-{{ $pengajuan->id }}">
                <div class="flex items-start justify-between gap-3">
                    <span class="font-semibold">{{ $pengajuan->nomor_pengajuan }}</span>
                    <flux:badge :color="$pengajuan->status->color()">{{ $pengajuan->status->label() }}</flux:badge>
                </div>
                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Tanggal</dt><dd class="text-right font-medium">{{ $pengajuan->created_at->format('d M Y') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Nasabah</dt><dd class="text-right font-medium">{{ $pengajuan->user->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Layanan</dt><dd class="text-right font-medium">{{ $pengajuan->layanan->nama }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Invoice</dt><dd class="text-right font-medium">{{ $pengajuan->nomor_invoice }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Nominal</dt><dd class="text-right font-medium">{{ $pengajuan->pembayaran?->nominalFormatted() ?? '-' }}</dd></div>
                </dl>
                <flux:button :href="route('pengajuans.show', $pengajuan)" class="mt-4 w-full">Detail</flux:button>
            </div>
        @empty
            <flux:text>Belum ada pengajuan.</flux:text>
        @endforelse

        <div>{{ $this->pengajuans->links() }}</div>
    </div>

    <div class="hidden lg:block">
    <flux:table :paginate="$this->pengajuans">
        <flux:table.columns>
            <flux:table.column>Nomor Pengajuan</flux:table.column>
            <flux:table.column>Tanggal</flux:table.column>
            <flux:table.column>Nasabah</flux:table.column>
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
                    <flux:table.cell>{{ $pengajuan->user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $pengajuan->layanan->nama }}</flux:table.cell>
                    <flux:table.cell>{{ $pengajuan->nomor_invoice }}</flux:table.cell>
                    <flux:table.cell>{{ $pengajuan->pembayaran?->nominalFormatted() ?? '-' }}</flux:table.cell>
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
</section>
