<?php

use App\Models\Layanan;
use App\Models\LogAktivitas;
use App\Services\LogAktivitasService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Master Layanan')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $modalKonfirmasi = false;

    public ?int $targetKonfirmasiId = null;

    // Form tambah / ubah layanan
    public ?int $editingId = null;

    public string $kode = '';

    public string $nama = '';

    public string $deskripsi = '';

    public bool $is_active = true;

    /** @var array<int, array{nama: string, wajib: bool}> */
    public array $persyaratan = [['nama' => '', 'wajib' => true]];

    /**
     * Halaman ini hanya untuk Admin (route juga dilindungi
     * middleware role:admin).
     */
    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function layanans()
    {
        return Layanan::query()
            ->withCount('pengajuans')
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('nama', 'like', '%'.$this->search.'%')
                        ->orWhere('kode', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('nama')
            ->paginate(10);
    }

    public function tambahSyarat(): void
    {
        $this->persyaratan[] = ['nama' => '', 'wajib' => true];
    }

    public function hapusSyarat(int $index): void
    {
        unset($this->persyaratan[$index]);
        $this->persyaratan = array_values($this->persyaratan);

        if ($this->persyaratan === []) {
            $this->persyaratan = [['nama' => '', 'wajib' => true]];
        }
    }

    public function simpan(): void
    {
        $validated = $this->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('layanans', 'kode')->ignore($this->editingId)],
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'persyaratan.*.nama' => ['nullable', 'string', 'max:255'],
            'persyaratan.*.wajib' => ['boolean'],
        ]);

        // Buang baris syarat yang namanya kosong.
        $persyaratan = collect($this->persyaratan)
            ->filter(fn ($syarat) => trim($syarat['nama'] ?? '') !== '')
            ->map(fn ($syarat) => ['nama' => trim($syarat['nama']), 'wajib' => (bool) ($syarat['wajib'] ?? false)])
            ->values()
            ->all();

        $data = [
            'kode' => strtoupper(trim($validated['kode'])),
            'nama' => $validated['nama'],
            'deskripsi' => $validated['deskripsi'] ?: null,
            'is_active' => $this->is_active,
            'persyaratan_dokumen' => $persyaratan,
        ];

        if ($this->editingId) {
            Layanan::findOrFail($this->editingId)->update($data);

            app(LogAktivitasService::class)->catat(
                LogAktivitas::LAYANAN_DIUBAH,
                'Admin '.auth()->user()->name." mengubah layanan {$data['nama']} ({$data['kode']}).",
                auth()->user()
            );

            Flux::toast(variant: 'success', text: 'Layanan berhasil diperbarui.');
        } else {
            Layanan::create($data);

            app(LogAktivitasService::class)->catat(
                LogAktivitas::LAYANAN_DIBUAT,
                'Admin '.auth()->user()->name." membuat layanan {$data['nama']} ({$data['kode']}).",
                auth()->user()
            );

            Flux::toast(variant: 'success', text: 'Layanan berhasil ditambahkan.');
        }

        $this->batalUbah();
    }

    public function mulaiUbah(int $id): void
    {
        $layanan = Layanan::findOrFail($id);

        $this->editingId = $layanan->id;
        $this->kode = $layanan->kode;
        $this->nama = $layanan->nama;
        $this->deskripsi = $layanan->deskripsi ?? '';
        $this->is_active = $layanan->is_active;
        $this->persyaratan = $layanan->persyaratan_dokumen ?: [['nama' => '', 'wajib' => true]];
    }

    public function batalUbah(): void
    {
        $this->reset('editingId', 'kode', 'nama', 'deskripsi');
        $this->is_active = true;
        $this->persyaratan = [['nama' => '', 'wajib' => true]];
        $this->resetValidation();
    }

    /**
     * Modal konfirmasi Flux untuk penghapusan layanan: method
     * hapus() aslinya baru berjalan setelah admin menyetujui pada
     * modal.
     */
    public function mintaKonfirmasiHapus(int $id): void
    {
        $this->targetKonfirmasiId = $id;
        $this->modalKonfirmasi = true;
    }

    public function tutupKonfirmasi(): void
    {
        $this->modalKonfirmasi = false;
        $this->targetKonfirmasiId = null;
    }

    public function jalankanAksiKonfirmasi(): void
    {
        $id = $this->targetKonfirmasiId;

        $this->tutupKonfirmasi();

        if ($id !== null) {
            $this->hapus($id);
        }
    }

    /**
     * @return array{judul: string, isi: string, tombol: string}|null
     */
    #[Computed]
    public function detailKonfirmasi(): ?array
    {
        $layanan = $this->targetKonfirmasiId !== null ? Layanan::find($this->targetKonfirmasiId) : null;

        if ($layanan === null) {
            return null;
        }

        return [
            'judul' => 'Hapus Layanan Ini?',
            'isi' => 'Layanan '.$layanan->nama.' ('.$layanan->kode.') akan dihapus permanen. Bila layanan ini sudah memiliki pengajuan, penghapusan akan ditolak dan layanan sebaiknya dinonaktifkan saja.',
            'tombol' => 'Ya, Hapus Layanan',
        ];
    }

    public function hapus(int $id): void
    {
        $layanan = Layanan::findOrFail($id);

        if ($layanan->pengajuans()->exists()) {
            Flux::toast(variant: 'danger', text: 'Layanan ini sudah memiliki pengajuan dan tidak dapat dihapus. Nonaktifkan saja bila tidak lagi digunakan.');

            return;
        }

        app(LogAktivitasService::class)->catat(
            LogAktivitas::LAYANAN_DIHAPUS,
            'Admin '.auth()->user()->name." menghapus layanan {$layanan->nama} ({$layanan->kode}).",
            auth()->user()
        );

        $layanan->delete();

        Flux::toast(variant: 'success', text: 'Layanan berhasil dihapus.');
    }
}; ?>
<section class="w-full">
    {{-- Baris judul + tombol ikon (selaras mockup) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Master Layanan</h1>
            <p class="mt-1 text-[13px] text-mist dark:text-zinc-400">Kelola jenis layanan perdagangan dan pembayaran internasional beserta persyaratan dokumennya.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('notifikasis.index') }}" wire:navigate class="relative hidden size-10 items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Notifikasi" title="Notifikasi">
                <flux:icon name="bell" class="size-5" />
                @php
                    $belumDibacaLayanan = auth()->user()->notifikasis()->whereNull('dibaca_at')->count();
                @endphp
                @if ($belumDibacaLayanan > 0)
                    <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $belumDibacaLayanan }}</span>
                @endif
            </a>
            <button type="button" x-data x-on:click="$flux.dark = !$flux.dark" class="hidden size-10 cursor-pointer items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Ganti tema gelap atau terang" title="Mode gelap / terang">
                <flux:icon.moon class="size-5 dark:hidden" />
                <flux:icon.sun class="hidden size-5 dark:block" />
            </button>
        </div>
    </div>

    <div class="bento mt-5">
        <section class="tile md:col-span-2 xl:col-span-12">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">{{ $editingId ? 'Ubah Layanan' : 'Tambah Layanan' }}</h2>
            </div>
            <form wire:submit="simpan">
                <div class="grid gap-4 lg:grid-cols-3">
                    <flux:input wire:model="kode" label="Kode" type="text" required placeholder="mis. LC-IMP" />
                    <flux:input wire:model="nama" label="Nama Layanan" type="text" required class="lg:col-span-2" />
                    <flux:textarea wire:model="deskripsi" label="Deskripsi" rows="3" class="lg:col-span-3" />
                    <div class="lg:col-span-3">
                        <flux:checkbox wire:model="is_active" label="Aktif (dapat dipilih nasabah)" />
                    </div>
                </div>

                <p class="mt-5 mb-2.5 text-[12.5px] font-semibold text-zinc-900 dark:text-white">Persyaratan Dokumen Templat</p>
                <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($persyaratan as $index => $syarat)
                        <div class="flex items-center gap-2.5 rounded-xl bg-soft px-3 py-2.25 dark:bg-night-soft" wire:key="syarat-{{ $index }}">
                            <flux:checkbox wire:model.live="persyaratan.{{ $index }}.wajib" />
                            <flux:input wire:model="persyaratan.{{ $index }}.nama" type="text" size="sm" placeholder="Nama dokumen, mis. Invoice" class="min-w-0 flex-1" />
                            <span class="pill {{ $syarat['wajib'] ? 'pill-amber' : 'pill-zinc' }} shrink-0">{{ $syarat['wajib'] ? 'Wajib' : 'Opsional' }}</span>
                            <button type="button" class="btn btn-danger btn-small shrink-0" wire:click="hapusSyarat({{ $index }})">Hapus</button>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3.5 flex flex-wrap items-center gap-3">
                    <button type="button" class="btn" wire:click="tambahSyarat">+ Tambah Dokumen</button>
                    <span class="text-[12.5px] text-mist dark:text-zinc-400">Baris baru muncul sebagai chip — isi nama dokumennya, lalu atur statusnya lewat kotak centang (pill mengikuti).</span>
                </div>

                <div class="mt-5 flex gap-2">
                    <flux:button variant="primary" type="submit" class="flex-1">{{ $editingId ? 'Simpan Perubahan' : 'Tambah Layanan' }}</flux:button>
                    @if ($editingId)
                        <flux:button wire:click="batalUbah">Batal</flux:button>
                    @endif
                </div>
            </form>
        </section>

        <section class="tile md:col-span-2 xl:col-span-12">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Daftar Layanan</h2>
                <flux:input wire:model.live="search" type="search" placeholder="Cari nama atau kode..." class="w-full sm:w-60" />
            </div>
            <div class="-mx-4.25 overflow-x-auto px-4.25 sm:-mx-5.5 sm:px-5.5">
                <table class="tabel-kartu w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left dark:border-night-line">
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Kode</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Nama</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Persyaratan Dokumen</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Pengajuan</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Status</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->layanans as $layanan)
                            <tr class="border-b border-line last:border-b-0 dark:border-night-line" wire:key="layanan-{{ $layanan->id }}">
                                <td class="py-3.25 pr-3 font-mono text-[13px] font-bold whitespace-nowrap text-zinc-900 dark:text-white" data-label="Kode">{{ $layanan->kode }}</td>
                                <td class="py-3.25 pr-3 text-[13.5px] font-semibold text-zinc-900 dark:text-white" data-label="Nama">{{ $layanan->nama }}</td>
                                <td class="py-3.25 pr-3" data-label="Persyaratan Dokumen">
                                    <span class="flex flex-wrap gap-1.5">
                                        @forelse ($layanan->persyaratan_dokumen ?? [] as $syarat)
                                            <span class="pill {{ $syarat['wajib'] ? 'pill-indigo' : 'pill-zinc' }} px-2 py-0.5 text-[10.5px]">{{ $syarat['nama'] }}{{ $syarat['wajib'] ? '' : ' (opsional)' }}</span>
                                        @empty
                                            <span class="text-[13px] text-zinc-500 dark:text-zinc-400">Belum diatur</span>
                                        @endforelse
                                    </span>
                                </td>
                                <td class="py-3.25 pr-3 text-[13.5px] text-zinc-600 dark:text-zinc-300" data-label="Pengajuan">{{ $layanan->pengajuans_count }}</td>
                                <td class="py-3.25 pr-3" data-label="Status"><span class="pill {{ $layanan->is_active ? 'pill-green' : 'pill-zinc' }}">{{ $layanan->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="aksi py-3.25" data-label="Aksi">
                                    <span class="flex gap-2">
                                        <button type="button" class="btn btn-small" wire:click="mulaiUbah({{ $layanan->id }})">Ubah</button>
                                        <button type="button" class="btn btn-danger btn-small" wire:click="mintaKonfirmasiHapus({{ $layanan->id }})">Hapus</button>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($this->layanans->total() > 0)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-mist dark:text-zinc-400">
                    <span>Menampilkan {{ $this->layanans->firstItem() }}–{{ $this->layanans->lastItem() }} dari {{ $this->layanans->total() }} layanan</span>
                    @if ($this->layanans->hasPages())
                        @php
                            $halamanIni = $this->layanans->currentPage();
                            $halamanAkhir = $this->layanans->lastPage();
                            $daftarHalaman = range(max(1, $halamanIni - 2), min($halamanAkhir, $halamanIni + 2));
                        @endphp
                        <div class="flex items-center gap-1.5">
                            <button type="button" wire:click="previousPage" class="chip {{ $this->layanans->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman sebelumnya">‹</button>
                            @foreach ($daftarHalaman as $halaman)
                                <button type="button" wire:click="gotoPage({{ $halaman }})" class="chip {{ $halaman === $halamanIni ? 'chip-aktif' : '' }}">{{ $halaman }}</button>
                            @endforeach
                            <button type="button" wire:click="nextPage" class="chip {{ $halamanIni === $halamanAkhir ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman berikutnya">›</button>
                        </div>
                    @endif
                </div>
            @endif
            <p class="mt-3 text-[12.5px] leading-normal text-mist dark:text-zinc-400">Layanan yang sudah memiliki pengajuan tidak dapat dihapus — nonaktifkan saja lewat Ubah agar tidak dipilih untuk pengajuan baru.</p>
        </section>
    </div>

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
