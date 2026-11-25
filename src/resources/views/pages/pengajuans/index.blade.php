<?php

use App\Enums\StatusPengajuan;
use App\Models\Layanan;
use App\Models\Pengajuan;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pantau Pengajuan')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $layananFilter = '';

    // Form buat pengajuan oleh staf (Fase 11 — pengajuan dibuat
    // Operator karena akun nasabah baru ada setelah LC turun).
    public string $formNasabahId = '';

    public string $formLayananId = '';


    /**
     * Pantauan menyeluruh hanya untuk Admin dan Operator.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isOperator(), 403);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function daftarNasabah()
    {
        return User::where('role', 'nasabah')->orderBy('name')->get();
    }

    /**
     * Operator/Admin membuat pengajuan untuk seorang nasabah: cukup
     * nasabah + layanan. Nomor pengajuan dibuat otomatis, snapshot
     * persyaratan disalin dari templat layanan, dan seluruh data
     * acuan (termasuk nomor invoice, yang baru terbit setelah LC
     * turun) dilengkapi menyusul pada detail pengajuan. Pembayaran
     * tidak lagi dibuat (Fase 11 — fokus sistem adalah dokumen).
     */
    public function buatPengajuan(): void
    {
        $validated = $this->validate([
            'formNasabahId' => ['required', Rule::exists('users', 'id')],
            'formLayananId' => ['required', Rule::exists('layanans', 'id')],
        ], [
            'formNasabahId.required' => 'Pilih nasabah terlebih dahulu.',
            'formLayananId.required' => 'Pilih layanan terlebih dahulu.',
        ]);

        $layanan = Layanan::findOrFail($validated['formLayananId']);

        $pengajuan = Pengajuan::create([
            'user_id' => $validated['formNasabahId'],
            'layanan_id' => $layanan->id,
            'nomor_pengajuan' => Pengajuan::generateNomorPengajuan(),
            'status' => StatusPengajuan::MenungguVerifikasi,
            'persyaratan_dokumen' => $layanan->persyaratan_dokumen ?? [],
        ]);

        $pengajuan->riwayatStatuses()->create([
            'status_dari' => null,
            'status_ke' => StatusPengajuan::MenungguVerifikasi,
            'catatan' => 'Pengajuan dibuat oleh staf.',
            'changed_by' => auth()->id(),
        ]);

        $this->redirect(route('pengajuans.show', $pengajuan), navigate: true);
    }

    /**
     * Layanan aktif saja untuk pilihan form buat pengajuan — layanan
     * lama yang dinonaktifkan tidak boleh dipilih untuk pengajuan baru
     * (daftar lengkap tetap dipakai dropdown filter riwayat).
     */
    #[Computed]
    public function daftarLayananAktif()
    {
        return Layanan::aktif()->orderBy('nama')->get();
    }

    #[Computed]
    public function daftarLayanan()
    {
        return Layanan::orderBy('nama')->get();
    }

    #[Computed]
    public function pengajuans()
    {
        return Pengajuan::query()
            ->with(['user', 'layanan'])
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('nomor_pengajuan', 'like', '%'.$this->search.'%')
                        ->orWhere('nomor_invoice', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->layananFilter, fn ($query) => $query->where('layanan_id', $this->layananFilter))
            ->latest()
            ->paginate(10);
    }
}; ?>

