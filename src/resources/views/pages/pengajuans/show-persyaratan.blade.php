<?php

use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Services\LogAktivitasService;
use App\Services\RuleBasedEvidenceChecker;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public Pengajuan $pengajuan;

    /** @var list<array{nama: string, wajib: bool}> */
    public array $syaratBaris = [];

    public function mount(Pengajuan $pengajuan): void
    {
        $this->pengajuan = $pengajuan;
        $this->syaratBaris = collect($this->pengajuan->persyaratanBerlaku())
            ->map(fn ($syarat) => ['nama' => $syarat['nama'], 'wajib' => (bool) ($syarat['wajib'] ?? false)])
            ->all();
    }

    /**
     * Menambah satu baris kosong pada editor persyaratan dokumen.
     */
    public function tambahBarisSyarat(): void
    {
        $this->authorize('updateAcuan', $this->pengajuan);
        $this->syaratBaris[] = ['nama' => '', 'wajib' => true];
    }

    /**
     * Menghapus satu baris pada editor persyaratan dokumen.
     */
    public function hapusBarisSyarat(int $indeks): void
    {
        $this->authorize('updateAcuan', $this->pengajuan);
        unset($this->syaratBaris[$indeks]);
        $this->syaratBaris = array_values($this->syaratBaris);
    }

    /**
     * Operator menyimpan snapshot persyaratan dokumen pengajuan ini
     * (Fase 11 — wujud klausul dokumen pada LC, yang isinya memang
     * dapat berbeda dari templat layanan). Pemeriksaan dijalankan
     * ulang terhadap snapshot baru.
     */
    public function simpanPersyaratan(): void
    {
        $this->authorize('updateAcuan', $this->pengajuan);

        $validated = $this->validate([
            'syaratBaris' => ['array'],
            'syaratBaris.*.nama' => ['required', 'string', 'max:100'],
            'syaratBaris.*.wajib' => ['boolean'],
        ], [
            'syaratBaris.*.nama.required' => 'Nama dokumen pada setiap baris persyaratan wajib diisi.',
        ]);

        $this->pengajuan->update([
            'persyaratan_dokumen' => collect($validated['syaratBaris'])
                ->map(fn ($baris) => ['nama' => trim($baris['nama']), 'wajib' => (bool) ($baris['wajib'] ?? false)])
                ->all(),
        ]);

        app(RuleBasedEvidenceChecker::class)->periksa($this->pengajuan);

        app(LogAktivitasService::class)->catat(
            LogAktivitas::PERSYARATAN_DIPERBARUI,
            auth()->user()->name." memperbarui persyaratan dokumen pengajuan {$this->pengajuan->nomor_pengajuan}.",
            auth()->user(),
            $this->pengajuan,
            []
        );

        Flux::toast(variant: 'success', text: 'Persyaratan dokumen berhasil disimpan dan pemeriksaan dijalankan ulang.');
        $this->dispatch('pengajuan-diperbarui');
    }
}; ?>

                    <form wire:submit="simpanPersyaratan" class="mt-4 space-y-4 rounded-2xl border border-line p-4 dark:border-night-line">
                        <flux:heading level="3">Persyaratan Dokumen Pengajuan Ini</flux:heading>
                        <flux:text size="sm">Snapshot dari templat layanan saat pengajuan dibuat — sesuaikan dengan klausul dokumen pada LC pengajuan ini bila berbeda.</flux:text>
                        @foreach ($syaratBaris as $indeks => $baris)
                            <div class="flex items-end gap-3">
                                <flux:input wire:model="syaratBaris.{{ $indeks }}.nama" label="{{ $indeks === 0 ? 'Nama Dokumen' : '' }}" type="text" class="flex-1" />
                                <label class="flex h-10 cursor-pointer items-center gap-2">
                                    <flux:checkbox wire:model="syaratBaris.{{ $indeks }}.wajib" />
                                    <span class="text-sm">Wajib</span>
                                </label>
                                <flux:button type="button" variant="danger" wire:click="hapusBarisSyarat({{ $indeks }})">Hapus</flux:button>
                            </div>
                        @endforeach
                        <div class="flex flex-wrap gap-3">
                            <flux:button type="button" wire:click="tambahBarisSyarat">Tambah Dokumen</flux:button>
                            <flux:button variant="primary" type="submit">Simpan Persyaratan</flux:button>
                        </div>
                    </form>
