<?php

use App\Enums\MataUang;
use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Services\LogAktivitasService;
use App\Services\RuleBasedEvidenceChecker;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Pengajuan $pengajuan;

    public $uploadLc = null;

    public bool $modalKonfirmasi = false;

    // Data acuan LC (Fase 11) — diisi Operator dari dokumen LC.
    public string $acuanNomorInvoice = '';

    public string $acuanNomorLc = '';

    public string $acuanNamaApplicant = '';

    public string $acuanNomorPo = '';

    public string $acuanNominal = '';

    public string $acuanMataUang = '';

    public string $acuanToleransi = '';

    public string $acuanKuantitas = '';

    public string $acuanSatuan = '';

    public string $acuanTanggalTerbit = '';

    public string $acuanTanggalKedaluwarsa = '';

    public function mount(Pengajuan $pengajuan): void
    {
        $this->pengajuan = $pengajuan;
        $this->acuanNomorInvoice = $this->pengajuan->nomor_invoice ?? '';
        $this->acuanNomorLc = $this->pengajuan->nomor_lc ?? '';
        $this->acuanNamaApplicant = $this->pengajuan->nama_applicant ?? '';
        $this->acuanNomorPo = $this->pengajuan->nomor_po ?? '';
        $this->acuanNominal = $this->pengajuan->nominal_acuan !== null ? (string) $this->pengajuan->nominal_acuan : '';
        $this->acuanMataUang = $this->pengajuan->mata_uang_acuan?->value ?? '';
        $this->acuanToleransi = $this->pengajuan->toleransi_persen !== null ? (string) $this->pengajuan->toleransi_persen : '';
        $this->acuanKuantitas = $this->pengajuan->kuantitas_acuan !== null ? (string) $this->pengajuan->kuantitas_acuan : '';
        $this->acuanSatuan = $this->pengajuan->satuan_acuan ?? '';
        $this->acuanTanggalTerbit = $this->pengajuan->tanggal_terbit_lc?->toDateString() ?? '';
        $this->acuanTanggalKedaluwarsa = $this->pengajuan->tanggal_kedaluwarsa_lc?->toDateString() ?? '';
    }

    /**
     * Operator menyimpan data acuan LC dari dokumen LC yang terbit
     * (Fase 11): data inilah patokan checker v2. Setelah tersimpan,
     * pemeriksaan dijalankan ulang agar hasilnya memakai acuan terbaru.
     */
    public function simpanAcuan(): void
    {
        $this->authorize('updateAcuan', $this->pengajuan);

        $validated = $this->validate([
            'acuanNomorInvoice' => ['nullable', 'string', 'max:100'],
            'acuanNomorLc' => ['nullable', 'string', 'max:100'],
            'acuanNamaApplicant' => ['nullable', 'string', 'max:255'],
            'acuanNomorPo' => ['nullable', 'string', 'max:100'],
            'acuanNominal' => ['nullable', 'numeric', 'min:0'],
            'acuanMataUang' => ['nullable', Rule::enum(MataUang::class)],
            'acuanToleransi' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'acuanKuantitas' => ['nullable', 'numeric', 'min:0'],
            'acuanSatuan' => ['nullable', 'string', 'max:50'],
            'acuanTanggalTerbit' => ['nullable', 'date'],
            'acuanTanggalKedaluwarsa' => ['nullable', 'date'],
        ]);

        $kosongKeNull = fn ($nilai) => ($nilai === '' || $nilai === null) ? null : $nilai;

        $this->pengajuan->update([
            'nomor_invoice' => $kosongKeNull($validated['acuanNomorInvoice']),
            'nomor_lc' => $kosongKeNull($validated['acuanNomorLc']),
            'nama_applicant' => $kosongKeNull($validated['acuanNamaApplicant']),
            'nomor_po' => $kosongKeNull($validated['acuanNomorPo']),
            'nominal_acuan' => $kosongKeNull($validated['acuanNominal']),
            'mata_uang_acuan' => $kosongKeNull($validated['acuanMataUang']),
            'toleransi_persen' => $kosongKeNull($validated['acuanToleransi']),
            'kuantitas_acuan' => $kosongKeNull($validated['acuanKuantitas']),
            'satuan_acuan' => $kosongKeNull($validated['acuanSatuan']),
            'tanggal_terbit_lc' => $kosongKeNull($validated['acuanTanggalTerbit']),
            'tanggal_kedaluwarsa_lc' => $kosongKeNull($validated['acuanTanggalKedaluwarsa']),
        ]);

        app(RuleBasedEvidenceChecker::class)->periksa($this->pengajuan);

        app(LogAktivitasService::class)->catat(
            LogAktivitas::DATA_ACUAN_DIPERBARUI,
            auth()->user()->name." memperbarui data acuan LC pengajuan {$this->pengajuan->nomor_pengajuan}.",
            auth()->user(),
            $this->pengajuan,
            ['nomor_lc' => $this->pengajuan->nomor_lc]
        );

        Flux::toast(variant: 'success', text: 'Data acuan LC berhasil disimpan dan pemeriksaan dijalankan ulang.');
        $this->dispatch('pengajuan-diperbarui');
    }

    /**
     * Staf mengunggah/mengganti lampiran dokumen LC (Fase 11
     * Perbaikan 3). LC adalah acuan — berkasnya tidak diperiksa
     * checker dan tidak masuk daftar persyaratan.
     */
    public function unggahLc(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isOperator(), 403);

        $this->validate([
            'uploadLc' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'uploadLc.required' => 'Pilih berkas dokumen LC terlebih dahulu.',
            'uploadLc.mimes' => 'Berkas LC harus berformat PDF, JPG, atau PNG.',
            'uploadLc.max' => 'Ukuran berkas LC maksimal 5 MB.',
        ]);

        if ($this->pengajuan->path_file_lc) {
            Storage::disk('local')->delete($this->pengajuan->path_file_lc);
        }

        $path = $this->uploadLc->store('dokumen-lc', 'local');

        $this->pengajuan->update([
            'path_file_lc' => $path,
            'nama_file_lc' => $this->uploadLc->getClientOriginalName(),
        ]);

        $this->reset('uploadLc');
        $this->pengajuan->refresh();

        app(LogAktivitasService::class)->catat(
            LogAktivitas::DOKUMEN_LC_DIPERBARUI,
            'Dokumen LC pengajuan '.$this->pengajuan->nomor_pengajuan.' diperbarui oleh '.auth()->user()->name.'.',
            auth()->user(),
            $this->pengajuan
        );

        Flux::toast(variant: 'success', text: 'Dokumen LC berhasil dilampirkan.');
    }

    public function hapusLc(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isOperator(), 403);

        if ($this->pengajuan->path_file_lc) {
            Storage::disk('local')->delete($this->pengajuan->path_file_lc);
        }

        $this->pengajuan->update([
            'path_file_lc' => null,
            'nama_file_lc' => null,
        ]);

        $this->pengajuan->refresh();

        app(LogAktivitasService::class)->catat(
            LogAktivitas::DOKUMEN_LC_DIPERBARUI,
            'Dokumen LC pengajuan '.$this->pengajuan->nomor_pengajuan.' dihapus oleh '.auth()->user()->name.'.',
            auth()->user(),
            $this->pengajuan
        );

        Flux::toast(variant: 'success', text: 'Dokumen LC berhasil dihapus.');
    }

    public function unduhLc()
    {
        $this->authorize('view', $this->pengajuan);

        if (! $this->pengajuan->path_file_lc) {
            Flux::toast(variant: 'danger', text: 'Dokumen LC belum dilampirkan.');

            return null;
        }

        return response()->download(
            Storage::disk('local')->path($this->pengajuan->path_file_lc),
            $this->pengajuan->nama_file_lc
        );
    }

    /**
     * Modal konfirmasi Flux milik komponen ini untuk penghapusan
     * lampiran dokumen LC: method hapusLc() aslinya baru berjalan
     * setelah staf menyetujui pada modal.
     */
    public function mintaKonfirmasiHapusLc(): void
    {
        $this->modalKonfirmasi = true;
    }

    public function tutupKonfirmasi(): void
    {
        $this->modalKonfirmasi = false;
    }

    public function jalankanAksiKonfirmasi(): void
    {
        $this->tutupKonfirmasi();

        $this->hapusLc();
    }

    /**
     * @return array{judul: string, isi: string, tombol: string}|null
     */
    #[Computed]
    public function detailKonfirmasi(): ?array
    {
        if (! $this->pengajuan->path_file_lc) {
            return null;
        }

        return [
            'judul' => 'Hapus Lampiran Dokumen LC?',
            'isi' => 'Berkas LC "'.($this->pengajuan->nama_file_lc ?? '').'" akan dihapus dari pengajuan ini. Data LC yang sudah diisi tidak ikut terhapus.',
            'tombol' => 'Ya, Hapus Lampiran',
        ];
    }
}; ?>

            <div>
                <flux:heading level="2">Data LC</flux:heading>
                <flux:table class="mt-3">
                    <flux:table.rows>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Nomor LC</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->nomor_lc ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Applicant</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->nama_applicant ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Nomor PO</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->nomor_po ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Nominal Acuan</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->nominal_acuan !== null ? ($pengajuan->mata_uang_acuan?->value.' '.number_format((float) $pengajuan->nominal_acuan, 2, ',', '.')) : '-' }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Toleransi</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->toleransi_persen !== null ? $pengajuan->toleransi_persen.'%' : '-' }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Kuantitas Acuan</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->kuantitas_acuan !== null ? number_format((float) $pengajuan->kuantitas_acuan, 3, ',', '.').' '.($pengajuan->satuan_acuan ?? '') : '-' }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Tanggal Terbit LC</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->tanggal_terbit_lc?->format('d M Y') ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Tanggal Kedaluwarsa LC</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->tanggal_kedaluwarsa_lc?->format('d M Y') ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Dokumen LC</flux:table.cell>
                            <flux:table.cell>
                                @if ($pengajuan->path_file_lc)
                                    <span>{{ $pengajuan->nama_file_lc }}</span>
                                    <flux:button size="sm" class="ml-2" wire:click="unduhLc">Unduh</flux:button>
                                @else
                                    <span>Belum dilampirkan</span>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>

                @can('updateAcuan', $pengajuan)
                    <form wire:submit="simpanAcuan" class="mt-4 space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading level="3">Isi / Ubah Data LC</flux:heading>
                        <flux:text size="sm">Diisi dari dokumen LC yang terbit — data ini menjadi patokan pemeriksaan otomatis.</flux:text>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="acuanNomorInvoice" label="Nomor Invoice" type="text" />
                            <flux:input wire:model="acuanNomorLc" label="Nomor LC" type="text" />
                            <flux:input wire:model="acuanNamaApplicant" label="Nama Applicant" type="text" />
                            <flux:input wire:model="acuanNomorPo" label="Nomor PO" type="text" />
                            <flux:select wire:model="acuanMataUang" label="Mata Uang Acuan">
                                <option value="">— Pilih —</option>
                                @foreach (\App\Enums\MataUang::cases() as $mataUang)
                                    <option value="{{ $mataUang->value }}">{{ $mataUang->label() }}</option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="acuanNominal" label="Nominal Acuan" type="number" step="0.01" min="0" />
                            <flux:input wire:model="acuanToleransi" label="Toleransi (%)" type="number" step="0.01" min="0" max="100" />
                            <flux:input wire:model="acuanKuantitas" label="Kuantitas Acuan" type="number" step="0.001" min="0" />
                            <flux:input wire:model="acuanSatuan" label="Satuan Kuantitas" type="text" />
                            <flux:input wire:model="acuanTanggalTerbit" label="Tanggal Terbit LC" type="date" />
                            <flux:input wire:model="acuanTanggalKedaluwarsa" label="Tanggal Kedaluwarsa LC" type="date" />
                        </div>
                        <flux:button variant="primary" type="submit">Simpan Data LC</flux:button>
                    </form>

