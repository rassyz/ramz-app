<?php

use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Services\LogAktivitasService;
use App\Services\RuleBasedEvidenceChecker;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Pengajuan $pengajuan;

    public function mount(Pengajuan $pengajuan): void
    {
        $this->pengajuan = $pengajuan;
    }

    /**
     * Komponen ini menyegarkan diri setiap kali komponen lain pada
     * halaman detail mengubah data yang mempengaruhi hasil
     * pemeriksaan (dokumen diunggah/dihapus, Data LC disimpan, atau
     * persyaratan disimpan).
     */
    #[On('pengajuan-diperbarui')]
    public function segarkan(): void
    {
        // Render ulang saja; computed dihitung ulang pada request ini.
    }

    #[Computed]
    public function hasilTerakhir()
    {
        return $this->pengajuan->hasilPemeriksaanTerakhir()
            ->with('details')
            ->first();
    }

    #[Computed]
    public function riwayatPemeriksaan()
    {
        return $this->pengajuan->hasilPemeriksaans()->limit(5)->get();
    }

    /**
     * Pemeriksaan manual oleh staf (Operator/Admin) dari halaman detail.
     */
    public function jalankanPemeriksaan(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isOperator(), 403);

        $hasil = app(RuleBasedEvidenceChecker::class)->periksa($this->pengajuan);

        app(LogAktivitasService::class)->catat(
            LogAktivitas::PEMERIKSAAN_DIJALANKAN,
            auth()->user()->name." menjalankan pemeriksaan manual pada pengajuan {$this->pengajuan->nomor_pengajuan} dengan hasil {$hasil->status->label()}.",
            auth()->user(),
            $this->pengajuan,
            ['hasil' => $hasil->status->value, 'sesuai' => $hasil->total_sesuai, 'total' => $hasil->total_pemeriksaan]
        );

        Flux::toast(variant: 'success', text: 'Pemeriksaan selesai: '.$hasil->status->label().' ('.$hasil->total_sesuai.' dari '.$hasil->total_pemeriksaan.' pemeriksaan sesuai).');

        unset($this->hasilTerakhir, $this->riwayatPemeriksaan);
    }
}; ?>

<div>
    <flux:separator class="my-8" />

    <div>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading level="2">Hasil Pemeriksaan — Rule-Based Evidence Checker</flux:heading>
                <flux:subheading>Pemeriksaan kelengkapan dokumen dan pencocokan data terstruktur. Hasil ini bersifat pendukung; keputusan verifikasi akhir tetap pada Operator.</flux:subheading>
            </div>
            @if (auth()->user()->isAdmin() || auth()->user()->isOperator())
                <flux:button wire:click="jalankanPemeriksaan">Jalankan Pemeriksaan</flux:button>
            @endif
        </div>

        @if ($this->hasilTerakhir)
            @php $hasil = $this->hasilTerakhir; @endphp
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <flux:badge size="lg" :color="$hasil->status->color()">{{ $hasil->status->label() }}</flux:badge>
                <flux:text>{{ $hasil->total_sesuai }} sesuai, {{ $hasil->total_tidak_sesuai }} tidak sesuai, dari {{ $hasil->total_pemeriksaan }} pemeriksaan — {{ $hasil->created_at->format('d M Y H:i') }}</flux:text>
            </div>

            {{-- Detail pemeriksaan (mobile) — Fase 8.2 --}}
            <div class="mt-4 space-y-3 lg:hidden">
                @foreach ($hasil->details as $detail)
                    <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="detail-mobile-{{ $detail->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <span class="font-medium">{{ $detail->nama_pemeriksaan }}</span>
                            <flux:badge :color="$detail->hasil->color()">{{ $detail->hasil->label() }}</flux:badge>
                        </div>
                        <dl class="mt-2 space-y-1 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Nilai Pengajuan</dt><dd class="break-all text-right font-medium">{{ $detail->nilai_pengajuan ?? '-' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Nilai Dokumen</dt><dd class="break-all text-right font-medium">{{ $detail->nilai_dokumen ?? '-' }}</dd></div>
                        </dl>
                        @if ($detail->keterangan)
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $detail->keterangan }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-4 hidden lg:block">
            <flux:table class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Pemeriksaan</flux:table.column>
                    <flux:table.column>Nilai Pengajuan</flux:table.column>
                    <flux:table.column>Nilai Dokumen</flux:table.column>
                    <flux:table.column>Hasil</flux:table.column>
                    <flux:table.column>Keterangan</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($hasil->details as $detail)
                        <flux:table.row :key="$detail->id">
                            <flux:table.cell class="font-medium">{{ $detail->nama_pemeriksaan }}</flux:table.cell>
                            <flux:table.cell>{{ $detail->nilai_pengajuan ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $detail->nilai_dokumen ?? '-' }}</flux:table.cell>
                            <flux:table.cell><flux:badge :color="$detail->hasil->color()">{{ $detail->hasil->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ $detail->keterangan }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            </div>

            @if ($this->riwayatPemeriksaan->count() > 1)
                <flux:text class="mt-4 block font-medium">Riwayat pemeriksaan:</flux:text>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($this->riwayatPemeriksaan as $riwayatHasil)
                        <flux:badge :color="$riwayatHasil->status->color()">{{ $riwayatHasil->created_at->format('d M H:i') }} — {{ $riwayatHasil->status->label() }}</flux:badge>
                    @endforeach
                </div>
            @endif
        @else
            <flux:text class="mt-4 block">Belum ada pemeriksaan. Pemeriksaan berjalan otomatis setiap dokumen diunggah, diganti, atau dihapus.</flux:text>
        @endif
    </div>
</div>
