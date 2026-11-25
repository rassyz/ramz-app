<?php

use App\Enums\KeputusanVerifikasi;
use App\Enums\MataUang;
use App\Enums\StatusPengajuan;
use App\Models\Dokumen;
use App\Models\Pengajuan;
use App\Models\LogAktivitas;
use App\Services\LogAktivitasService;
use App\Services\NotifikasiService;
use App\Services\RuleBasedEvidenceChecker;
use App\Services\VerifikasiService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Detail Pengajuan')] class extends Component
{
    use WithFileUploads;

    public Pengajuan $pengajuan;

    // Form unggah / ganti dokumen
    public string $uploadJenis = '';

    public $uploadFile = null;

    public $uploadLc = null;

    public bool $modalKonfirmasi = false;

    public ?string $aksiKonfirmasi = null;

    public ?int $dokumenKonfirmasiId = null;

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

    /** @var list<array{nama: string, wajib: bool}> */
    public array $syaratBaris = [];

    // Form verifikasi & pengelolaan status (Fase 4, khusus operator)
    public string $keputusan = '';

    public string $catatanVerifikasi = '';

    /**
     * Akses detail ditegakkan PengajuanPolicy: staf dapat melihat
     * semua pengajuan, nasabah hanya miliknya sendiri.
     */
    public function mount(Pengajuan $pengajuan): void
    {
        $this->authorize('view', $pengajuan);
        $this->pengajuan = $pengajuan->load(['user', 'layanan']);
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
        $this->syaratBaris = collect($this->pengajuan->persyaratanBerlaku())
            ->map(fn ($syarat) => ['nama' => $syarat['nama'], 'wajib' => (bool) ($syarat['wajib'] ?? false)])
            ->all();
    }

    #[Computed]
    public function riwayat()
    {
        return $this->pengajuan->riwayatStatuses()->with('diubahOleh')->get();
    }

    #[Computed]
    public function dokumens()
    {
        return $this->pengajuan->dokumens()->get();
    }

    /**
     * Nama jenis dokumen dari snapshot persyaratan pengajuan ini.
     *
     * @return list<string>
     */
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

    #[Computed]
    public function verifikasiRiwayat()
    {
        return $this->pengajuan->verifikasiDokumens()
            ->with(['operator', 'hasilPemeriksaan'])
            ->reorder()
            ->latest()
            ->get();
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
        unset($this->dokumens, $this->hasilTerakhir, $this->riwayatPemeriksaan);
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

        unset($this->dokumens, $this->hasilTerakhir, $this->riwayatPemeriksaan);
    }

    /**
     * Pemeriksaan manual oleh staf (Operator/Admin) dari halaman detail.
     */
    /**
     * Menandai dokumen fisik telah diterima dan disetarakan dengan
     * data masukan nasabah (Fase 10, D5 — info lapangan LC). Penanda
     * informatif sesuai alur lapangan; BUKAN gerbang verifikasi.
     */
    public function tandaiFisikDisetarakan(): void
    {
        $this->authorize('verify', $this->pengajuan);

        if ($this->pengajuan->dokumen_fisik_diterima_at !== null
            || in_array($this->pengajuan->status, [StatusPengajuan::Selesai, StatusPengajuan::Dibatalkan], true)) {
            return;
        }

        $this->pengajuan->update([
            'dokumen_fisik_diterima_at' => now(),
            'disetarakan_oleh' => auth()->id(),
        ]);
        $this->pengajuan->load('disetarakanOleh');

        app(LogAktivitasService::class)->catat(
            LogAktivitas::DOKUMEN_FISIK_DISETARAKAN,
            'Dokumen fisik pengajuan '.$this->pengajuan->nomor_pengajuan.' diterima dan disetarakan dengan data masukan nasabah.',
            pengajuan: $this->pengajuan,
        );

        Flux::toast(variant: 'success', text: 'Dokumen fisik ditandai telah diterima dan disetarakan.');
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
        unset($this->hasilTerakhir, $this->riwayatPemeriksaan);
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
        unset($this->hasilTerakhir, $this->riwayatPemeriksaan);
    }

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
        unset($this->riwayat, $this->verifikasiRiwayat, $this->dapatKelolaDokumen);

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
        unset($this->riwayat, $this->dapatKelolaDokumen);

        Flux::toast(variant: 'success', text: 'Status pengajuan berhasil diperbarui.');
    }

    /**
     * Nasabah mengajukan kembali pengajuan yang perlu perbaikan.
     */
    public function ajukanKembali(): void
    {
        $this->authorize('resubmit', $this->pengajuan);

        try {
            app(VerifikasiService::class)->ajukanKembali($this->pengajuan, auth()->user());
        } catch (InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->pengajuan->refresh();
        unset($this->riwayat, $this->dapatKelolaDokumen);

        Flux::toast(variant: 'success', text: 'Pengajuan berhasil diajukan kembali dan menunggu verifikasi operator.');
    }

    /**
     * Admin menghapus permanen pengajuan yang sudah Dibatalkan
     * (Fase 11): berkas dokumen pada penyimpanan ikut dihapus, data
     * anak terhapus oleh cascade basis data, dan penghapusan dicatat
     * pada log aktivitas tanpa tautan record (record-nya hilang).
     */
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
     * Modal konfirmasi Flux: tombol aksi meminta persetujuan dulu di
     * sini; method aksi aslinya baru berjalan dari
     * jalankanAksiKonfirmasi() setelah pengguna menyetujui di modal.
     */
    public function mintaKonfirmasi(string $aksi, ?int $dokumenId = null): void
    {
        $this->aksiKonfirmasi = $aksi;
        $this->dokumenKonfirmasiId = $dokumenId;
        $this->modalKonfirmasi = true;
    }

    public function tutupKonfirmasi(): void
    {
        $this->modalKonfirmasi = false;
        $this->aksiKonfirmasi = null;
        $this->dokumenKonfirmasiId = null;
    }

    public function jalankanAksiKonfirmasi(): void
    {
        $aksi = $this->aksiKonfirmasi;
        $dokumenId = $this->dokumenKonfirmasiId;

        $this->tutupKonfirmasi();

        match ($aksi) {
            'batalkan' => $this->batalkan(),
            'hapusPengajuan' => $this->hapusPengajuan(),
            'hapusLc' => $this->hapusLc(),
            'ajukanKembali' => $this->ajukanKembali(),
            'tandaiFisik' => $this->tandaiFisikDisetarakan(),
            'majukanDiproses' => $this->majukanStatus('diproses'),
            'majukanSelesai' => $this->majukanStatus('selesai'),
            'hapusDokumen' => $dokumenId !== null ? $this->hapusDokumen($dokumenId) : null,
            default => null,
        };
    }

    /**
     * Judul, penjelasan konsekuensi, dan label tombol persetujuan
     * untuk aksi yang sedang menunggu konfirmasi.
     *
     * @return array{judul: string, isi: string, tombol: string, destruktif: bool}|null
     */
    #[Computed]
    public function detailKonfirmasi(): ?array
    {
        $nomor = $this->pengajuan->nomor_pengajuan;

        return match ($this->aksiKonfirmasi) {
            'batalkan' => [
                'judul' => 'Batalkan Pengajuan Ini?',
                'isi' => "Pengajuan {$nomor} akan berstatus Dibatalkan dan tidak dapat dilanjutkan lagi. Dokumen yang sudah diunggah tetap tersimpan sebagai arsip.",
                'tombol' => 'Ya, Batalkan',
                'destruktif' => true,
            ],
            'hapusPengajuan' => [
                'judul' => 'Hapus Permanen Pengajuan Ini?',
                'isi' => "Pengajuan {$nomor} beserta {$this->dokumens->count()} dokumen dan seluruh hasil pemeriksaannya akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.",
                'tombol' => 'Ya, Hapus Permanen',
                'destruktif' => true,
            ],
            'hapusLc' => [
                'judul' => 'Hapus Lampiran Dokumen LC?',
                'isi' => 'Berkas LC "'.$this->pengajuan->nama_file_lc.'" akan dihapus dari pengajuan ini. Data LC yang sudah diisi tidak ikut terhapus.',
                'tombol' => 'Ya, Hapus Lampiran',
                'destruktif' => true,
            ],
            'hapusDokumen' => $this->teksKonfirmasiHapusDokumen(),
            'ajukanKembali' => [
                'judul' => 'Ajukan Kembali Pengajuan Ini?',
                'isi' => 'Pengajuan ini akan kembali berstatus Menunggu Verifikasi untuk diperiksa ulang oleh operator.',
                'tombol' => 'Ya, Ajukan Kembali',
                'destruktif' => false,
            ],
            'tandaiFisik' => [
                'judul' => 'Tandai Dokumen Fisik Diterima?',
                'isi' => 'Dokumen fisik pengajuan ini akan ditandai telah diterima dan disetarakan dengan data masukan nasabah.',
                'tombol' => 'Ya, Tandai Diterima',
                'destruktif' => false,
            ],
            'majukanDiproses' => [
                'judul' => 'Mulai Memproses Pengajuan Ini?',
                'isi' => 'Status pengajuan akan berubah menjadi Diproses.',
                'tombol' => 'Ya, Mulai Diproses',
                'destruktif' => false,
            ],
            'majukanSelesai' => [
                'judul' => 'Tandai Pengajuan Ini Selesai?',
                'isi' => 'Status pengajuan akan berubah menjadi Selesai dan pengajuan tidak dapat diubah lagi.',
                'tombol' => 'Ya, Tandai Selesai',
                'destruktif' => false,
            ],
            default => null,
        };
    }

    /**
     * @return array{judul: string, isi: string, tombol: string, destruktif: bool}|null
     */
    private function teksKonfirmasiHapusDokumen(): ?array
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

    public function hapusPengajuan(): void
    {
        $this->authorize('delete', $this->pengajuan);

        $nomorPengajuan = $this->pengajuan->nomor_pengajuan;

        DB::transaction(function () {
            foreach ($this->pengajuan->dokumens as $dokumen) {
                Storage::disk('local')->delete($dokumen->path_file);
            }

            if ($this->pengajuan->path_file_lc) {
                Storage::disk('local')->delete($this->pengajuan->path_file_lc);
            }

            $this->pengajuan->delete();
        });

        app(LogAktivitasService::class)->catat(
            LogAktivitas::PENGAJUAN_DIHAPUS,
            'Admin '.auth()->user()->name." menghapus pengajuan {$nomorPengajuan} yang telah dibatalkan beserta seluruh dokumen dan hasil pemeriksaannya.",
            auth()->user(),
            null,
            ['nomor_pengajuan' => $nomorPengajuan]
        );

        $this->redirect(route('pengajuans.index'), navigate: true);
    }

    public function batalkan(): void
    {
        $this->authorize('cancel', $this->pengajuan);

        $olehStaf = ! auth()->user()->isNasabah();

        DB::transaction(function () use ($olehStaf) {
            $statusLama = $this->pengajuan->status;

            $this->pengajuan->update([
                'status' => StatusPengajuan::Dibatalkan,
                'catatan' => null,
            ]);

            $this->pengajuan->riwayatStatuses()->create([
                'status_dari' => $statusLama,
                'status_ke' => StatusPengajuan::Dibatalkan,
                'catatan' => $olehStaf ? 'Dibatalkan oleh staf.' : 'Dibatalkan oleh nasabah.',
                'changed_by' => auth()->id(),
            ]);
        });

        $this->pengajuan->refresh();
        unset($this->riwayat);

        app(NotifikasiService::class)->statusBerubah($this->pengajuan);

        app(LogAktivitasService::class)->catat(
            LogAktivitas::PENGAJUAN_DIBATALKAN,
            ($olehStaf ? 'Staf ' : 'Nasabah ').auth()->user()->name." membatalkan pengajuan {$this->pengajuan->nomor_pengajuan}.",
            auth()->user(),
            $this->pengajuan
        );

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
                <flux:button variant="danger" wire:click="mintaKonfirmasi('batalkan')">Batalkan Pengajuan</flux:button>
            @endcan
            @can('delete', $pengajuan)
                <flux:button variant="danger" wire:click="mintaKonfirmasi('hapusPengajuan')">Hapus Pengajuan</flux:button>
            @endcan
        </div>
    </div>

    <flux:button size="sm" class="mt-4" :href="auth()->user()->isNasabah() ? route('nasabah.pengajuan') : route('pengajuans.index')">&larr; Kembali</flux:button>

    <flux:separator class="mt-4 mb-6" />

    @if (auth()->user()->isNasabah() && $pengajuan->status === \App\Enums\StatusPengajuan::PerluPerbaikan)
        <div class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
            <flux:heading level="2">Pengajuan Dikembalikan untuk Perbaikan</flux:heading>
            @if ($pengajuan->catatan)
                <flux:text class="mt-2 block">Catatan operator: {{ $pengajuan->catatan }}</flux:text>
            @endif
            <flux:text class="mt-2 block">Perbaiki dokumen pada bagian Dokumen Pendukung di bawah, lalu ajukan kembali pengajuan ini.</flux:text>
            @can('resubmit', $pengajuan)
                <flux:button variant="primary" class="mt-3 w-full sm:w-auto" wire:click="mintaKonfirmasi('ajukanKembali')">Ajukan Kembali</flux:button>
            @endcan
        </div>
    @endif

    <div class="mb-6 rounded-3xl bg-white p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading level="2">Dokumen Fisik</flux:heading>
                @if ($pengajuan->dokumen_fisik_diterima_at)
                    <flux:text class="mt-1 block">
                        Diterima &amp; disetarakan oleh {{ $pengajuan->disetarakanOleh?->name ?? '-' }}
                        pada {{ $pengajuan->dokumen_fisik_diterima_at->format('d M Y H:i') }}.
                    </flux:text>
                @else
                    <flux:text class="mt-1 block">Dokumen fisik belum diterima / belum disetarakan dengan data masukan nasabah.</flux:text>
                @endif
            </div>
            @if ($pengajuan->dokumen_fisik_diterima_at)
                <flux:badge color="green" size="lg">Fisik Disetarakan</flux:badge>
            @else
                <flux:badge color="amber" size="lg">Fisik Belum Diterima</flux:badge>
                @can('verify', $pengajuan)
                    @unless ($pengajuan->status === \App\Enums\StatusPengajuan::Selesai || $pengajuan->status === \App\Enums\StatusPengajuan::Dibatalkan)
                        <flux:button variant="primary" class="w-full sm:w-auto" wire:click="mintaKonfirmasi('tandaiFisik')">Tandai Fisik Diterima &amp; Disetarakan</flux:button>
                    @endunless
                @endcan
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <div>
                <flux:heading level="2">Data Pengajuan</flux:heading>
                <flux:table class="mt-3">
                    <flux:table.rows>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Nasabah</flux:table.cell>
                            <flux:table.cell class="break-all">{{ $pengajuan->user->name }} ({{ $pengajuan->user->email }})</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Jenis Layanan</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->layanan->nama }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell class="font-medium">Nomor Invoice</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->nomor_invoice ?? '-' }}</flux:table.cell>
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

                    <form wire:submit="simpanPersyaratan" class="mt-4 space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
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
                                <flux:button type="button" variant="danger" wire:click="mintaKonfirmasi('hapusLc')">Hapus Lampiran</flux:button>
                            @endif
                        </div>
                    </form>
                @endif
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

            <flux:heading level="2" class="mt-8">Riwayat Verifikasi</flux:heading>
            <div class="mt-4 space-y-4">
                @forelse ($this->verifikasiRiwayat as $verifikasi)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge :color="$verifikasi->keputusan->color()">{{ $verifikasi->keputusan->label() }}</flux:badge>
                        </div>
                        <flux:text size="sm" class="mt-2 block">
                            {{ $verifikasi->created_at->format('d M Y H:i') }} — oleh {{ $verifikasi->operator->name }}
                            @if ($verifikasi->hasilPemeriksaan)
                                — acuan pemeriksaan: {{ $verifikasi->hasilPemeriksaan->status->label() }}
                            @endif
                        </flux:text>
                        @if ($verifikasi->catatan)
                            <flux:text size="sm" class="mt-1 block">{{ $verifikasi->catatan }}</flux:text>
                        @endif
                    </div>
                @empty
                    <flux:text>Belum ada keputusan verifikasi.</flux:text>
                @endforelse
            </div>
        </div>
    </div>

    @if (auth()->user()->isOperator())
        <flux:separator class="my-8" />

        <div>
            <flux:heading level="2">Verifikasi & Pengelolaan — Operator</flux:heading>
            <flux:subheading>Keputusan dan pengelolaan status hanya dapat dilakukan oleh operator.</flux:subheading>

            <div class="mt-4 grid gap-6 lg:grid-cols-2">
                @can('verify', $pengajuan)
                    <form wire:submit="simpanKeputusan" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading level="3">Keputusan Verifikasi</flux:heading>

                        @if ($this->hasilTerakhir)
                            <flux:text>Acuan hasil pemeriksaan terakhir: <flux:badge :color="$this->hasilTerakhir->status->color()">{{ $this->hasilTerakhir->status->label() }}</flux:badge> ({{ $this->hasilTerakhir->created_at->format('d M Y H:i') }})</flux:text>
                        @else
                            <flux:text>Perhatian: checker belum pernah berjalan untuk pengajuan ini. Keputusan tetap dapat diambil berdasarkan pemeriksaan dokumen secara langsung.</flux:text>
                        @endif

                        <flux:radio.group wire:model="keputusan" label="Keputusan" required>
                            <flux:radio value="terverifikasi" label="Terverifikasi" />
                            <flux:radio value="perlu_perbaikan" label="Perlu Perbaikan" />
                        </flux:radio.group>

                        <flux:textarea wire:model="catatanVerifikasi" label="Catatan (wajib untuk Perlu Perbaikan)" rows="3" />
                        @error('catatanVerifikasi')
                            <flux:text class="text-red-500">{{ $message }}</flux:text>
                        @enderror

                        <flux:button variant="primary" type="submit" class="w-full sm:w-auto">Simpan Keputusan</flux:button>
                    </form>
                @endcan

                <div class="space-y-6">
                    @can('updateStatus', $pengajuan)
                        @if ($pengajuan->status === \App\Enums\StatusPengajuan::Terverifikasi)
                            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                <flux:heading level="3">Kelola Status</flux:heading>
                                <flux:text class="mt-2 block">Pengajuan sudah terverifikasi dan dapat mulai diproses.</flux:text>
                                <flux:button variant="primary" class="mt-3 w-full sm:w-auto" wire:click="mintaKonfirmasi('majukanDiproses')">Mulai Diproses</flux:button>
                            </div>
                        @elseif ($pengajuan->status === \App\Enums\StatusPengajuan::Diproses)
                            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                <flux:heading level="3">Kelola Status</flux:heading>
                                <flux:text class="mt-2 block">Pengajuan sedang diproses dan dapat ditandai selesai.</flux:text>
                                <flux:button variant="primary" class="mt-3 w-full sm:w-auto" wire:click="mintaKonfirmasi('majukanSelesai')">Tandai Selesai</flux:button>
                            </div>
                        @endif
                    @endcan

                </div>
            </div>
        </div>
    @endif

    <flux:separator class="my-8" />

    <div>
        <div class="flex flex-wrap items-center gap-3">
            <flux:heading level="2">Dokumen Pendukung</flux:heading>
            @if ($this->persyaratanDisesuaikan)
                <flux:badge color="amber">Persyaratan disesuaikan dari templat</flux:badge>
            @endif
        </div>
        <flux:subheading>Dokumen yang dipersyaratkan oleh jenis layanan pengajuan ini beserta status unggahannya.</flux:subheading>

        @php
            $petaDokumen = $this->dokumens->keyBy(fn ($d) => strtolower(trim($d->jenis_dokumen)));
        @endphp

        {{-- Kartu dokumen (mobile) — Fase 8.2 --}}
        <div class="mt-4 space-y-3 lg:hidden">
            @forelse ($pengajuan->persyaratanBerlaku() as $syarat)
                @php $dokumen = $petaDokumen->get(strtolower(trim($syarat['nama']))); @endphp
                <div class="rounded-2xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-start justify-between gap-3">
                        <span class="font-medium">
                            {{ $syarat['nama'] }}
                            <flux:badge size="sm" :color="$syarat['wajib'] ? 'blue' : 'zinc'">{{ $syarat['wajib'] ? 'Wajib' : 'Opsional' }}</flux:badge>
                        </span>
                        <flux:badge :color="$dokumen ? 'green' : 'amber'">{{ $dokumen ? 'Terunggah' : 'Belum Diunggah' }}</flux:badge>
                    </div>
                    <p class="mt-2 wrap-break-word text-sm text-zinc-500 dark:text-zinc-400">
                        @if ($dokumen)
                            {{ $dokumen->nama_file_asli }} ({{ $dokumen->ukuranFormatted() }})
                        @else
                            Belum ada berkas diunggah.
                        @endif
                    </p>
                    @if ($dokumen)
                        <div class="mt-3 flex gap-2">
                            <flux:button :href="route('dokumens.download', $dokumen)" class="flex-1">Unduh</flux:button>
                            @can('delete', $dokumen)
                                <flux:button variant="danger" class="flex-1" wire:click="mintaKonfirmasi('hapusDokumen', {{ $dokumen->id }})">Hapus</flux:button>
                            @endcan
                        </div>
                    @endif
                </div>
            @empty
                <flux:text>Layanan ini belum memiliki persyaratan dokumen.</flux:text>
            @endforelse
        </div>

        <div class="mt-4 hidden lg:block">
        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>Dokumen</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Berkas</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($pengajuan->persyaratanBerlaku() as $syarat)
                    @php $dokumen = $petaDokumen->get(strtolower(trim($syarat['nama']))); @endphp
                    <flux:table.row>
                        <flux:table.cell class="font-medium">
                            {{ $syarat['nama'] }}
                            <flux:badge size="sm" :color="$syarat['wajib'] ? 'blue' : 'zinc'">{{ $syarat['wajib'] ? 'Wajib' : 'Opsional' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$dokumen ? 'green' : 'amber'">{{ $dokumen ? 'Terunggah' : 'Belum Diunggah' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($dokumen)
                                {{ $dokumen->nama_file_asli }} ({{ $dokumen->ukuranFormatted() }})
                            @else
                                -
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($dokumen)
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" :href="route('dokumens.download', $dokumen)">Unduh</flux:button>
                                    @can('delete', $dokumen)
                                        <flux:button size="sm" variant="danger" wire:click="mintaKonfirmasi('hapusDokumen', {{ $dokumen->id }})">Hapus</flux:button>
                                    @endcan
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell>Layanan ini belum memiliki persyaratan dokumen.</flux:table.cell>
                        <flux:table.cell></flux:table.cell>
                        <flux:table.cell></flux:table.cell>
                        <flux:table.cell></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        </div>

        @if ($this->dapatKelolaDokumen)
            <form wire:submit="unggahDokumen" class="mt-6 max-w-xl space-y-4">
                <flux:heading level="3">Unggah / Ganti Dokumen</flux:heading>

                <flux:select wire:model.live="uploadJenis" label="Jenis Dokumen" placeholder="Pilih jenis dokumen">
                    @foreach ($this->daftarJenisDokumen as $jenis)
                        <option value="{{ $jenis }}">{{ $jenis }}{{ $petaDokumen->has(strtolower(trim($jenis))) ? ' (sudah ada — unggah ulang untuk mengganti)' : '' }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="uploadFile" label="Berkas (PDF/JPG/PNG, maks 5 MB)" type="file" />
                @error('uploadFile')
                    <flux:text class="text-red-500">{{ $message }}</flux:text>
                @enderror

                @php
                    $jenisKunci = $this->jenisTerpilihKunci;
                    $jenisWajibTerpilih = collect($pengajuan->persyaratanBerlaku())
                        ->contains(fn ($syarat) => strtolower(trim($syarat['nama'])) === $jenisKunci && ($syarat['wajib'] ?? false));
                @endphp
                @if ($jenisWajibTerpilih)
                    <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="font-medium">Data yang tertera pada dokumen — diisi persis seperti pada dokumen, dipakai untuk pemeriksaan otomatis:</flux:text>
                        <flux:input wire:model="docNomorDokumen" label="Nomor Dokumen" type="text" required />
                        <flux:input wire:model="docTanggalDokumen" label="Tanggal Dokumen" type="date" required />
                    </div>
                @endif

                @if ($this->jenisTerpilihInvoice)
                    <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="font-medium">Data khusus Invoice:</flux:text>
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
                @endif

                @if (str_contains($jenisKunci, 'faktur pajak'))
                    <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="font-medium">Data khusus Faktur Pajak:</flux:text>
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
                @endif

                @if (str_contains($jenisKunci, 'draft'))
                    <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="font-medium">Data khusus Draft (wesel):</flux:text>
                        <flux:input wire:model="docNominal" label="Nominal pada Dokumen" type="number" step="0.01" min="0" required />
                        <flux:select wire:model="docMataUang" label="Mata Uang pada Dokumen" required>
                            @foreach (MataUang::cases() as $mataUang)
                                <option value="{{ $mataUang->value }}">{{ $mataUang->label() }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif

                @if (str_contains($jenisKunci, 'asuransi'))
                    <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="font-medium">Data khusus Asuransi:</flux:text>
                        <flux:input wire:model="docNominal" label="Nilai Pertanggungan pada Dokumen" type="number" step="0.01" min="0" required />
                    </div>
                @endif

                @if (str_contains($jenisKunci, 'surat jalan'))
                    <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="font-medium">Data khusus Surat Jalan:</flux:text>
                        <flux:input wire:model="docNomorLc" label="Nomor LC pada Dokumen" type="text" required />
                        <flux:input wire:model="docNomorPo" label="Nomor PO pada Dokumen" type="text" required />
                    </div>
                @endif

                @if (str_contains($jenisKunci, 'packing list'))
                    <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="font-medium">Data khusus Packing List:</flux:text>
                        <flux:input wire:model="docKuantitas" label="Kuantitas pada Dokumen" type="number" step="0.001" min="0" required />
                        <flux:input wire:model="docSatuan" label="Satuan Kuantitas" type="text" required />
                    </div>
                @endif

                @if (str_contains($jenisKunci, 'bill of lading'))
                    <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="font-medium">Data khusus Bill of Lading:</flux:text>
                        <flux:input wire:model="docIdentitasPengirim" label="Identitas Pengirim (Shipper) pada Dokumen" type="text" required />
                    </div>
                @endif

                <flux:button variant="primary" type="submit" class="w-full sm:w-auto">Unggah Dokumen</flux:button>
            </form>
        @elseif (auth()->user()->isNasabah())
            <flux:text class="mt-4 block">Dokumen tidak dapat diubah pada status pengajuan saat ini.</flux:text>
        @endif
    </div>

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

    @if ($this->detailKonfirmasi)
        <flux:modal wire:model="modalKonfirmasi" class="md:w-md">
            <div class="space-y-5">
                <div class="flex items-start gap-3">
                    @if ($this->detailKonfirmasi['destruktif'])
                        <div class="shrink-0 rounded-full bg-red-100 p-2 text-red-600 dark:bg-red-950 dark:text-red-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" />
                            </svg>
                        </div>
                    @endif
                    <div>
                        <flux:heading level="2">{{ $this->detailKonfirmasi['judul'] }}</flux:heading>
                        <flux:text class="mt-2 block">{{ $this->detailKonfirmasi['isi'] }}</flux:text>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <flux:button wire:click="tutupKonfirmasi">Batal</flux:button>
                    <flux:button :variant="$this->detailKonfirmasi['destruktif'] ? 'danger' : 'primary'" wire:click="jalankanAksiKonfirmasi">{{ $this->detailKonfirmasi['tombol'] }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</section>
