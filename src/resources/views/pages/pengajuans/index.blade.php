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

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <flux:input wire:model.live="search" type="search" placeholder="Cari nomor pengajuan, invoice, atau nasabah..." class="max-w-sm" />
        <flux:select wire:model.live="statusFilter" class="max-w-52">
            <option value="">Semua Status</option>
            @foreach (StatusPengajuan::cases() as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="layananFilter" class="max-w-64">
            <option value="">Semua Layanan</option>
            @foreach ($this->daftarLayanan as $layanan)
                <option value="{{ $layanan->id }}">{{ $layanan->nama }}</option>
            @endforeach
        </flux:select>
    </div>

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
</section>
