<?php

use App\Enums\KeputusanVerifikasi;
use App\Enums\MataUang;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPengajuan;
use App\Models\Dokumen;
use App\Models\Pengajuan;
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

    public string $docNomorInvoice = '';

    public string $docIdentitas = '';

    public string $docNominal = '';

    public string $docMataUang = 'USD';

    public string $docJenisLayanan = '';

    // Form verifikasi & pengelolaan status (Fase 4, khusus operator)
    public string $keputusan = '';

    public string $catatanVerifikasi = '';

    public string $statusPembayaranBaru = '';

    /**
     * Akses detail ditegakkan PengajuanPolicy: staf dapat melihat
     * semua pengajuan, nasabah hanya miliknya sendiri.
     */
    public function mount(Pengajuan $pengajuan): void
    {
        $this->authorize('view', $pengajuan);
        $this->pengajuan = $pengajuan->load(['user', 'layanan', 'pembayaran']);
        $this->statusPembayaranBaru = $this->pengajuan->pembayaran?->status->value ?? '';
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
     * Nama jenis dokumen dari persyaratan layanan pengajuan ini.
     *
     * @return list<string>
     */
    #[Computed]
    public function daftarJenisDokumen(): array
    {
        return collect($this->pengajuan->layanan->persyaratan_dokumen ?? [])
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
    }

    public function unggahDokumen(): void
    {
        $this->authorize('create', [Dokumen::class, $this->pengajuan]);

        $rules = [
            'uploadJenis' => ['required', 'string', Rule::in($this->daftarJenisDokumen)],
            'uploadFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        if ($this->jenisTerpilihInvoice) {
            $rules += [
                'docNomorInvoice' => ['required', 'string', 'max:100'],
                'docIdentitas' => ['required', 'string', 'max:255'],
                'docNominal' => ['required', 'numeric', 'min:0'],
                'docMataUang' => ['required', Rule::enum(MataUang::class)],
                'docJenisLayanan' => ['required', 'string', 'max:50'],
            ];
        }

        $this->validate($rules, [
            'uploadFile.required' => 'Pilih berkas dokumen terlebih dahulu.',
            'uploadFile.mimes' => 'Berkas harus berformat PDF, JPG, JPEG, atau PNG.',
            'uploadFile.max' => 'Ukuran berkas maksimal 5 MB.',
            'docNomorInvoice.required' => 'Nomor invoice pada dokumen wajib diisi.',
            'docIdentitas.required' => 'Identitas nasabah pada dokumen wajib diisi.',
            'docNominal.required' => 'Nominal pada dokumen wajib diisi.',
        ]);

        $existing = $this->pengajuan->dokumens()
            ->where('jenis_dokumen', $this->uploadJenis)
            ->first();

        $pathBaru = $this->uploadFile->store('dokumen/'.$this->pengajuan->id, 'local');

        $isInvoice = $this->jenisTerpilihInvoice;

        Dokumen::updateOrCreate(
            ['pengajuan_id' => $this->pengajuan->id, 'jenis_dokumen' => $this->uploadJenis],
            [
                'nama_file_asli' => $this->uploadFile->getClientOriginalName(),
                'path_file' => $pathBaru,
                'mime' => $this->uploadFile->getMimeType(),
                'ukuran_file' => $this->uploadFile->getSize(),
                'diupload_oleh' => auth()->id(),
                'doc_nomor_invoice' => $isInvoice ? $this->docNomorInvoice : null,
                'doc_identitas_nasabah' => $isInvoice ? $this->docIdentitas : null,
                'doc_nominal' => $isInvoice ? $this->docNominal : null,
                'doc_mata_uang' => $isInvoice ? $this->docMataUang : null,
                'doc_jenis_layanan' => $isInvoice ? $this->docJenisLayanan : null,
            ]
        );

        // Berkas lama dari dokumen yang diganti dihapus dari storage.
        if ($existing && $existing->path_file !== $pathBaru) {
            Storage::disk('local')->delete($existing->path_file);
        }

        // Checker berjalan otomatis setelah dokumen berubah (alur To-Be).
        app(RuleBasedEvidenceChecker::class)->periksa($this->pengajuan);

        Flux::toast(variant: 'success', text: 'Dokumen '.$this->uploadJenis.' berhasil diunggah dan pemeriksaan otomatis telah dijalankan.');

        $this->reset('uploadJenis', 'uploadFile', 'docNomorInvoice', 'docIdentitas', 'docNominal', 'docJenisLayanan');
        $this->docMataUang = 'USD';
        unset($this->dokumens, $this->hasilTerakhir, $this->riwayatPemeriksaan);
    }

    public function hapusDokumen(int $dokumenId): void
    {
        $dokumen = $this->pengajuan->dokumens()->findOrFail($dokumenId);

        $this->authorize('delete', $dokumen);

        Storage::disk('local')->delete($dokumen->path_file);
        $dokumen->delete();

        app(RuleBasedEvidenceChecker::class)->periksa($this->pengajuan);

        Flux::toast(variant: 'success', text: 'Dokumen berhasil dihapus dan pemeriksaan otomatis telah dijalankan.');

        unset($this->dokumens, $this->hasilTerakhir, $this->riwayatPemeriksaan);
    }

    /**
     * Pemeriksaan manual oleh staf (Operator/Admin) dari halaman detail.
     */
    public function jalankanPemeriksaan(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isOperator(), 403);

        $hasil = app(RuleBasedEvidenceChecker::class)->periksa($this->pengajuan);

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
     * Operator menyimpan status pembayaran baru.
     */
    public function simpanStatusPembayaran(): void
    {
        $this->authorize('updatePembayaran', $this->pengajuan);

        $this->validate([
            'statusPembayaranBaru' => ['required', Rule::enum(StatusPembayaran::class)],
        ]);

        try {
            app(VerifikasiService::class)->ubahStatusPembayaran(
                $this->pengajuan,
                auth()->user(),
                StatusPembayaran::from($this->statusPembayaranBaru)
            );
        } catch (InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->pengajuan->refresh()->load('pembayaran.diubahOleh');
        unset($this->riwayat);

        Flux::toast(variant: 'success', text: 'Status pembayaran berhasil diperbarui.');
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

    @if (auth()->user()->isNasabah() && $pengajuan->status === \App\Enums\StatusPengajuan::PerluPerbaikan)
        <div class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
            <flux:heading level="2">Pengajuan Dikembalikan untuk Perbaikan</flux:heading>
            @if ($pengajuan->catatan)
                <flux:text class="mt-2 block">Catatan operator: {{ $pengajuan->catatan }}</flux:text>
            @endif
            <flux:text class="mt-2 block">Perbaiki dokumen pada bagian Dokumen Pendukung di bawah, lalu ajukan kembali pengajuan ini.</flux:text>
            @can('resubmit', $pengajuan)
                <flux:button variant="primary" class="mt-3" wire:click="ajukanKembali" wire:confirm="Ajukan kembali pengajuan ini untuk diverifikasi ulang?">Ajukan Kembali</flux:button>
            @endcan
        </div>
    @endif

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
                    @if ($pengajuan->pembayaran->diubah_oleh)
                        <flux:text size="sm" class="mt-2 block">Terakhir diubah oleh {{ $pengajuan->pembayaran->diubahOleh?->name ?? '-' }} pada {{ $pengajuan->pembayaran->updated_at->format('d M Y H:i') }}</flux:text>
                    @endif
                @else
                    <flux:text class="mt-3 block">Belum ada data pembayaran terhubung.</flux:text>
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

                        <flux:button variant="primary" type="submit">Simpan Keputusan</flux:button>
                    </form>
                @endcan

                <div class="space-y-6">
                    @can('updateStatus', $pengajuan)
                        @if ($pengajuan->status === \App\Enums\StatusPengajuan::Terverifikasi)
                            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                <flux:heading level="3">Kelola Status</flux:heading>
                                <flux:text class="mt-2 block">Pengajuan sudah terverifikasi dan dapat mulai diproses.</flux:text>
                                <flux:button variant="primary" class="mt-3" wire:click="majukanStatus('diproses')" wire:confirm="Ubah status menjadi Diproses?">Mulai Diproses</flux:button>
                            </div>
                        @elseif ($pengajuan->status === \App\Enums\StatusPengajuan::Diproses)
                            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                <flux:heading level="3">Kelola Status</flux:heading>
                                <flux:text class="mt-2 block">Pengajuan sedang diproses dan dapat ditandai selesai.</flux:text>
                                <flux:button variant="primary" class="mt-3" wire:click="majukanStatus('selesai')" wire:confirm="Tandai pengajuan ini sebagai Selesai?">Tandai Selesai</flux:button>
                            </div>
                        @endif
                    @endcan

                    @can('updatePembayaran', $pengajuan)
                        @if ($pengajuan->pembayaran)
                            <form wire:submit="simpanStatusPembayaran" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                <flux:heading level="3">Status Pembayaran</flux:heading>
                                <flux:select wire:model="statusPembayaranBaru" label="Status Pembayaran" required>
                                    @foreach (\App\Enums\StatusPembayaran::cases() as $statusPembayaran)
                                        <option value="{{ $statusPembayaran->value }}">{{ $statusPembayaran->label() }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:text size="sm">Status hanya dapat berpindah satu langkah dari status saat ini ({{ $pengajuan->pembayaran->status->label() }}).</flux:text>
                                <flux:button variant="primary" type="submit">Simpan Status Pembayaran</flux:button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    @endif

    <flux:separator class="my-8" />

    <div>
        <flux:heading level="2">Dokumen Pendukung</flux:heading>
        <flux:subheading>Dokumen yang dipersyaratkan oleh jenis layanan pengajuan ini beserta status unggahannya.</flux:subheading>

        @php
            $petaDokumen = $this->dokumens->keyBy(fn ($d) => strtolower(trim($d->jenis_dokumen)));
        @endphp

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>Dokumen</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Berkas</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($pengajuan->layanan->persyaratan_dokumen ?? [] as $syarat)
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
                                        <flux:button size="sm" variant="danger" wire:click="hapusDokumen({{ $dokumen->id }})" wire:confirm="Hapus dokumen {{ $dokumen->jenis_dokumen }}?">Hapus</flux:button>
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

                @if ($this->jenisTerpilihInvoice)
                    <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text class="font-medium">Data yang tertera pada dokumen Invoice — diisi persis seperti pada dokumen, dipakai untuk pemeriksaan otomatis:</flux:text>
                        <flux:input wire:model="docNomorInvoice" label="Nomor Invoice pada Dokumen" type="text" required />
                        <flux:input wire:model="docIdentitas" label="Identitas Nasabah pada Dokumen" type="text" required />
                        <flux:input wire:model="docNominal" label="Nominal pada Dokumen" type="number" step="0.01" min="0" required />
                        <flux:select wire:model="docMataUang" label="Mata Uang pada Dokumen" required>
                            @foreach (MataUang::cases() as $mataUang)
                                <option value="{{ $mataUang->value }}">{{ $mataUang->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="docJenisLayanan" label="Jenis Layanan pada Dokumen" required>
                            @foreach (\App\Models\Layanan::aktif()->orderBy('nama')->get() as $layananPilihan)
                                <option value="{{ $layananPilihan->kode }}">{{ $layananPilihan->nama }} ({{ $layananPilihan->kode }})</option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif

                <flux:button variant="primary" type="submit">Unggah Dokumen</flux:button>
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
</section>
