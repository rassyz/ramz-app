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
<section class="tile md:col-span-2 xl:col-span-8">
    <div class="flex flex-wrap items-center justify-between gap-2.5">
        <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Hasil Pemeriksaan</h2>
        @if (auth()->user()->isAdmin() || auth()->user()->isOperator())
            <button type="button" class="btn btn-primary btn-small" wire:click="jalankanPemeriksaan">Jalankan Pemeriksaan</button>
        @endif
    </div>
    <p class="mt-1.5 mb-4 text-[13px] text-mist dark:text-zinc-400">Pemeriksaan kelengkapan dokumen dan pencocokan data terstruktur oleh Rule-Based Evidence Checker. Hasil ini bersifat pendukung; keputusan verifikasi akhir tetap pada Operator.</p>

    @if ($this->hasilTerakhir)
        @php
            $hasil = $this->hasilTerakhir;
            $pillHasil = [
                'sesuai' => 'pill-green',
                'tidak_sesuai' => 'pill-red',
                'belum_dapat_diperiksa' => 'pill-zinc',
            ];
        @endphp
        <div class="mb-2 flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-3">
            <span class="pill {{ $pillHasil[$hasil->status->value] ?? 'pill-zinc' }}">{{ $hasil->status->label() }}</span>
            <span class="text-[13px] text-mist dark:text-zinc-400">{{ $hasil->total_sesuai }} sesuai, {{ $hasil->total_tidak_sesuai }} tidak sesuai, dari {{ $hasil->total_pemeriksaan }} pemeriksaan — {{ $hasil->created_at->format('d M Y H:i') }}</span>
        </div>

        @foreach ($hasil->details as $detail)
            <div class="flex flex-col gap-1.5 border-b border-dashed border-line py-2.75 last:border-b-0 sm:flex-row sm:gap-3 dark:border-night-line" wire:key="detail-{{ $detail->id }}">
                <span class="shrink-0 sm:w-40 sm:pt-0.5"><span class="pill {{ $pillHasil[$detail->hasil->value] ?? 'pill-zinc' }}">{{ $detail->hasil->label() }}</span></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[13.5px] leading-snug font-semibold text-zinc-900 dark:text-white">{{ $detail->nama_pemeriksaan }}</span>
                    @if ($detail->nilai_pengajuan !== null || $detail->nilai_dokumen !== null)
                        <span class="mt-0.5 block text-[12.5px] text-mist dark:text-zinc-400">Nilai pengajuan: {{ $detail->nilai_pengajuan ?? '-' }} • Nilai dokumen: {{ $detail->nilai_dokumen ?? '-' }}</span>
                    @endif
                    @if ($detail->keterangan)
                        <span class="mt-0.5 block text-[12.5px] leading-normal text-zinc-600 dark:text-zinc-300">{{ $detail->keterangan }}</span>
                    @endif
                </span>
            </div>
        @endforeach

        @if ($this->riwayatPemeriksaan->count() > 1)
            <p class="mt-4 text-[13px] font-medium text-zinc-700 dark:text-zinc-200">Riwayat pemeriksaan:</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($this->riwayatPemeriksaan as $riwayatHasil)
                    <span class="pill {{ $pillHasil[$riwayatHasil->status->value] ?? 'pill-zinc' }}">{{ $riwayatHasil->created_at->format('d M H:i') }} — {{ $riwayatHasil->status->label() }}</span>
                @endforeach
            </div>
        @endif
    @else
        <p class="text-[13.5px] text-zinc-500 dark:text-zinc-400">Belum ada pemeriksaan. Pemeriksaan berjalan otomatis setiap dokumen diunggah, diganti, atau dihapus.</p>
    @endif
</section>
