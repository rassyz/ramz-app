<?php

use App\Enums\StatusPengajuan;
use App\Models\Pengajuan;
use App\Models\LogAktivitas;
use App\Services\LogAktivitasService;
use App\Services\NotifikasiService;
use App\Services\VerifikasiService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail Pengajuan')] class extends Component
{
    public Pengajuan $pengajuan;

    public bool $modalKonfirmasi = false;

    public ?string $aksiKonfirmasi = null;

    /**
     * Akses detail ditegakkan PengajuanPolicy: staf dapat melihat
     * semua pengajuan, nasabah hanya miliknya sendiri.
     */
    public function mount(Pengajuan $pengajuan): void
    {
        $this->authorize('view', $pengajuan);
        $this->pengajuan = $pengajuan->load(['user', 'layanan']);
    }

    /**
     * Menyegarkan salinan pengajuan milik induk setiap kali
     * komponen anak mengubah data bersama (mis. snapshot
     * persyaratan disimpan oleh komponen persyaratan), supaya
     * daftar periksa dokumen dan pilihan jenis dokumen pada form
     * unggah membaca keadaan terbaru.
     */
    #[On('pengajuan-diperbarui')]
    public function segarkan(): void
    {
        $this->pengajuan->refresh();
    }

    #[Computed]
    public function riwayat()
    {
        return $this->pengajuan->riwayatStatuses()->with('diubahOleh')->get();
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
        unset($this->riwayat);

        Flux::toast(variant: 'success', text: 'Pengajuan berhasil diajukan kembali dan menunggu verifikasi operator.');
    }

    /**
     * Modal konfirmasi Flux: tombol aksi meminta persetujuan dulu di
     * sini; method aksi aslinya baru berjalan dari
     * jalankanAksiKonfirmasi() setelah pengguna menyetujui di modal.
     */
    public function mintaKonfirmasi(string $aksi): void
    {
        $this->aksiKonfirmasi = $aksi;
        $this->modalKonfirmasi = true;
    }

    public function tutupKonfirmasi(): void
    {
        $this->modalKonfirmasi = false;
        $this->aksiKonfirmasi = null;
    }

    public function jalankanAksiKonfirmasi(): void
    {
        $aksi = $this->aksiKonfirmasi;

        $this->tutupKonfirmasi();

        match ($aksi) {
            'batalkan' => $this->batalkan(),
            'hapusPengajuan' => $this->hapusPengajuan(),
            'ajukanKembali' => $this->ajukanKembali(),
            'tandaiFisik' => $this->tandaiFisikDisetarakan(),
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
                'isi' => "Pengajuan {$nomor} beserta {$this->pengajuan->dokumens()->count()} dokumen dan seluruh hasil pemeriksaannya akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.",
                'tombol' => 'Ya, Hapus Permanen',
                'destruktif' => true,
            ],
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
            default => null,
        };
    }

    /**
     * Admin menghapus permanen pengajuan yang sudah Dibatalkan
     * (Fase 11): berkas dokumen pada penyimpanan ikut dihapus, data
     * anak terhapus oleh cascade basis data, dan penghapusan dicatat
     * pada log aktivitas tanpa tautan record (record-nya hilang).
     */
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

<livewire:pages::pengajuans.show-data-lc :pengajuan="$pengajuan" />        </div>


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

<livewire:pages::pengajuans.show-verifikasi :pengajuan="$pengajuan" />

<livewire:pages::pengajuans.show-dokumen :pengajuan="$pengajuan" />

    <livewire:pages::pengajuans.show-pemeriksaan :pengajuan="$pengajuan" />

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
