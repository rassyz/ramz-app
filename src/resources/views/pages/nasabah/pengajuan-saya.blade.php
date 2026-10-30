<?php

use App\Enums\MataUang;
use App\Enums\StatusPengajuan;
use App\Models\Layanan;
use App\Models\Pengajuan;
use App\Services\NotifikasiService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pengajuan Saya')] class extends Component
{
    use WithPagination;

    // Form buat pengajuan baru
    public ?int $layanan_id = null;

    public string $nomor_invoice = '';

    public string $nominal = '';

    public string $mata_uang = 'USD';

    /**
     * Halaman ini hanya untuk Nasabah.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()->isNasabah(), 403);
        $this->authorize('create', Pengajuan::class);
    }

    #[Computed]
    public function layanansAktif()
    {
        return Layanan::aktif()->orderBy('nama')->get();
    }

    #[Computed]
    public function layananTerpilih(): ?Layanan
    {
        return $this->layanan_id ? Layanan::find($this->layanan_id) : null;
    }

    #[Computed]
    public function pengajuans()
    {
        return auth()->user()->pengajuans()
            ->with(['layanan', 'pembayaran'])
            ->latest()
            ->paginate(10);
    }

    public function buatPengajuan(): void
    {
        $this->authorize('create', Pengajuan::class);

        $validated = $this->validate([
            'layanan_id' => ['required', Rule::exists('layanans', 'id')->where('is_active', true)],
            'nomor_invoice' => ['required', 'string', 'max:100'],
            'nominal' => ['required', 'numeric', 'min:0.01'],
            'mata_uang' => ['required', Rule::enum(MataUang::class)],
        ]);

        $pengajuan = null;

        DB::transaction(function () use ($validated, &$pengajuan) {
            $pengajuan = Pengajuan::create([
                'nomor_pengajuan' => Pengajuan::generateNomorPengajuan(),
                'user_id' => auth()->id(),
                'layanan_id' => $validated['layanan_id'],
                'nomor_invoice' => $validated['nomor_invoice'],
                'status' => StatusPengajuan::MenungguVerifikasi,
            ]);

            $pengajuan->pembayaran()->create([
                'nominal' => $validated['nominal'],
                'mata_uang' => $validated['mata_uang'],
            ]);

            $pengajuan->riwayatStatuses()->create([
                'status_dari' => null,
                'status_ke' => StatusPengajuan::MenungguVerifikasi,
                'catatan' => 'Pengajuan dibuat.',
                'changed_by' => auth()->id(),
            ]);
        });

        app(NotifikasiService::class)->pengajuanDibuat($pengajuan);

        Flux::toast(variant: 'success', text: 'Pengajuan '.$pengajuan->nomor_pengajuan.' berhasil dibuat. Unggah dokumen pendukung akan tersedia pada tahap berikutnya.');

        $this->reset('layanan_id', 'nomor_invoice', 'nominal');
        $this->mata_uang = 'USD';
        $this->resetPage();
    }
}; ?>

<section class="w-full">
    <flux:heading level="1" size="xl">Pengajuan Saya</flux:heading>
    <flux:subheading>Ajukan layanan perdagangan dan pembayaran internasional dan pantau statusnya di sini.</flux:subheading>

    <flux:separator class="mt-4 mb-6" />

    <div class="grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-1">
            <flux:heading level="2">Buat Pengajuan Baru</flux:heading>

            <form wire:submit="buatPengajuan" class="mt-4 space-y-4">
                <flux:select wire:model.live="layanan_id" label="Jenis Layanan" placeholder="Pilih layanan">
                    @foreach ($this->layanansAktif as $layanan)
                        <option value="{{ $layanan->id }}">{{ $layanan->nama }}</option>
                    @endforeach
                </flux:select>

                @if ($this->layananTerpilih)
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <flux:text class="font-medium">Dokumen yang wajib disiapkan:</flux:text>
                        <ul class="mt-2 list-inside list-disc space-y-1">
                            @forelse ($this->layananTerpilih->persyaratan_dokumen ?? [] as $syarat)
                                <li>
                                    <flux:text size="sm">{{ $syarat['nama'] }}@if (!$syarat['wajib']) (opsional)@endif</flux:text>
                                </li>
                            @empty
                                <li><flux:text size="sm">Belum ada persyaratan dokumen untuk layanan ini.</flux:text></li>
                            @endforelse
                        </ul>
                        <flux:text size="sm" class="mt-2 block">Dokumen diunggah setelah pengajuan dibuat — fitur unggah dokumen hadir pada tahap berikutnya.</flux:text>
                    </div>
                @endif

                <flux:input wire:model="nomor_invoice" label="Nomor Invoice" type="text" required placeholder="mis. INV-2026-010" />
                <flux:input wire:model="nominal" label="Nominal Pembayaran" type="number" step="0.01" min="0" required />
                <flux:select wire:model="mata_uang" label="Mata Uang" required>
                    @foreach (MataUang::cases() as $mataUang)
                        <option value="{{ $mataUang->value }}">{{ $mataUang->label() }}</option>
                    @endforeach
                </flux:select>

                <flux:button variant="primary" type="submit" class="w-full">Kirim Pengajuan</flux:button>
            </form>
        </div>

        <div class="lg:col-span-2">
            <flux:heading level="2">Daftar Pengajuan</flux:heading>


            {{-- Daftar kartu (mobile) — Fase 8.2: seluruh field terbaca tanpa geser menyamping --}}
            <div class="mt-4 space-y-3 lg:hidden">
                @forelse ($this->pengajuans as $pengajuan)
                    <div class="rounded-2xl bg-white p-4 shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-zinc-950/5 dark:bg-zinc-900" wire:key="kartu-pengajuan-{{ $pengajuan->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <span class="font-semibold">{{ $pengajuan->nomor_pengajuan }}</span>
                            <flux:badge :color="$pengajuan->status->color()">{{ $pengajuan->status->label() }}</flux:badge>
                        </div>
                        <dl class="mt-3 space-y-1.5 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Tanggal</dt><dd class="text-right font-medium">{{ $pengajuan->created_at->format('d M Y') }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Layanan</dt><dd class="text-right font-medium">{{ $pengajuan->layanan->nama }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Invoice</dt><dd class="text-right font-medium">{{ $pengajuan->nomor_invoice }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-zinc-500 dark:text-zinc-400">Nominal</dt><dd class="text-right font-medium">{{ $pengajuan->pembayaran?->nominalFormatted() ?? '-' }}</dd></div>
                        </dl>
                        <flux:button :href="route('pengajuans.show', $pengajuan)" class="mt-4 w-full">Detail</flux:button>
                    </div>
                @empty
                    <flux:text>Belum ada pengajuan.</flux:text>
                @endforelse

                <div>{{ $this->pengajuans->links() }}</div>
            </div>

            <div class="mt-4 hidden lg:block">
            <flux:table :paginate="$this->pengajuans" >
                <flux:table.columns>
                    <flux:table.column>Nomor Pengajuan</flux:table.column>
                    <flux:table.column>Tanggal</flux:table.column>
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
                            <flux:table.cell>{{ $pengajuan->layanan->nama }}</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->nomor_invoice }}</flux:table.cell>
                            <flux:table.cell>{{ $pengajuan->pembayaran?->nominalFormatted() ?? '-' }}</flux:table.cell>
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
        </div>
    </div>
</section>
