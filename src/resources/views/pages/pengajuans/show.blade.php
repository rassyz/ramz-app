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
    @php
        $petaPillStatus = [
            'menunggu_verifikasi' => 'pill-amber',
            'perlu_perbaikan' => 'pill-red',
            'terverifikasi' => 'pill-indigo',
            'diproses' => 'pill-blue',
            'selesai' => 'pill-green',
            'dibatalkan' => 'pill-zinc',
        ];
        $pillStatusDetail = $petaPillStatus[$pengajuan->status->value] ?? 'pill-zinc';
    @endphp

    {{-- Kepala halaman: breadcrumb + nomor + meta + aksi (selaras mockup) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="mb-1 text-[12.5px] text-mist dark:text-zinc-400">
                @if (auth()->user()->isNasabah())
                    <a href="{{ route('nasabah.pengajuan') }}" wire:navigate class="font-semibold text-primary hover:underline">Pengajuan Saya</a>
                @else
                    <a href="{{ route('pengajuans.index') }}" wire:navigate class="font-semibold text-primary hover:underline">Pantau Pengajuan</a>
                @endif
                / Detail
            </div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $pengajuan->nomor_pengajuan }}</h1>
            <p class="mt-1 text-[13px] text-mist dark:text-zinc-400">{{ $pengajuan->user->name }} — {{ $pengajuan->layanan->nama }} — dibuat {{ $pengajuan->created_at->format('d M Y, H.i') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="pill {{ $pillStatusDetail }} px-3.5 py-1.75 text-[13px]">{{ $pengajuan->status->label() }}</span>
            @can('cancel', $pengajuan)
                <button type="button" class="btn" wire:click="mintaKonfirmasi('batalkan')">Batalkan Pengajuan</button>
            @endcan
            @can('delete', $pengajuan)
                <button type="button" class="btn btn-danger" wire:click="mintaKonfirmasi('hapusPengajuan')">Hapus Pengajuan</button>
            @endcan
        </div>
    </div>

    <div class="bento mt-5">
        @if (auth()->user()->isNasabah() && $pengajuan->status === \App\Enums\StatusPengajuan::PerluPerbaikan)
            <section class="tile border-transparent bg-red-50 md:col-span-2 xl:col-span-12 dark:bg-[#331314]">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-red-700 dark:text-red-300">Pengajuan Dikembalikan untuk Perbaikan</h2>
                @if ($pengajuan->catatan)
                    <p class="mt-2 text-[13.5px] leading-normal text-zinc-700 dark:text-zinc-200">Catatan operator: {{ $pengajuan->catatan }}</p>
                @endif
                <p class="mt-2 text-[13.5px] leading-normal text-zinc-700 dark:text-zinc-200">Perbaiki dokumen pada bagian Dokumen Pendukung di bawah, lalu ajukan kembali pengajuan ini.</p>
                @can('resubmit', $pengajuan)
                    <button type="button" class="btn btn-primary mt-3.5" wire:click="mintaKonfirmasi('ajukanKembali')">Ajukan Kembali</button>
                @endcan
            </section>
        @endif

        <livewire:pages::pengajuans.show-dokumen :pengajuan="$pengajuan" />

        <livewire:pages::pengajuans.show-data-lc :pengajuan="$pengajuan" />

        <livewire:pages::pengajuans.show-pemeriksaan :pengajuan="$pengajuan" />

        @if (auth()->user()->isOperator())
            <livewire:pages::pengajuans.show-verifikasi :pengajuan="$pengajuan" />
        @endif

        {{-- Riwayat status & verifikasi sebagai linimasa (selaras mockup) --}}
        <section class="tile md:col-span-1 xl:col-span-4">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Riwayat</h2>
            </div>
            <p class="mb-2 text-[11px] font-bold tracking-[0.07em] text-mist uppercase dark:text-zinc-400">Status</p>
            <div class="relative pl-6.5">
                <span class="absolute top-1.5 bottom-1.5 left-2 w-0.5 bg-line dark:bg-night-line"></span>
                @forelse ($this->riwayat as $riwayat)
                    <div class="relative pb-4.5 last:pb-0.5">
                        <span class="absolute top-0.75 left-[-24.5px] size-3.25 rounded-full border-[3px] border-primary bg-white dark:bg-night-tile"></span>
                        <p class="text-[13.5px] font-semibold text-zinc-900 dark:text-white">
                            @if ($riwayat->status_dari)
                                {{ $riwayat->status_dari->label() }} → {{ $riwayat->status_ke->label() }}
                            @else
                                Pengajuan dibuat
                            @endif
                        </p>
                        <p class="mt-0.5 text-xs leading-normal text-mist dark:text-zinc-400">{{ $riwayat->created_at->format('d M Y H:i') }} — oleh {{ $riwayat->diubahOleh?->name ?? 'Sistem' }}</p>
                        @if ($riwayat->catatan)
                            <p class="mt-0.5 text-[12.5px] leading-normal text-zinc-600 dark:text-zinc-300">{{ $riwayat->catatan }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-[13px] text-zinc-500 dark:text-zinc-400">Belum ada riwayat status.</p>
                @endforelse
            </div>
            <p class="mt-5 mb-2 text-[11px] font-bold tracking-[0.07em] text-mist uppercase dark:text-zinc-400">Verifikasi</p>
            <div class="relative pl-6.5">
                <span class="absolute top-1.5 bottom-1.5 left-2 w-0.5 bg-line dark:bg-night-line"></span>
                @forelse ($this->verifikasiRiwayat as $verifikasi)
                    <div class="relative pb-4.5 last:pb-0.5">
                        <span class="absolute top-0.75 left-[-24.5px] size-3.25 rounded-full border-[3px] border-primary bg-white dark:bg-night-tile"></span>
                        <p class="text-[13.5px] font-semibold text-zinc-900 dark:text-white">{{ $verifikasi->keputusan->label() }}</p>
                        <p class="mt-0.5 text-xs leading-normal text-mist dark:text-zinc-400">{{ $verifikasi->created_at->format('d M Y H:i') }} — oleh {{ $verifikasi->operator->name }}@if ($verifikasi->hasilPemeriksaan) — acuan pemeriksaan: {{ $verifikasi->hasilPemeriksaan->status->label() }}@endif</p>
                        @if ($verifikasi->catatan)
                            <p class="mt-0.5 text-[12.5px] leading-normal text-zinc-600 dark:text-zinc-300">{{ $verifikasi->catatan }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-[13px] text-zinc-500 dark:text-zinc-400">Belum ada keputusan verifikasi.</p>
                @endforelse
            </div>
        </section>

        {{-- Data Pengajuan ringkas --}}
        <section class="tile md:col-span-1 xl:col-span-4">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Data Pengajuan</h2>
            </div>
            <dl class="grid grid-cols-[auto_1fr] gap-x-4.5 gap-y-2.25 text-[13.5px]">
                <dt class="text-mist dark:text-zinc-400">Nasabah</dt>
                <dd class="text-right font-semibold text-zinc-900 dark:text-white">{{ $pengajuan->user->name }}<span class="block text-[12px] font-medium break-all text-mist dark:text-zinc-400">{{ $pengajuan->user->email }}</span></dd>
                <dt class="text-mist dark:text-zinc-400">Jenis Layanan</dt>
                <dd class="text-right font-semibold text-zinc-900 dark:text-white">{{ $pengajuan->layanan->nama }}</dd>
                <dt class="text-mist dark:text-zinc-400">Nomor Invoice</dt>
                <dd class="text-right font-semibold text-zinc-900 dark:text-white">{{ $pengajuan->nomor_invoice ?? '-' }}</dd>
                <dt class="text-mist dark:text-zinc-400">Status</dt>
                <dd class="text-right"><span class="pill {{ $pillStatusDetail }}">{{ $pengajuan->status->label() }}</span></dd>
                @if ($pengajuan->catatan)
                    <dt class="text-mist dark:text-zinc-400">Catatan</dt>
                    <dd class="text-right font-semibold text-zinc-900 dark:text-white">{{ $pengajuan->catatan }}</dd>
                @endif
            </dl>
        </section>

        {{-- Dokumen Fisik --}}
        <section class="tile md:col-span-2 xl:col-span-4">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Dokumen Fisik</h2>
                @if ($pengajuan->dokumen_fisik_diterima_at)
                    <span class="pill pill-green">Fisik Disetarakan</span>
                @else
                    <span class="pill pill-amber">Fisik Belum Diterima</span>
                @endif
            </div>
            @if ($pengajuan->dokumen_fisik_diterima_at)
                <p class="text-[13.5px] leading-normal text-zinc-600 dark:text-zinc-300">Diterima &amp; disetarakan oleh {{ $pengajuan->disetarakanOleh?->name ?? '-' }} pada {{ $pengajuan->dokumen_fisik_diterima_at->format('d M Y H:i') }}.</p>
            @else
                <p class="text-[13.5px] leading-normal text-zinc-600 dark:text-zinc-300">Dokumen fisik belum diterima / belum disetarakan dengan data masukan nasabah.</p>
                @can('verify', $pengajuan)
                    @unless ($pengajuan->status === \App\Enums\StatusPengajuan::Selesai || $pengajuan->status === \App\Enums\StatusPengajuan::Dibatalkan)
                        <button type="button" class="btn btn-primary btn-small mt-3.5" wire:click="mintaKonfirmasi('tandaiFisik')">Tandai Fisik Diterima &amp; Disetarakan</button>
                    @endunless
                @endcan
            @endif
        </section>
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
