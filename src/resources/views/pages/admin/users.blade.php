<?php

use App\Enums\UserRole;
use App\Models\LogAktivitas;
use App\Models\User;
use App\Services\LogAktivitasService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kelola Pengguna')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $modalKonfirmasi = false;

    public ?int $targetKonfirmasiId = null;

    // Form tambah pengguna
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $nomorHp = '';

    public string $role = 'nasabah';

    // Form ubah pengguna
    public ?int $editingId = null;

    public string $editName = '';

    public string $editEmail = '';

    public string $editRole = 'nasabah';

    public string $editNomorHp = '';

    /**
     * Halaman ini hanya untuk Admin (KF-02). Route juga dilindungi
     * middleware role:admin; authorize di sini adalah lapis kedua.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function createUser(): void
    {
        $this->authorize('create', User::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'nomorHp' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $penggunaBaru = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nomor_hp' => $validated['nomorHp'] ?: null,
            'password' => $validated['password'],
            'role' => UserRole::from($validated['role']),
            // Akun yang dibuat Admin langsung terverifikasi.
            'email_verified_at' => now(),
        ]);

        app(LogAktivitasService::class)->catat(
            LogAktivitas::PENGGUNA_DIBUAT,
            'Admin '.auth()->user()->name." membuat pengguna {$penggunaBaru->name} ({$penggunaBaru->email}) dengan peran {$penggunaBaru->role->label()}.",
            auth()->user()
        );

        $this->reset('name', 'email', 'password', 'role', 'nomorHp');
        $this->role = 'nasabah';

        Flux::toast(variant: 'success', text: 'Pengguna berhasil ditambahkan.');
    }

    public function startEdit(int $id): void
    {
        $user = User::findOrFail($id);

        $this->authorize('update', $user);

        $this->editingId = $user->id;
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        $this->editRole = $user->role->value;
        $this->editNomorHp = $user->nomor_hp ?? '';
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editName', 'editEmail', 'editRole', 'editNomorHp');
        $this->editRole = 'nasabah';
    }

    public function updateUser(): void
    {
        $user = User::findOrFail($this->editingId);

        $this->authorize('update', $user);

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'editNomorHp' => ['nullable', 'string', 'max:20'],
            'editRole' => ['required', Rule::enum(UserRole::class)],
        ]);

        // Admin tidak dapat menurunkan perannya sendiri dari halaman ini,
        // agar tidak terkunci dari pengelolaan pengguna.
        if ($user->id === auth()->id()
            && $user->isAdmin()
            && $validated['editRole'] !== UserRole::Admin->value
            && User::where('role', UserRole::Admin)->count() <= 1) {
            Flux::toast(variant: 'danger', text: 'Peran Anda tidak dapat diubah karena Anda adalah satu-satunya Admin.');

            return;
        }

        $user->update([
            'name' => $validated['editName'],
            'email' => $validated['editEmail'],
            'nomor_hp' => $validated['editNomorHp'] ?: null,
            'role' => UserRole::from($validated['editRole']),
        ]);

        app(LogAktivitasService::class)->catat(
            LogAktivitas::PENGGUNA_DIUBAH,
            'Admin '.auth()->user()->name." mengubah pengguna {$user->name} ({$user->email}).",
            auth()->user()
        );

        $this->cancelEdit();

        Flux::toast(variant: 'success', text: 'Pengguna berhasil diperbarui.');
    }

    /**
     * Modal konfirmasi Flux untuk penghapusan pengguna: method
     * deleteUser() aslinya baru berjalan setelah admin menyetujui
     * pada modal.
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
            $this->deleteUser($id);
        }
    }

    /**
     * @return array{judul: string, isi: string, tombol: string}|null
     */
    #[Computed]
    public function detailKonfirmasi(): ?array
    {
        $user = $this->targetKonfirmasiId !== null ? User::find($this->targetKonfirmasiId) : null;

        if ($user === null) {
            return null;
        }

        return [
            'judul' => 'Hapus Pengguna Ini?',
            'isi' => 'Pengguna '.$user->name.' ('.$user->email.') akan dihapus permanen dan kehilangan akses ke aplikasi. Tindakan ini tidak dapat dibatalkan.',
            'tombol' => 'Ya, Hapus Pengguna',
        ];
    }

    public function deleteUser(int $id): void
    {
        $user = User::findOrFail($id);

        $this->authorize('delete', $user);

        app(LogAktivitasService::class)->catat(
            LogAktivitas::PENGGUNA_DIHAPUS,
            'Admin '.auth()->user()->name." menghapus pengguna {$user->name} ({$user->email}).",
            auth()->user()
        );

        $user->delete();

        Flux::toast(variant: 'success', text: 'Pengguna berhasil dihapus.');
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(10);
    }
}; ?>
<section class="w-full">
    @php
        $pillPeran = [
            'admin' => 'pill-violet',
            'operator' => 'pill-blue',
            'nasabah' => 'pill-indigo',
        ];
    @endphp

    {{-- Baris judul + tombol ikon (selaras mockup) --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">Kelola Pengguna</h1>
            <p class="mt-1 text-[13px] text-mist dark:text-zinc-400">Kelola akun pengguna dan tetapkan hak akses berdasarkan peran: Admin, Operator, atau Nasabah.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('notifikasis.index') }}" wire:navigate class="relative hidden size-10 items-center justify-center rounded-xl border border-line bg-white text-zinc-500 hover:text-primary lg:flex dark:border-night-line dark:bg-night-tile dark:text-zinc-400" aria-label="Notifikasi" title="Notifikasi">
                <flux:icon name="bell" class="size-5" />
                @php
                    $belumDibacaPengguna = auth()->user()->notifikasis()->whereNull('dibaca_at')->count();
                @endphp
                @if ($belumDibacaPengguna > 0)
                    <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $belumDibacaPengguna }}</span>
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
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Tambah Pengguna</h2>
            </div>
            <form wire:submit="createUser" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input wire:model="name" label="Nama" type="text" required autocomplete="name" />
                <flux:input wire:model="email" label="Email" type="email" required autocomplete="email" />
                <flux:input wire:model="nomorHp" label="Nomor HP (WhatsApp)" type="text" placeholder="08xxxxxxxxxx" />
                <flux:input wire:model="password" label="Password" type="password" required autocomplete="new-password" viewable />
                <flux:select wire:model="role" label="Peran">
                    @foreach (UserRole::cases() as $roleOption)
                        <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                    @endforeach
                </flux:select>
                <div class="flex items-end">
                    <flux:button variant="primary" type="submit" class="w-full">Tambah Pengguna</flux:button>
                </div>
            </form>
            <p class="mt-3 text-[12.5px] leading-normal text-mist dark:text-zinc-400">Nomor HP dipakai untuk notifikasi WhatsApp. Email dan kata sandi awal disampaikan Admin kepada pengguna — akun dibuatkan oleh Admin, bukan mendaftar sendiri.</p>
        </section>

        <section class="tile md:col-span-2 xl:col-span-12">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">Daftar Pengguna</h2>
                <div class="flex items-center gap-3">
                    <span class="text-[12.5px] text-mist dark:text-zinc-400">{{ $this->users->total() }} akun</span>
                    <flux:input wire:model.live="search" type="search" placeholder="Cari nama atau email..." class="w-full sm:w-60" />
                </div>
            </div>
            <div class="-mx-4.25 overflow-x-auto px-4.25 sm:-mx-5.5 sm:px-5.5">
                <table class="tabel-kartu w-full text-sm">
                    <thead>
                        <tr class="border-b border-line text-left dark:border-night-line">
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Nama</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Email</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Nomor HP</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Peran</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Terdaftar</th>
                            <th class="pb-2.5 text-[11px] font-bold tracking-[0.07em] text-zinc-400 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->users as $user)
                            <tr class="border-b border-line last:border-b-0 dark:border-night-line" wire:key="pengguna-{{ $user->id }}">
                                <td class="py-3.25 pr-3 text-[13.5px] font-semibold text-zinc-900 dark:text-white" data-label="Nama">{{ $user->name }}</td>
                                <td class="py-3.25 pr-3 text-[13.5px] break-all text-zinc-600 dark:text-zinc-300" data-label="Email">{{ $user->email }}</td>
                                <td class="py-3.25 pr-3 text-[13.5px] whitespace-nowrap text-zinc-600 dark:text-zinc-300" data-label="Nomor HP">{{ $user->nomor_hp ?? '-' }}</td>
                                <td class="py-3.25 pr-3" data-label="Peran"><span class="pill {{ $pillPeran[$user->role->value] ?? 'pill-zinc' }}">{{ $user->role->label() }}</span></td>
                                <td class="py-3.25 pr-3 text-[13.5px] whitespace-nowrap text-zinc-500 dark:text-zinc-400" data-label="Terdaftar">{{ $user->created_at->format('d M Y') }}</td>
                                <td class="aksi py-3.25" data-label="Aksi">
                                    <span class="flex gap-2">
                                        <button type="button" class="btn btn-small" wire:click="startEdit({{ $user->id }})">Ubah</button>
                                        @if (auth()->user()->can('delete', $user))
                                            <button type="button" class="btn btn-danger btn-small" wire:click="mintaKonfirmasiHapus({{ $user->id }})">Hapus</button>
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($this->users->total() > 0)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-mist dark:text-zinc-400">
                    <span>Menampilkan {{ $this->users->firstItem() }}–{{ $this->users->lastItem() }} dari {{ $this->users->total() }} pengguna</span>
                    @if ($this->users->hasPages())
                        @php
                            $halamanIni = $this->users->currentPage();
                            $halamanAkhir = $this->users->lastPage();
                            $daftarHalaman = range(max(1, $halamanIni - 2), min($halamanAkhir, $halamanIni + 2));
                        @endphp
                        <div class="flex items-center gap-1.5">
                            <button type="button" wire:click="previousPage" class="chip {{ $this->users->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman sebelumnya">‹</button>
                            @foreach ($daftarHalaman as $halaman)
                                <button type="button" wire:click="gotoPage({{ $halaman }})" class="chip {{ $halaman === $halamanIni ? 'chip-aktif' : '' }}">{{ $halaman }}</button>
                            @endforeach
                            <button type="button" wire:click="nextPage" class="chip {{ $halamanIni === $halamanAkhir ? 'pointer-events-none opacity-40' : '' }}" aria-label="Halaman berikutnya">›</button>
                        </div>
                    @endif
                </div>
            @endif

            @if ($editingId)
                <div class="mt-4.5 rounded-[18px] border-[1.5px] border-dashed border-line p-4.5 dark:border-night-line">
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-white">Ubah Pengguna</h3>
                    <form wire:submit="updateUser" class="mt-3.5 grid gap-4 sm:grid-cols-3">
                        <flux:input wire:model="editName" label="Nama" type="text" required />
                        <flux:input wire:model="editEmail" label="Email" type="email" required />
                        <flux:input wire:model="editNomorHp" label="Nomor HP (WhatsApp)" type="text" placeholder="08xxxxxxxxxx" />
                        <flux:select wire:model="editRole" label="Peran">
                            @foreach (UserRole::cases() as $roleOption)
                                <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                            @endforeach
                        </flux:select>
                        <div class="flex gap-2 sm:col-span-3">
                            <flux:button variant="primary" type="submit">Simpan Perubahan</flux:button>
                            <flux:button wire:click="cancelEdit">Batal</flux:button>
                        </div>
                    </form>
                </div>
            @endif
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
