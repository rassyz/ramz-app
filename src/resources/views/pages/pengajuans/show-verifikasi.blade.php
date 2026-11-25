<?php

use App\Enums\KeputusanVerifikasi;
use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Services\VerifikasiService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Pengajuan $pengajuan;

    // Form verifikasi & pengelolaan status (Fase 4, khusus operator)
    public string $keputusan = '';

    public string $catatanVerifikasi = '';

    public bool $modalKonfirmasi = false;

    public ?string $statusKonfirmasi = null;

    public function mount(Pengajuan $pengajuan): void
    {
        $this->pengajuan = $pengajuan;
    }

    #[Computed]
    public function hasilTerakhir()
    {
        return $this->pengajuan->hasilPemeriksaanTerakhir()
            ->with('details')
            ->first();
    }

    /**
     * Operator menyimpan keputusan verifikasi (Fase 4).
     */
    public function simpanKeputusan(): void
    {
        $this->authorize('verify', $this->pengajuan);

        $this->validate([
            'keputusan' => ['required', Rule::enum(KeputusanVerifikasi::class)],
            'catatanVerifikasi' => [
                Rule::requiredIf($this->keputusan === KeputusanVerifikasi::PerluPerbaikan->value),
                'nullable', 'string',
            ],
        ], [
            'catatanVerifikasi.required' => 'Catatan wajib diisi untuk keputusan Perlu Perbaikan.',
        ]);

        try {
            app(VerifikasiService::class)->putuskanVerifikasi(
                $this->pengajuan,
                auth()->user(),
                KeputusanVerifikasi::from($this->keputusan),
                $this->catatanVerifikasi !== '' ? $this->catatanVerifikasi : null
            );
        } catch (InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->pengajuan->refresh();
        $this->reset('keputusan', 'catatanVerifikasi');
        unset($this->hasilTerakhir);
        $this->dispatch('pengajuan-diperbarui');

        Flux::toast(variant: 'success', text: 'Keputusan verifikasi berhasil disimpan.');
    }

    /**
     * Operator memajukan status: terverifikasi -> diproses -> selesai.
     */
    public function majukanStatus(string $status): void
    {
        $this->authorize('updateStatus', $this->pengajuan);

        try {
            app(VerifikasiService::class)->ubahStatus(
                $this->pengajuan,
                auth()->user(),
                StatusPengajuan::from($status)
            );
        } catch (InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->pengajuan->refresh();
        $this->dispatch('pengajuan-diperbarui');

        Flux::toast(variant: 'success', text: 'Status pengajuan berhasil diperbarui.');
    }

    /**
     * Modal konfirmasi Flux milik komponen ini untuk pemajuan
     * status: method majukanStatus() aslinya baru berjalan setelah
     * operator menyetujui pada modal.
     */
    public function mintaKonfirmasiMajukan(string $status): void
    {
        $this->statusKonfirmasi = $status;
        $this->modalKonfirmasi = true;
    }

    public function tutupKonfirmasi(): void
    {
        $this->modalKonfirmasi = false;
        $this->statusKonfirmasi = null;
    }

    public function jalankanAksiKonfirmasi(): void
    {
        $status = $this->statusKonfirmasi;

        $this->tutupKonfirmasi();

        if ($status !== null) {
            $this->majukanStatus($status);
        }
    }

    /**
     * @return array{judul: string, isi: string, tombol: string}|null
     */
    #[Computed]
    public function detailKonfirmasi(): ?array
    {
        return match ($this->statusKonfirmasi) {
            'diproses' => [
                'judul' => 'Mulai Memproses Pengajuan Ini?',
                'isi' => 'Status pengajuan akan berubah menjadi Diproses.',
                'tombol' => 'Ya, Mulai Diproses',
            ],
            'selesai' => [
                'judul' => 'Tandai Pengajuan Ini Selesai?',
                'isi' => 'Status pengajuan akan berubah menjadi Selesai dan pengajuan tidak dapat diubah lagi.',
                'tombol' => 'Ya, Tandai Selesai',
            ],
            default => null,
        };
    }
}; ?>
<section class="tile md:col-span-2 xl:col-span-4">
    @if (auth()->user()->isOperator())
        <div class="mb-4 flex items-center justify-between gap-2.5">
            <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Verifikasi &amp; Pengelolaan — Operator</h2>
        </div>
        <p class="-mt-2.5 mb-4 text-[13px] text-mist dark:text-zinc-400">Keputusan dan pengelolaan status hanya dapat dilakukan oleh operator.</p>

        @php
            $pillHasilVerifikasi = [
                'sesuai' => 'pill-green',
                'tidak_sesuai' => 'pill-red',
                'belum_dapat_diperiksa' => 'pill-zinc',
            ];
        @endphp

        @can('verify', $pengajuan)
            <form wire:submit="simpanKeputusan" class="space-y-4">
                <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Keputusan Verifikasi</h3>

                @if ($this->hasilTerakhir)
                    <p class="text-[13px] leading-normal text-zinc-600 dark:text-zinc-300">Acuan hasil pemeriksaan terakhir: <span class="pill {{ $pillHasilVerifikasi[$this->hasilTerakhir->status->value] ?? 'pill-zinc' }}">{{ $this->hasilTerakhir->status->label() }}</span> ({{ $this->hasilTerakhir->created_at->format('d M Y H:i') }})</p>
                @else
                    <p class="text-[13px] leading-normal text-zinc-600 dark:text-zinc-300">Perhatian: checker belum pernah berjalan untuk pengajuan ini. Keputusan tetap dapat diambil berdasarkan pemeriksaan dokumen secara langsung.</p>
                @endif

                <flux:radio.group wire:model="keputusan" label="Keputusan" required>
                    <flux:radio value="terverifikasi" label="Terverifikasi" description="Dokumen sesuai ketentuan LC" />
                    <flux:radio value="perlu_perbaikan" label="Perlu Perbaikan" description="Nasabah diminta memperbaiki dokumen" />
                </flux:radio.group>

                <flux:textarea wire:model="catatanVerifikasi" label="Catatan (wajib untuk Perlu Perbaikan)" rows="3" />
                @error('catatanVerifikasi')
                    <flux:text class="text-red-500">{{ $message }}</flux:text>
                @enderror

                <flux:button variant="primary" type="submit" class="w-full">Simpan Keputusan</flux:button>
            </form>
        @endcan

        @can('updateStatus', $pengajuan)
            @if ($pengajuan->status === \App\Enums\StatusPengajuan::Terverifikasi)
                <div class="mt-4 rounded-[14px] bg-soft p-3.25 dark:bg-night-soft">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Kelola Status</h3>
                    <p class="mt-1.5 text-[13px] leading-normal text-zinc-600 dark:text-zinc-300">Pengajuan sudah terverifikasi dan dapat mulai diproses.</p>
                    <flux:button variant="primary" class="mt-3 w-full" wire:click="mintaKonfirmasiMajukan('diproses')">Mulai Diproses</flux:button>
                </div>
            @elseif ($pengajuan->status === \App\Enums\StatusPengajuan::Diproses)
                <div class="mt-4 rounded-[14px] bg-soft p-3.25 dark:bg-night-soft">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Kelola Status</h3>
                    <p class="mt-1.5 text-[13px] leading-normal text-zinc-600 dark:text-zinc-300">Pengajuan sedang diproses dan dapat ditandai selesai.</p>
                    <flux:button variant="primary" class="mt-3 w-full" wire:click="mintaKonfirmasiMajukan('selesai')">Tandai Selesai</flux:button>
                </div>
            @endif
        @endcan
    @endif

    @if ($this->detailKonfirmasi)
        <flux:modal wire:model="modalKonfirmasi" class="md:w-md">
            <div class="space-y-5">
                <div>
                    <flux:heading level="2">{{ $this->detailKonfirmasi['judul'] }}</flux:heading>
                    <flux:text class="mt-2 block">{{ $this->detailKonfirmasi['isi'] }}</flux:text>
                </div>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <flux:button wire:click="tutupKonfirmasi">Batal</flux:button>
                    <flux:button variant="primary" wire:click="jalankanAksiKonfirmasi">{{ $this->detailKonfirmasi['tombol'] }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</section>
