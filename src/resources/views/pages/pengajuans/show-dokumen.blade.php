<?php

use App\Enums\MataUang;
use App\Models\Dokumen;
use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Services\LogAktivitasService;
use App\Services\RuleBasedEvidenceChecker;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Pengajuan $pengajuan;

    public bool $modalKonfirmasi = false;

    public ?int $dokumenKonfirmasiId = null;

    // Form unggah / ganti dokumen
    public string $uploadJenis = '';

    public $uploadFile = null;

    public string $docNomorInvoice = '';

    public string $docIdentitas = '';

    public string $docNominal = '';

    public string $docMataUang = 'USD';

    public string $docJenisLayanan = '';

    public string $docNomorDokumen = '';

    public string $docTanggalDokumen = '';

    public string $docNomorLc = '';

    public string $docNomorPo = '';

    public string $docKuantitas = '';

    public string $docSatuan = '';

    public string $docIdentitasPengirim = '';

    public function mount(Pengajuan $pengajuan): void
    {
        $this->pengajuan = $pengajuan;
    }

    /**
     * Menyegarkan salinan pengajuan milik komponen ini setiap kali
     * komponen lain mengubah data bersama (mis. snapshot
     * persyaratan disimpan oleh komponen persyaratan), supaya
     * daftar periksa dan pilihan jenis dokumen membaca keadaan
     * terbaru.
     */
    #[On('pengajuan-diperbarui')]
    public function segarkan(): void
    {
        $this->pengajuan->refresh();
    }

    #[Computed]
    public function dokumens()
    {
        return $this->pengajuan->dokumens()->get();
    }

    /**
     * Penanda visual: snapshot persyaratan pengajuan ini menyimpang
     * dari templat layanannya (disesuaikan dengan klausul LC).
     */
    #[Computed]
    public function persyaratanDisesuaikan(): bool
    {
        if ($this->pengajuan->persyaratan_dokumen === null) {
            return false;
        }

        $normalisasi = fn ($daftar) => collect($daftar ?? [])
            ->map(fn ($syarat) => [strtolower(trim($syarat['nama'] ?? '')), (bool) ($syarat['wajib'] ?? false)])
            ->sortBy(0)
            ->values()
            ->all();

        return $normalisasi($this->pengajuan->persyaratan_dokumen)
            !== $normalisasi($this->pengajuan->layanan?->persyaratan_dokumen);
    }

    /**
     * Nama jenis dokumen dari snapshot persyaratan pengajuan ini.
     *
     * @return list<string>
     */
    #[Computed]
    public function daftarJenisDokumen(): array
    {
        return collect($this->pengajuan->persyaratanBerlaku())
            ->pluck('nama')
            ->all();
    }

    #[Computed]
    public function dapatKelolaDokumen(): bool
    {
        return auth()->user()->can('create', [Dokumen::class, $this->pengajuan]);
    }

    #[Computed]
    public function jenisTerpilihInvoice(): bool
    {
        return $this->uploadJenis !== ''
            && str_contains(strtolower($this->uploadJenis), 'invoice');
    }

    /**
     * Kunci jenis dokumen terpilih yang dinormalisasi (huruf kecil),
     * untuk menentukan kelompok field terstruktur yang diminta.
     */
    #[Computed]
    public function jenisTerpilihKunci(): string
    {
        return strtolower(trim($this->uploadJenis));
    }

    /**
     * Saat jenis dokumen dipilih, isi awal form data dokumen diambil
     * dari dokumen yang sudah ada (untuk penggantian) agar mudah diedit.
     */
    public function updatedUploadJenis(): void
    {
        $existing = $this->dokumens->firstWhere('jenis_dokumen', $this->uploadJenis);

        $this->docNomorInvoice = $existing?->doc_nomor_invoice ?? '';
        $this->docIdentitas = $existing?->doc_identitas_nasabah ?? auth()->user()->name;
        $this->docNominal = $existing?->doc_nominal ?? '';
        $this->docMataUang = $existing?->doc_mata_uang?->value ?? 'USD';
        $this->docJenisLayanan = $existing?->doc_jenis_layanan ?? $this->pengajuan->layanan->kode;
        $this->docNomorDokumen = $existing?->doc_nomor_dokumen ?? '';
        $this->docTanggalDokumen = $existing?->doc_tanggal_dokumen?->toDateString() ?? '';
        $this->docNomorLc = $existing?->doc_nomor_lc ?? '';
        $this->docNomorPo = $existing?->doc_nomor_po ?? '';
        $this->docKuantitas = $existing?->doc_kuantitas ?? '';
        $this->docSatuan = $existing?->doc_satuan ?? '';
        $this->docIdentitasPengirim = $existing?->doc_identitas_pengirim ?? '';
    }

    public function unggahDokumen(): void
    {
        $this->authorize('create', [Dokumen::class, $this->pengajuan]);

        $jenis = $this->jenisTerpilihKunci;
        $isInvoice = str_contains($jenis, 'invoice');
        $isDraft = str_contains($jenis, 'draft');
        $isSuratJalan = str_contains($jenis, 'surat jalan');
        $isPackingList = str_contains($jenis, 'packing list');
        $isBillOfLading = str_contains($jenis, 'bill of lading');
        $isAsuransi = str_contains($jenis, 'asuransi');
        $isFakturPajak = str_contains($jenis, 'faktur pajak');
        $isWajib = collect($this->pengajuan->persyaratanBerlaku())
            ->contains(fn ($syarat) => strtolower(trim($syarat['nama'])) === $jenis && ($syarat['wajib'] ?? false));

        $rules = [
            'uploadJenis' => ['required', 'string', Rule::in($this->daftarJenisDokumen)],
            'uploadFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        // Fase 11: setiap dokumen wajib membawa nomor & tanggal dokumen.
        if ($isWajib) {
            $rules += [
                'docNomorDokumen' => ['required', 'string', 'max:100'],
                'docTanggalDokumen' => ['required', 'date'],
            ];
        }

        if ($isInvoice) {
            $rules += [
                'docNomorInvoice' => ['required', 'string', 'max:100'],
                'docIdentitas' => ['required', 'string', 'max:255'],
                'docNominal' => ['required', 'numeric', 'min:0'],
                'docMataUang' => ['required', Rule::enum(MataUang::class)],
                'docNomorLc' => ['required', 'string', 'max:100'],
                'docNomorPo' => ['required', 'string', 'max:100'],
            ];
        }

        if ($isDraft) {
            $rules += [
                'docNominal' => ['required', 'numeric', 'min:0'],
                'docMataUang' => ['required', Rule::enum(MataUang::class)],
            ];
        }

        if ($isAsuransi) {
            $rules += [
                'docNominal' => ['required', 'numeric', 'min:0'],
            ];
        }

        if ($isSuratJalan) {
            $rules += [
                'docNomorLc' => ['required', 'string', 'max:100'],
                'docNomorPo' => ['required', 'string', 'max:100'],
            ];
        }

        if ($isPackingList) {
            $rules += [
                'docKuantitas' => ['required', 'numeric', 'min:0'],
                'docSatuan' => ['required', 'string', 'max:50'],
            ];
        }

        if ($isBillOfLading) {
            $rules += [
                'docIdentitasPengirim' => ['required', 'string', 'max:255'],
            ];
        }

        if ($isFakturPajak) {
            $rules += [
                'docNomorLc' => ['required', 'string', 'max:100'],
                'docNomorInvoice' => ['required', 'string', 'max:100'],
                'docNominal' => ['required', 'numeric', 'min:0'],
                'docMataUang' => ['required', Rule::enum(MataUang::class)],
                'docIdentitas' => ['required', 'string', 'max:255'],
            ];
        }

        $this->validate($rules, [
            'uploadFile.required' => 'Pilih berkas dokumen terlebih dahulu.',
            'uploadFile.mimes' => 'Berkas harus berformat PDF, JPG, JPEG, atau PNG.',
            'uploadFile.max' => 'Ukuran berkas maksimal 5 MB.',
            'docNomorInvoice.required' => 'Nomor invoice pada dokumen wajib diisi.',
            'docIdentitas.required' => 'Identitas nasabah pada dokumen wajib diisi.',
            'docNominal.required' => 'Nominal pada dokumen wajib diisi.',
            'docNomorDokumen.required' => 'Nomor dokumen wajib diisi.',
            'docTanggalDokumen.required' => 'Tanggal dokumen wajib diisi.',
            'docNomorLc.required' => 'Nomor LC pada dokumen wajib diisi.',
            'docNomorPo.required' => 'Nomor PO pada dokumen wajib diisi.',
            'docKuantitas.required' => 'Kuantitas pada dokumen wajib diisi.',
            'docSatuan.required' => 'Satuan kuantitas wajib diisi.',
            'docIdentitasPengirim.required' => 'Identitas pengirim pada dokumen wajib diisi.',
        ]);

        $existing = $this->pengajuan->dokumens()
            ->where('jenis_dokumen', $this->uploadJenis)
            ->first();

        $pathBaru = $this->uploadFile->store('dokumen/'.$this->pengajuan->id, 'local');

        Dokumen::updateOrCreate(
            ['pengajuan_id' => $this->pengajuan->id, 'jenis_dokumen' => $this->uploadJenis],
            [
                'nama_file_asli' => $this->uploadFile->getClientOriginalName(),
                'path_file' => $pathBaru,
                'mime' => $this->uploadFile->getMimeType(),
                'ukuran_file' => $this->uploadFile->getSize(),
                'diupload_oleh' => auth()->id(),
                'doc_nomor_dokumen' => $isWajib ? $this->docNomorDokumen : null,
                'doc_tanggal_dokumen' => $isWajib ? $this->docTanggalDokumen : null,
                'doc_nomor_invoice' => ($isInvoice || $isFakturPajak) ? $this->docNomorInvoice : null,
                'doc_identitas_nasabah' => ($isInvoice || $isFakturPajak) ? $this->docIdentitas : null,
                'doc_nominal' => ($isInvoice || $isDraft || $isAsuransi || $isFakturPajak) ? $this->docNominal : null,
                'doc_mata_uang' => ($isInvoice || $isDraft || $isFakturPajak) ? $this->docMataUang : null,
                'doc_jenis_layanan' => null,
                'doc_nomor_lc' => ($isInvoice || $isSuratJalan || $isFakturPajak) ? $this->docNomorLc : null,
                'doc_nomor_po' => ($isInvoice || $isSuratJalan) ? $this->docNomorPo : null,
                'doc_kuantitas' => $isPackingList ? $this->docKuantitas : null,
                'doc_satuan' => $isPackingList ? $this->docSatuan : null,
                'doc_identitas_pengirim' => $isBillOfLading ? $this->docIdentitasPengirim : null,
            ]
        );

        // Berkas lama dari dokumen yang diganti dihapus dari storage.
        if ($existing && $existing->path_file !== $pathBaru) {
            Storage::disk('local')->delete($existing->path_file);
        }

        // Checker berjalan otomatis setelah dokumen berubah (alur To-Be).
        app(RuleBasedEvidenceChecker::class)->periksa($this->pengajuan);

        app(LogAktivitasService::class)->catat(
            $existing ? LogAktivitas::DOKUMEN_DIGANTI : LogAktivitas::DOKUMEN_DIUNGGAH,
            auth()->user()->name.' '.($existing ? 'mengganti' : 'mengunggah')." dokumen {$this->uploadJenis} pada pengajuan {$this->pengajuan->nomor_pengajuan}.",
            auth()->user(),
            $this->pengajuan,
            ['jenis_dokumen' => $this->uploadJenis]
        );

        Flux::toast(variant: 'success', text: 'Dokumen '.$this->uploadJenis.' berhasil diunggah dan pemeriksaan otomatis telah dijalankan.');

        $this->reset('uploadJenis', 'uploadFile', 'docNomorInvoice', 'docIdentitas', 'docNominal', 'docJenisLayanan', 'docNomorDokumen', 'docTanggalDokumen', 'docNomorLc', 'docNomorPo', 'docKuantitas', 'docSatuan', 'docIdentitasPengirim');
        $this->docMataUang = 'USD';
        unset($this->dokumens);
        $this->dispatch('pengajuan-diperbarui');
    }

    public function hapusDokumen(int $dokumenId): void
    {
        $dokumen = $this->pengajuan->dokumens()->findOrFail($dokumenId);

        $this->authorize('delete', $dokumen);

        $jenisDokumen = $dokumen->jenis_dokumen;

        Storage::disk('local')->delete($dokumen->path_file);
        $dokumen->delete();

        app(RuleBasedEvidenceChecker::class)->periksa($this->pengajuan);

        app(LogAktivitasService::class)->catat(
            LogAktivitas::DOKUMEN_DIHAPUS,
            auth()->user()->name." menghapus dokumen {$jenisDokumen} pada pengajuan {$this->pengajuan->nomor_pengajuan}.",
            auth()->user(),
            $this->pengajuan,
            ['jenis_dokumen' => $jenisDokumen]
        );

        Flux::toast(variant: 'success', text: 'Dokumen berhasil dihapus dan pemeriksaan otomatis telah dijalankan.');

        unset($this->dokumens);
        $this->dispatch('pengajuan-diperbarui');
    }

    /**
     * Modal konfirmasi Flux milik komponen ini untuk penghapusan
     * dokumen: method hapusDokumen() aslinya baru berjalan setelah
     * pengguna menyetujui pada modal.
     */
    public function mintaKonfirmasiHapusDokumen(int $dokumenId): void
    {
        $this->dokumenKonfirmasiId = $dokumenId;
        $this->modalKonfirmasi = true;
    }

    public function tutupKonfirmasi(): void
    {
        $this->modalKonfirmasi = false;
        $this->dokumenKonfirmasiId = null;
    }

    public function jalankanAksiKonfirmasi(): void
    {
        $dokumenId = $this->dokumenKonfirmasiId;

        $this->tutupKonfirmasi();

        if ($dokumenId !== null) {
            $this->hapusDokumen($dokumenId);
        }
    }

    /**
     * @return array{judul: string, isi: string, tombol: string, destruktif: bool}|null
     */
    #[Computed]
    public function detailKonfirmasi(): ?array
    {
        $dokumen = $this->dokumenKonfirmasiId !== null
            ? $this->dokumens->firstWhere('id', $this->dokumenKonfirmasiId)
            : null;

        if ($dokumen === null) {
            return null;
        }

        return [
            'judul' => 'Hapus Dokumen Ini?',
            'isi' => 'Berkas '.$dokumen->jenis_dokumen.' ("'.$dokumen->nama_file_asli.'") akan dihapus permanen. Pemeriksaan otomatis akan berjalan ulang tanpa dokumen ini.',
            'tombol' => 'Ya, Hapus Dokumen',
            'destruktif' => true,
        ];
    }
}; ?>

