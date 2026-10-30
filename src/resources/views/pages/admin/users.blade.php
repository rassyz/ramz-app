<?php

use App\Enums\UserRole;
use App\Models\User;
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

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nomor_hp' => $validated['nomorHp'] ?: null,
            'password' => $validated['password'],
            'role' => UserRole::from($validated['role']),
            // Akun yang dibuat Admin langsung terverifikasi.
            'email_verified_at' => now(),
        ]);

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

        $this->cancelEdit();

        Flux::toast(variant: 'success', text: 'Pengguna berhasil diperbarui.');
    }

    public function deleteUser(int $id): void
    {
        $user = User::findOrFail($id);

        $this->authorize('delete', $user);

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
    <flux:heading level="1" size="xl">Kelola Pengguna</flux:heading>
    <flux:subheading>Kelola akun pengguna dan tetapkan hak akses berdasarkan peran: Admin, Operator, atau Nasabah.</flux:subheading>

    <flux:separator class="mt-4 mb-6" />

    <div class="grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-1">
            <flux:heading level="2">Tambah Pengguna</flux:heading>

            <form wire:submit="createUser" class="mt-4 space-y-4">
                <flux:input wire:model="name" label="Nama" type="text" required autocomplete="name" />
                <flux:input wire:model="email" label="Email" type="email" required autocomplete="email" />
                <flux:input wire:model="nomorHp" label="Nomor HP (WhatsApp)" type="text" placeholder="08xxxxxxxxxx" />
                <flux:input wire:model="password" label="Password" type="password" required autocomplete="new-password" viewable />
                <flux:select wire:model="role" label="Peran">
                    @foreach (UserRole::cases() as $roleOption)
                        <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                    @endforeach
                </flux:select>

                <flux:button variant="primary" type="submit" class="w-full">Tambah Pengguna</flux:button>
            </form>
        </div>

        <div class="lg:col-span-2">
            <div class="flex items-center justify-between gap-4">
                <flux:heading level="2">Daftar Pengguna</flux:heading>
                <flux:input wire:model.live="search" type="search" placeholder="Cari nama atau email..." class="max-w-64" />
            </div>

            <flux:table :paginate="$this->users" class="mt-4">
                <flux:table.columns>
                    <flux:table.column>Nama</flux:table.column>
                    <flux:table.column>Email</flux:table.column>
                    <flux:table.column>Nomor HP</flux:table.column>
                    <flux:table.column>Peran</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell>{{ $user->name }}</flux:table.cell>
                            <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>{{ $user->nomor_hp ?? '-' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge>{{ $user->role->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" wire:click="startEdit({{ $user->id }})">Ubah</flux:button>
                                    @if (auth()->user()->can('delete', $user))
                                        <flux:button size="sm" variant="danger" wire:click="deleteUser({{ $user->id }})" wire:confirm="Hapus pengguna {{ $user->name }}? Tindakan ini tidak dapat dibatalkan.">Hapus</flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            @if ($editingId)
                <div class="mt-8 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading level="3">Ubah Pengguna</flux:heading>

                    <form wire:submit="updateUser" class="mt-4 grid gap-4 sm:grid-cols-3">
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
        </div>
    </div>
</section>
