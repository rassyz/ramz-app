<?php

use App\Models\AturanValidasi;
use App\Models\LogAktivitas;
use App\Services\LogAktivitasService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Aturan Validasi')] class extends Component
{
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
            ->orderBy('kategori')
            ->orderBy('kode')
            ->get();
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
    <flux:heading level="1" size="xl">Aturan Validasi</flux:heading>
    <flux:subheading>Aturan IF–THEN yang dibaca Rule-Based Evidence Checker dari basis data. Nonaktifkan aturan untuk menghentikan penilaiannya pada pemeriksaan berikutnya, tanpa mengubah kode.</flux:subheading>

    <flux:separator class="mt-4 mb-6" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Kode</flux:table.column>
            <flux:table.column>Nama Aturan</flux:table.column>
            <flux:table.column>Kategori</flux:table.column>
            <flux:table.column>Field</flux:table.column>
            <flux:table.column>Keterangan</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->aturan as $aturan)
                <flux:table.row :key="$aturan->id">
                    <flux:table.cell class="font-medium">{{ $aturan->kode }}</flux:table.cell>
                    <flux:table.cell>{{ $aturan->nama }}</flux:table.cell>
                    <flux:table.cell><flux:badge :color="$aturan->kategori->value === 'kelengkapan' ? 'blue' : 'violet'">{{ $aturan->kategori->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell>{{ $aturan->field ?? '-' }}</flux:table.cell>
                    <flux:table.cell>{{ $aturan->keterangan }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$aturan->is_active ? 'green' : 'zinc'">{{ $aturan->is_active ? 'Aktif' : 'Nonaktif' }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end">
                            <flux:button size="sm" wire:click="toggleAktif({{ $aturan->id }})">{{ $aturan->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</section>
