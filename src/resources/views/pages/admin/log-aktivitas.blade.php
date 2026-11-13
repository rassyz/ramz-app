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
    <flux:heading level="1" size="xl">Log Aktivitas</flux:heading>
    <flux:subheading>Catatan aktivitas bisnis seluruh pengguna: siapa melakukan apa, kapan, dan pada pengajuan mana.</flux:subheading>

    <div class="mt-6 flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="filterAktivitas" label="Aktivitas" class="max-w-72">
            <option value="">Semua Aktivitas</option>
            @foreach (LogAktivitas::labels() as $kunci => $label)
                <option value="{{ $kunci }}">{{ $label }}</option>
            @endforeach
        </flux:select>
        <flux:input wire:model.live="tanggalMulai" type="date" label="Dari Tanggal" />
        <flux:input wire:model.live="tanggalSelesai" type="date" label="Sampai Tanggal" />
        <flux:input wire:model.live.debounce.400ms="cariPengajuan" type="search" label="Nomor Pengajuan" placeholder="PGJ-..." />
    </div>

    @if ($this->logs->isEmpty())
        <flux:text class="mt-6 block">Tidak ada catatan aktivitas yang cocok dengan filter.</flux:text>
    @else
        <flux:table class="mt-6">
            <flux:table.columns>
                <flux:table.column>Waktu</flux:table.column>
                <flux:table.column>Pelaku</flux:table.column>
                <flux:table.column>Aktivitas</flux:table.column>
                <flux:table.column>Deskripsi</flux:table.column>
                <flux:table.column>Pengajuan</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->logs as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $log->created_at->format('d M Y H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $log->user?->name ?? 'Sistem' }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm">{{ $log->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell>
                            {{ $log->deskripsi }}
                            @if ($log->konteks)
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    @foreach ($log->konteks as $kunci => $nilai)
                                        <span>{{ $kunci }}: {{ is_scalar($nilai) ? $nilai : json_encode($nilai) }}</span>@unless ($loop->last) · @endunless
                                    @endforeach
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($log->pengajuan)
                                <flux:link :href="route('pengajuans.show', $log->pengajuan)">{{ $log->pengajuan->nomor_pengajuan }}</flux:link>
                            @else
                                -
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $this->logs->links() }}
        </div>
    @endif
</section>
