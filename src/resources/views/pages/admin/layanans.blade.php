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
    <flux:heading level="1" size="xl">Master Layanan</flux:heading>
    <flux:subheading>Kelola jenis layanan perdagangan dan pembayaran internasional beserta persyaratan dokumennya.</flux:subheading>

    <flux:separator class="mt-4 mb-6" />

    <div class="grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-1">
            <flux:heading level="2">{{ $editingId ? 'Ubah Layanan' : 'Tambah Layanan' }}</flux:heading>

            <form wire:submit="simpan" class="mt-4 space-y-4">
                <flux:input wire:model="kode" label="Kode" type="text" required placeholder="mis. LC-IMP" />
                <flux:input wire:model="nama" label="Nama Layanan" type="text" required />
                <flux:textarea wire:model="deskripsi" label="Deskripsi" rows="3" />
                <flux:checkbox wire:model="is_active" label="Aktif (dapat dipilih nasabah)" />

                <div class="space-y-3">
                    <flux:heading level="3">Persyaratan Dokumen</flux:heading>

                    @foreach ($persyaratan as $index => $syarat)
                        <div class="flex items-end gap-2">
                            <flux:input wire:model="persyaratan.{{ $index }}.nama" type="text" placeholder="Nama dokumen, mis. Invoice" class="flex-1" />
                            <flux:checkbox wire:model="persyaratan.{{ $index }}.wajib" label="Wajib" />
                            <flux:button size="sm" variant="danger" wire:click="hapusSyarat({{ $index }})">Hapus</flux:button>
                        </div>
                    @endforeach

                    <flux:button size="sm" wire:click="tambahSyarat">+ Tambah Dokumen</flux:button>
                </div>

                <div class="flex gap-2">
                    <flux:button variant="primary" type="submit" class="flex-1">{{ $editingId ? 'Simpan Perubahan' : 'Tambah Layanan' }}</flux:button>
                    @if ($editingId)
                        <flux:button wire:click="batalUbah">Batal</flux:button>
                    @endif
                </div>
            </form>
        </div>

        <div class="lg:col-span-2">
            <div class="flex items-center justify-between gap-4">
                <flux:heading level="2">Daftar Layanan</flux:heading>
                <flux:input wire:model.live="search" type="search" placeholder="Cari nama atau kode..." class="max-w-64" />
            </div>

            <flux:table :paginate="$this->layanans" class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Kode</flux:table.column>
                    <flux:table.column>Nama</flux:table.column>
                    <flux:table.column>Syarat Dokumen</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->layanans as $layanan)
                        <flux:table.row :key="$layanan->id">
                            <flux:table.cell class="font-medium">{{ $layanan->kode }}</flux:table.cell>
                            <flux:table.cell>{{ $layanan->nama }}</flux:table.cell>
                            <flux:table.cell>
                                @forelse ($layanan->persyaratan_dokumen ?? [] as $syarat)
                                    <flux:badge size="sm" :color="$syarat['wajib'] ? 'blue' : 'zinc'">{{ $syarat['nama'] }}{{ $syarat['wajib'] ? '' : ' (opsional)' }}</flux:badge>
                                @empty
                                    <flux:text size="sm">Belum diatur</flux:text>
                                @endforelse
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$layanan->is_active ? 'green' : 'zinc'">{{ $layanan->is_active ? 'Aktif' : 'Nonaktif' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" wire:click="mulaiUbah({{ $layanan->id }})">Ubah</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="hapus({{ $layanan->id }})" wire:confirm="Hapus layanan {{ $layanan->nama }}?">Hapus</flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</section>