<section class="w-full">
    <flux:heading level="1" size="xl">Pantau Pengajuan</flux:heading>
    <flux:subheading>Seluruh pengajuan layanan dari nasabah beserta statusnya. Pengajuan baru dibuat oleh staf untuk nasabah.</flux:subheading>

    <flux:separator class="mt-4 mb-6" />


    <form wire:submit="buatPengajuan" class="mb-6 space-y-4 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading level="2">Buat Pengajuan Baru</flux:heading>
        <flux:text size="sm">Pengajuan dibuat untuk nasabah terpilih; data acuan LC dilengkapi pada detail pengajuan ketika LC terbit.</flux:text>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="formNasabahId" label="Nasabah" required>
                <option value="">— Pilih nasabah —</option>
                @foreach ($this->daftarNasabah as $nasabah)
                    <option value="{{ $nasabah->id }}">{{ $nasabah->name }} ({{ $nasabah->email }})</option>
                @endforeach
            </flux:select>
            <flux:select wire:model="formLayananId" label="Layanan" required>
                <option value="">— Pilih layanan —</option>
                @foreach ($this->daftarLayananAktif as $layanan)
                    <option value="{{ $layanan->id }}">{{ $layanan->nama }}</option>
                @endforeach
            </flux:select>
        </div>
        <flux:button variant="primary" type="submit">Buat Pengajuan</flux:button>
    </form>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-center">
        <flux:input wire:model.live="search" type="search" placeholder="Cari nomor pengajuan, invoice, atau nasabah..." class="w-full sm:col-span-2 lg:col-span-1 lg:max-w-sm" />
        <flux:select wire:model.live="statusFilter" class="w-full lg:max-w-52">
            <option value="">Semua Status</option>
            @foreach (StatusPengajuan::cases() as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="layananFilter" class="w-full lg:max-w-64">
            <option value="">Semua Layanan</option>
            @foreach ($this->daftarLayananAktif as $layanan)
                <option value="{{ $layanan->id }}">{{ $layanan->nama }}</option>
            @endforeach
        </flux:select>
    </div>


    {{-- Daftar kartu (mobile) — Fase 8.2 --}}
    <div class="space-y-3 lg:hidden">
        @forelse ($this->pengajuans as $pengajuan)
            <div class="rounded-2xl bg-white p-4 shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-zinc-950/5 dark:bg-zinc-900" wire:key="kartu-pengajuan-{{ $pengajuan->id }}">
                <div class="flex items-start justify-between gap-3">
                    <span class="font-semibold">{{ $pengajuan->nomor_pengajuan }}</span>
                    <flux:badge :color="$pengajuan->status->color()">{{ $pengajuan->status->label() }}</flux:badge>
                </div>
                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Tanggal</dt><dd class="text-right font-medium">{{ $pengajuan->created_at->format('d M Y') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Nasabah</dt><dd class="text-right font-medium">{{ $pengajuan->user->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Layanan</dt><dd class="text-right font-medium">{{ $pengajuan->layanan->nama }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Invoice</dt><dd class="text-right font-medium">{{ $pengajuan->nomor_invoice }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Nominal Acuan</dt><dd class="text-right font-medium">{{ $pengajuan->nominal_acuan !== null ? ($pengajuan->mata_uang_acuan?->value.' '.number_format((float) $pengajuan->nominal_acuan, 2, ',', '.')) : '-' }}</dd></div>
                </dl>
                <flux:button :href="route('pengajuans.show', $pengajuan)" class="mt-4 w-full">Detail</flux:button>
            </div>
        @empty
            <flux:text>Belum ada pengajuan.</flux:text>
        @endforelse

        <div>{{ $this->pengajuans->links() }}</div>
    </div>

    <div class="hidden lg:block">
    <flux:table :paginate="$this->pengajuans">
        <flux:table.columns>
            <flux:table.column>Nomor Pengajuan</flux:table.column>
            <flux:table.column>Tanggal</flux:table.column>
            <flux:table.column>Nasabah</flux:table.column>
            <flux:table.column>Layanan</flux:table.column>
            <flux:table.column>Invoice</flux:table.column>
            <flux:table.column>Nominal</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->pengajuans as $pengajuan)
                <flux:table.row :key="$pengajuan->id">
                    <flux:table.cell class="font-medium">{{ $pengajuan->nomor_pengajuan }}</flux:table.cell>
                    <flux:table.cell>{{ $pengajuan->created_at->format('d M Y') }}</flux:table.cell>
                    <flux:table.cell>{{ $pengajuan->user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $pengajuan->layanan->nama }}</flux:table.cell>
                    <flux:table.cell>{{ $pengajuan->nomor_invoice }}</flux:table.cell>
                    <flux:table.cell>{{ $pengajuan->nominal_acuan !== null ? ($pengajuan->mata_uang_acuan?->value.' '.number_format((float) $pengajuan->nominal_acuan, 2, ',', '.')) : '-' }}</flux:table.cell>
                    <flux:table.cell><flux:badge :color="$pengajuan->status->color()">{{ $pengajuan->status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end">
                            <flux:button size="sm" :href="route('pengajuans.show', $pengajuan)">Detail</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
    </div>
</section>