<livewire:pages::pengajuans.show-persyaratan :pengajuan="$pengajuan" />
                @endcan

                @if (auth()->user()->isAdmin() || auth()->user()->isOperator())
                    <form wire:submit="unggahLc" class="mt-4 space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading level="3">Lampiran Dokumen LC</flux:heading>
                        <flux:text size="sm">Berkas LC asli sebagai acuan — dapat diunduh nasabah. Berkas ini tidak diperiksa oleh checker. Format PDF, JPG, atau PNG, maksimal 5 MB.</flux:text>
                        <flux:input wire:model="uploadLc" type="file" />
                        @error('uploadLc') <flux:text size="sm" class="text-red-600 dark:text-red-400">{{ $message }}</flux:text> @enderror
                        <div class="flex flex-wrap gap-3">
                            <flux:button variant="primary" type="submit">{{ $pengajuan->path_file_lc ? 'Ganti Dokumen LC' : 'Lampirkan Dokumen LC' }}</flux:button>
                            @if ($pengajuan->path_file_lc)
                                <flux:button type="button" variant="danger" wire:click="mintaKonfirmasiHapusLc">Hapus Lampiran</flux:button>
                            @endif
                        </div>
                    </form>
                @endif

                @if ($this->detailKonfirmasi)
                    <flux:modal wire:model="modalKonfirmasi" class="md:w-md">
                        <div class="space-y-5">
                            <div class="flex items-start gap-3">
                                <div class="shrink-0 rounded-full bg-red-100 p-2 text-red-600 dark:bg-red-950 dark:text-red-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" />
                                    </svg>
                                </div>
                                <div>
                                    <flux:heading level="2">{{ $this->detailKonfirmasi['judul'] }}</flux:heading>
                                    <flux:text class="mt-2 block">{{ $this->detailKonfirmasi['isi'] }}</flux:text>
                                </div>
                            </div>
                            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                <flux:button wire:click="tutupKonfirmasi">Batal</flux:button>
                                <flux:button variant="danger" wire:click="jalankanAksiKonfirmasi">{{ $this->detailKonfirmasi['tombol'] }}</flux:button>
                            </div>
                        </div>
                    </flux:modal>
                @endif
            </div>
