<?php

use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail Pengajuan')] class extends Component
{
    public Pengajuan $pengajuan;

    /**
     * Akses detail ditegakkan PengajuanPolicy: staf dapat melihat
     * semua pengajuan, nasabah hanya miliknya sendiri.
     */
    public function mount(Pengajuan $pengajuan): void
    {
        $this->authorize('view', $pengajuan);
        $this->pengajuan = $pengajuan->load(['user', 'layanan', 'pembayaran']);
    }

    #[Computed]
    public function riwayat()
    {
        return $this->pengajuan->riwayatStatuses()->with('diubahOleh')->get();
    }

    public function batalkan(): void
    {
        $this->authorize('cancel', $this->pengajuan);

        DB::transaction(function () {
            $statusLama = $this->pengajuan->status;

            $this->pengajuan->update([
                'status' => StatusPengajuan::Dibatalkan,
                'catatan' => null,
            ]);

            $this->pengajuan->riwayatStatuses()->create([
                'status_dari' => $statusLama,
                'status_ke' => StatusPengajuan::Dibatalkan,
                'catatan' => 'Dibatalkan oleh nasabah.',
                'changed_by' => auth()->id(),
            ]);
        });

        $this->pengajuan->refresh();
        unset($this->riwayat);

        Flux::toast(variant: 'success', text: 'Pengajuan berhasil dibatalkan.');
    }
}; ?>

<section class="w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading level="1" size="xl">{{ $pengajuan->nomor_pengajuan }}</flux:heading>
            <flux:subheading>
                Diajukan {{ $pengajuan->created_at->format('d M Y H:i') }} oleh {{ $pengajuan->user->name }}
            </flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge size="lg" :color="$pengajuan->status->color()">{{ $pengajuan->status->label() }}</flux:badge>
            @can('cancel', $pengajuan)
                <flux:button variant="danger" wire:click="batalkan" wire:confirm="Batalkan pengajuan ini?">Batalkan Pengajuan</flux:button>
            @endcan
        </div>
    </div>

    <flux:button size="sm" class="mt-4" :href="auth()->user()->isNasabah() ? route('nasabah.pengajuan') : route('pengajuans.index')">&larr; Kembali</flux:button>

    <flux:separator class="mt-4 mb-6" />

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <div>
                <flux:heading level="2">Data Pengajuan</flux:heading>
                <flux:table class="mt-3">
                    <flux:table.rows>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Nasabah</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->user->name }} ({{ $pengajuan->user->email }})</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Jenis Layanan</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->layanan->nama }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Nomor Invoice</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->nomor_invoice }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Status</flux:table.cell>
                            <flux:table.cell><flux:badge :color="$pengajuan->status->color()">{{ $pengajuan->status->label() }}</flux:badge></flux:table.cell>
                        </flux:table.row>
                        @if ($pengajuan->catatan)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">Catatan</flux:table.cell>
                                <flux:table.cell>{{ $pengajuan->catatan }}</flux:table.cell>
                            </flux:table.row>
                        @endif
                    </flux:table.rows>
                </flux:table>
            </div>

            <div>
                <flux:heading level="2">Pembayaran Terhubung</flux:heading>
                @if ($pengajuan->pembayaran)
                    <flux:table class="mt-3">
                        <flux:table.rows>
                            <flux:table.row>
                                <flux:table.cell class="font-medium">Nominal</flux:table.cell>
                                <flux:table.cell>{{ $pengajuan->pembayaran->nominalFormatted() }}</flux:table.cell>
                            </flux:table.row>
                            <flux:table.row>
                                <flux:table.cell class="font-medium">Mata Uang</flux:table.cell>
                                <flux:table.cell>{{ $pengajuan->pembayaran->mata_uang->label() }}</flux:table.cell>
                            </flux:table.row>
                            <flux:table.row>
                                <flux:table.cell class="font-medium">Tanggal Pembayaran</flux:table.cell>
                                <flux:table.cell>{{ $pengajuan->pembayaran->tanggal_pembayaran?->format('d M Y') ?? '-' }}</flux:table.cell>
                            </flux:table.row>
                            <flux:table.row>
                                <flux:table.cell class="font-medium">Status Pembayaran</flux:table.cell>
                                <flux:table.cell><flux:badge :color="$pengajuan->pembayaran->status->color()">{{ $pengajuan->pembayaran->status->label() }}</flux:badge></flux:table.cell>
                            </flux:table.row>
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:text class="mt-3 block">Belum ada data pembayaran terhubung.</flux:text>
                @endif
            </div>

            <div>
                <flux:heading level="2">Persyaratan Dokumen Layanan</flux:heading>
                <ul class="mt-3 list-inside list-disc space-y-1">
                    @forelse ($pengajuan->layanan->persyaratan_dokumen ?? [] as $syarat)
                        <li><flux:text size="sm">{{ $syarat['nama'] }}@if (!$syarat['wajib']) (opsional)@endif</flux:text></li>
                    @empty
                        <li><flux:text size="sm">Belum ada persyaratan dokumen untuk layanan ini.</flux:text></li>
                    @endforelse
                </ul>
                <flux:text size="sm" class="mt-2 block">Unggah dokumen dan hasil pemeriksaan kelengkapan hadir pada fase dokumen dan checker.</flux:text>
            </div>
        </div>

        <div>
            <flux:heading level="2">Riwayat Status</flux:heading>
            <div class="mt-4 space-y-4">
                @forelse ($this->riwayat as $riwayat)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($riwayat->status_dari)
                                <flux:badge :color="$riwayat->status_dari->color()">{{ $riwayat->status_dari->label() }}</flux:badge>
                                <span>&rarr;</span>
                            @endif
                            <flux:badge :color="$riwayat->status_ke->color()">{{ $riwayat->status_ke->label() }}</flux:badge>
                        </div>
                        <flux:text size="sm" class="mt-2 block">
                            {{ $riwayat->created_at->format('d M Y H:i') }} — oleh {{ $riwayat->diubahOleh?->name ?? 'Sistem' }}
                        </flux:text>
                        @if ($riwayat->catatan)
                            <flux:text size="sm" class="mt-1 block">{{ $riwayat->catatan }}</flux:text>
                        @endif
                    </div>
                @empty
                    <flux:text>Belum ada riwayat status.</flux:text>
                @endforelse
            </div>
        </div>
    </div>
</section>
