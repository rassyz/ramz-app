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
     * Layanan aktif saja — dipakai form buat pengajuan dan dropdown
     * filter (pilihan user 25 Nov 2026): layanan lama yang dinonaktifkan
     * tidak lagi ditawarkan di keduanya; pengajuannya tetap tampil
     * pada pilihan Semua Layanan.
     */
    #[Computed]
    public function daftarLayananAktif()
    {
        return Layanan::aktif()->orderBy('nama')->get();
    }

    /**
     * Jumlah pengajuan per status untuk chip filter (dihitung dari
     * seluruh pengajuan; hanya mengikuti filter layanan).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function jumlahPerStatus()
    {
        $perStatus = Pengajuan::query()
            ->when($this->layananFilter, fn ($query) => $query->where('layanan_id', $this->layananFilter))
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $hasil = ['semua' => (int) $perStatus->sum()];

        foreach (StatusPengajuan::cases() as $status) {
            $hasil[$status->value] = (int) ($perStatus[$status->value] ?? 0);
        }

        return $hasil;
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
    {{-- Baris judul + tombol ikon (selaras mockup) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Pantau Pengajuan</h1>
            <p class="mt-1 text-[13px] text-mist dark:text-zinc-400">Semua pengajuan nasabah — buat, pantau, dan tindak lanjuti.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('notifikasis.index') }}" wire:navigate class="relative hidden size-10 items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Notifikasi" title="Notifikasi">
                <flux:icon name="bell" class="size-5" />
                @php
                    $belumDibacaPantau = auth()->user()->notifikasis()->whereNull('dibaca_at')->count();
                @endphp
                @if ($belumDibacaPantau > 0)
                    <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $belumDibacaPantau }}</span>
                @endif
            </a>
            <button type="button" x-data x-on:click="$flux.dark = !$flux.dark" class="hidden size-10 cursor-pointer items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Ganti tema gelap atau terang" title="Mode gelap / terang">
                <flux:icon.moon class="size-5 dark:hidden" />
                <flux:icon.sun class="hidden size-5 dark:block" />
            </button>
        </div>
    </div>

    <div class="bento mt-5">
        {{-- Buat Pengajuan Baru (Fase 11: pengajuan dibuat staf) --}}
        <section class="tile md:col-span-2 xl:col-span-12">
            <div class="mb-4 flex items-center justify-between gap-2.5">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Buat Pengajuan Baru</h2>
                <span class="pill pill-indigo">Oleh Staf</span>
            </div>
            <form wire:submit="buatPengajuan">
                <div class="grid gap-4 lg:grid-cols-3 lg:items-end">
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
                    <button type="submit" class="btn btn-primary w-full">Buat Pengajuan</button>
                </div>
            </form>
            <p class="mt-3 text-[12.5px] text-mist dark:text-zinc-400">Nomor pengajuan terbit otomatis. Data LC, berkas LC, dan penyesuaian persyaratan diisi kemudian di halaman detail — nomor invoice menyusul setelah barang diserahkan penjual.</p>
        </section>

        {{-- Daftar pengajuan: chip status + pencarian + tabel responsif --}}
        <section class="tile md:col-span-2 xl:col-span-12">
            @php
                $pillStatus = [
                    'menunggu_verifikasi' => 'pill-amber',
                    'perlu_perbaikan' => 'pill-red',
                    'terverifikasi' => 'pill-indigo',
                    'diproses' => 'pill-blue',
                    'selesai' => 'pill-green',
                    'dibatalkan' => 'pill-zinc',
                ];
                $labelChip = [
                    'menunggu_verifikasi' => 'Menunggu',
                    'perlu_perbaikan' => 'Perlu Perbaikan',
                    'terverifikasi' => 'Terverifikasi',
                    'diproses' => 'Diproses',
                    'selesai' => 'Selesai',
                    'dibatalkan' => 'Dibatalkan',
                ];
            @endphp
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex flex-nowrap items-center gap-1.5 overflow-x-auto scrollbar-none [&::-webkit-scrollbar]:hidden">
                    <button type="button" wire:click="$set('statusFilter', '')" class="chip {{ $statusFilter === '' ? 'chip-aktif' : '' }}">Semua ({{ $this->jumlahPerStatus['semua'] }})</button>
                    @foreach (StatusPengajuan::cases() as $status)
                        <button type="button" wire:click="$set('statusFilter', '{{ $status->value }}')" class="chip {{ $statusFilter === $status->value ? 'chip-aktif' : '' }}">{{ $labelChip[$status->value] }} ({{ $this->jumlahPerStatus[$status->value] }})</button>
                    @endforeach
                </div>
                <div class="flex min-w-57.5 flex-1 flex-wrap items-center gap-2 sm:flex-nowrap">
                    <flux:input wire:model.live="search" type="search" placeholder="Cari nomor pengajuan, invoice, atau nasabah..." class="w-full" />
                    <flux:select wire:model.live="layananFilter" class="w-full sm:w-56">
                        <option value="">Semua Layanan</option>
                        @foreach ($this->daftarLayananAktif as $layanan)
                            <option value="{{ $layanan->id }}">{{ $layanan->nama }}</option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
            <div class="-mx-4.25 overflow-x-auto px-4.25 sm:-mx-5.5 sm:px-5.5">
                <table class="tabel-kartu w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">
                            <th class="pb-2.5">Nomor Pengajuan</th>
                            <th class="pb-2.5">Nasabah</th>
                            <th class="pb-2.5">Layanan</th>
                            <th class="pb-2.5">Nomor LC</th>
                            <th class="pb-2.5">Status</th>
                            <th class="pb-2.5">Dibuat</th>
                            <th class="pb-2.5"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->pengajuans as $pengajuan)
                            <tr class="group relative cursor-pointer border-t border-line transition-colors hover:bg-soft dark:border-night-line dark:hover:bg-night-soft" wire:key="pengajuan-{{ $pengajuan->id }}">
                                <td class="py-3.25 pr-3" data-label="Nomor Pengajuan">
                                    <a href="{{ route('pengajuans.show', $pengajuan) }}" wire:navigate class="font-mono text-[13px] font-bold whitespace-nowrap text-zinc-900 after:absolute after:inset-0 after:content-[''] hover:text-primary dark:text-white">{{ $pengajuan->nomor_pengajuan }}</a>
                                </td>
                                <td class="py-3.25 pr-3 text-[13.5px] text-zinc-600 dark:text-zinc-300" data-label="Nasabah">{{ $pengajuan->user->name }}</td>
                                <td class="py-3.25 pr-3 text-[13.5px] whitespace-nowrap text-zinc-600 dark:text-zinc-300" data-label="Layanan">{{ $pengajuan->layanan->nama }}</td>
                                <td class="py-3.25 pr-3 font-mono text-[13px] whitespace-nowrap text-zinc-600 dark:text-zinc-300" data-label="Nomor LC">{{ $pengajuan->nomor_lc ?? '—' }}</td>
                                <td class="py-3.25 pr-3" data-label="Status"><span class="pill {{ $pillStatus[$pengajuan->status->value] ?? 'pill-zinc' }}">{{ $pengajuan->status->label() }}</span></td>
                                <td class="py-3.25 pr-3 text-[13.5px] whitespace-nowrap text-zinc-500 dark:text-zinc-400" data-label="Dibuat" title="{{ $pengajuan->created_at->format('d M Y H:i') }}">{{ $pengajuan->created_at->diffForHumans() }}</td>
                                <td class="aksi py-3.25" data-label="">
                                    <span class="text-lg text-zinc-400 transition-colors group-hover:text-primary" aria-hidden="true">→</span>
                                </td>
                            </tr>
                        @empty
                            <tr class="border-t border-line dark:border-night-line">
                                <td class="py-6 text-center text-[13.5px] text-zinc-500 dark:text-zinc-400" colspan="7">Belum ada pengajuan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($this->pengajuans->total() > 0)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-mist dark:text-zinc-400">
                    <span>Menampilkan {{ $this->pengajuans->firstItem() }}–{{ $this->pengajuans->lastItem() }} dari {{ $this->pengajuans->total() }} pengajuan</span>
                    @if ($this->pengajuans->hasPages())
                        @php
                            $halamanIni = $this->pengajuans->currentPage();
                            $halamanAkhir = $this->pengajuans->lastPage();
                            $daftarHalaman = range(max(1, $halamanIni - 2), min($halamanAkhir, $halamanIni + 2));
                        @endphp
                        <div class="flex items-center gap-1.5">
                            <button type="button" wire:click="previousPage" class="chip {{ $this->pengajuans->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman sebelumnya">‹</button>
                            @foreach ($daftarHalaman as $halaman)
                                <button type="button" wire:click="gotoPage({{ $halaman }})" class="chip {{ $halaman === $halamanIni ? 'chip-aktif' : '' }}">{{ $halaman }}</button>
                            @endforeach
                            <button type="button" wire:click="nextPage" class="chip {{ $halamanIni === $halamanAkhir ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman berikutnya">›</button>
                        </div>
                    @endif
                </div>
            @endif
        </section>
    </div>
</section>