<section class="tile md:col-span-2 xl:col-span-8">
    <div class="flex flex-wrap items-center gap-2.5">
        <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Dokumen Pendukung</h2>
        @if ($this->persyaratanDisesuaikan)
            <span class="pill pill-amber">Persyaratan disesuaikan dari templat</span>
        @endif
    </div>
    <p class="mt-1.5 mb-4 text-[13px] text-mist dark:text-zinc-400">Dokumen yang dipersyaratkan oleh jenis layanan pengajuan ini beserta status unggahannya.</p>

        @php
            $petaDokumen = $this->dokumens->keyBy(fn ($d) => strtolower(trim($d->jenis_dokumen)));
        @endphp

        <div class="-mx-4.25 overflow-x-auto px-4.25 sm:-mx-5.5 sm:px-5.5">
            <table class="tabel-kartu w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-left dark:border-night-line">
                        <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Jenis</th>
                        <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Berkas</th>
                        <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Diunggah</th>
                        <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Status</th>
                        <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pengajuan->persyaratanBerlaku() as $syarat)
                        @php
                            $dokumen = $petaDokumen->get(strtolower(trim($syarat['nama'])));
                        @endphp
                        <tr class="border-b border-line last:border-b-0 dark:border-night-line" wire:key="dokumen-baris-{{ $loop->index }}">
                            <td class="py-3.25 pr-3" data-label="Jenis">
                                <span class="font-mono text-[13px] font-bold text-zinc-900 dark:text-white">{{ $syarat['nama'] }}</span>
                                <span class="pill {{ $syarat['wajib'] ? 'pill-indigo' : 'pill-zinc' }} px-2 py-0.5 text-[10.5px]">{{ $syarat['wajib'] ? 'Wajib' : 'Opsional' }}</span>
                            </td>
                            <td class="py-3.25 pr-3 text-[13.5px] text-zinc-600 dark:text-zinc-300" data-label="Berkas">
                                @if ($dokumen)
                                    <span class="break-all">{{ $dokumen->nama_file_asli }}</span>
                                    <span class="block text-[12px] text-mist dark:text-zinc-400">{{ $dokumen->ukuranFormatted() }}</span>
                                @else
                                    <span class="text-mist dark:text-zinc-400">— belum diunggah</span>
                                @endif
                            </td>
                            <td class="py-3.25 pr-3 text-[13.5px] whitespace-nowrap text-zinc-500 dark:text-zinc-400" data-label="Diunggah">
                                @if ($dokumen)
                                    <span title="{{ $dokumen->updated_at->format('d M Y H:i') }}">{{ $dokumen->updated_at->diffForHumans() }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-3.25 pr-3" data-label="Status"><span class="pill {{ $dokumen ? 'pill-green' : 'pill-amber' }}">{{ $dokumen ? 'Terunggah' : 'Belum Diunggah' }}</span></td>
                            <td class="aksi py-3.25" data-label="Aksi">
                                @if ($dokumen)
                                    <span class="flex gap-2">
                                        <a href="{{ route('dokumens.download', $dokumen) }}" class="btn btn-small">Unduh</a>
                                        @can('delete', $dokumen)
                                            <button type="button" class="btn btn-danger btn-small" wire:click="mintaKonfirmasiHapusDokumen({{ $dokumen->id }})">Hapus</button>
                                        @endcan
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-[13.5px] text-zinc-500 dark:text-zinc-400" colspan="5">Layanan ini belum memiliki persyaratan dokumen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->dapatKelolaDokumen)
            <form wire:submit="unggahDokumen" class="mt-4.5 rounded-[18px] border-[1.5px] border-dashed border-line p-4.5 dark:border-night-line">
                <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Unggah / Ganti Dokumen</h3>

                <div class="mt-3.5 grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="uploadJenis" label="Jenis Dokumen" placeholder="Pilih jenis dokumen">
                        @foreach ($this->daftarJenisDokumen as $jenis)
                            <option value="{{ $jenis }}">{{ $jenis }}{{ $petaDokumen->has(strtolower(trim($jenis))) ? ' (sudah ada — unggah ulang untuk mengganti)' : '' }}</option>
                        @endforeach
                    </flux:select>

                <flux:input wire:model="uploadFile" label="Berkas (PDF/JPG/PNG, maks 5 MB)" type="file" />
                </div>
                @error('uploadFile')
                    <flux:text class="text-red-500">{{ $message }}</flux:text>
                @enderror

                @php
                    $jenisKunci = $this->jenisTerpilihKunci;
                    $jenisWajibTerpilih = collect($pengajuan->persyaratanBerlaku())
                        ->contains(fn ($syarat) => strtolower(trim($syarat['nama'])) === $jenisKunci && ($syarat['wajib'] ?? false));
                @endphp
                @if ($jenisWajibTerpilih)
                    <div class="mt-4 rounded-[14px] bg-soft p-4 dark:bg-night-soft">
                        <flux:text class="font-medium">Data yang tertera pada dokumen — diisi persis seperti pada dokumen, dipakai untuk pemeriksaan otomatis:</flux:text>
                        <div class="mt-3.5 grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="docNomorDokumen" label="Nomor Dokumen" type="text" required />
                        <flux:input wire:model="docTanggalDokumen" label="Tanggal Dokumen" type="date" required />
                        </div>
                    </div>
                @endif

                @if ($this->jenisTerpilihInvoice)
                    <div class="mt-4 rounded-[14px] bg-soft p-4 dark:bg-night-soft">
                        <flux:text class="font-medium">Data khusus Invoice:</flux:text>
                        <div class="mt-3.5 grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="docNomorInvoice" label="Nomor Invoice pada Dokumen" type="text" required />
                        <flux:input wire:model="docIdentitas" label="Identitas Nasabah pada Dokumen" type="text" required />
                        <flux:input wire:model="docNominal" label="Nominal pada Dokumen" type="number" step="0.01" min="0" required />
                        <flux:select wire:model="docMataUang" label="Mata Uang pada Dokumen" required>
                            @foreach (MataUang::cases() as $mataUang)
                                <option value="{{ $mataUang->value }}">{{ $mataUang->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="docNomorLc" label="Nomor LC pada Dokumen" type="text" required />
                        <flux:input wire:model="docNomorPo" label="Nomor PO pada Dokumen" type="text" required />
                        </div>
                    </div>
                @endif

                @if (str_contains($jenisKunci, 'faktur pajak'))
                    <div class="mt-4 rounded-[14px] bg-soft p-4 dark:bg-night-soft">
                        <flux:text class="font-medium">Data khusus Faktur Pajak:</flux:text>
                        <div class="mt-3.5 grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="docNomorLc" label="Nomor LC pada Dokumen" type="text" required />
                        <flux:input wire:model="docNomorInvoice" label="Nomor Invoice pada Dokumen" type="text" required />
                        <flux:input wire:model="docNominal" label="Nominal pada Dokumen (Total DPP + PPN)" type="number" step="0.01" min="0" required />
                        <flux:select wire:model="docMataUang" label="Mata Uang pada Dokumen" required>
                            @foreach (MataUang::cases() as $mataUang)
                                <option value="{{ $mataUang->value }}">{{ $mataUang->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="docIdentitas" label="Identitas Penjual pada Dokumen" type="text" required />
                        </div>
                    </div>
                @endif

                @if (str_contains($jenisKunci, 'draft'))
                    <div class="mt-4 rounded-[14px] bg-soft p-4 dark:bg-night-soft">
                        <flux:text class="font-medium">Data khusus Draft (wesel):</flux:text>
                        <div class="mt-3.5 grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="docNominal" label="Nominal pada Dokumen" type="number" step="0.01" min="0" required />
                        <flux:select wire:model="docMataUang" label="Mata Uang pada Dokumen" required>
                            @foreach (MataUang::cases() as $mataUang)
                                <option value="{{ $mataUang->value }}">{{ $mataUang->label() }}</option>
                            @endforeach
                        </flux:select>
                        </div>
                    </div>
                @endif

                @if (str_contains($jenisKunci, 'asuransi'))
                    <div class="mt-4 rounded-[14px] bg-soft p-4 dark:bg-night-soft">
                        <flux:text class="font-medium">Data khusus Asuransi:</flux:text>
                        <div class="mt-3.5 grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="docNominal" label="Nilai Pertanggungan pada Dokumen" type="number" step="0.01" min="0" required />
                        </div>
                    </div>
                @endif

                @if (str_contains($jenisKunci, 'surat jalan'))
                    <div class="mt-4 rounded-[14px] bg-soft p-4 dark:bg-night-soft">
                        <flux:text class="font-medium">Data khusus Surat Jalan:</flux:text>
                        <div class="mt-3.5 grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="docNomorLc" label="Nomor LC pada Dokumen" type="text" required />
                        <flux:input wire:model="docNomorPo" label="Nomor PO pada Dokumen" type="text" required />
                        </div>
                    </div>
                @endif

                @if (str_contains($jenisKunci, 'packing list'))
                    <div class="mt-4 rounded-[14px] bg-soft p-4 dark:bg-night-soft">
                        <flux:text class="font-medium">Data khusus Packing List:</flux:text>
                        <div class="mt-3.5 grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="docKuantitas" label="Kuantitas pada Dokumen" type="number" step="0.001" min="0" required />
                        <flux:input wire:model="docSatuan" label="Satuan Kuantitas" type="text" required />
                        </div>
                    </div>
                @endif

                @if (str_contains($jenisKunci, 'bill of lading'))
                    <div class="mt-4 rounded-[14px] bg-soft p-4 dark:bg-night-soft">
                        <flux:text class="font-medium">Data khusus Bill of Lading:</flux:text>
                        <div class="mt-3.5 grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="docIdentitasPengirim" label="Identitas Pengirim (Shipper) pada Dokumen" type="text" required />
                        </div>
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <flux:button variant="primary" type="submit" class="w-full sm:w-auto">Unggah Dokumen</flux:button>
                    <span class="text-[12.5px] text-mist dark:text-zinc-400">Setiap unggahan otomatis menjalankan pemeriksaan ulang terhadap Data LC.</span>
                </div>
            </form>
        @elseif (auth()->user()->isNasabah())
            <flux:text class="mt-4 block">Dokumen tidak dapat diubah pada status pengajuan saat ini.</flux:text>
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
</section>
