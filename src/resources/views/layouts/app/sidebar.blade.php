<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#f1f1f8] dark:bg-zinc-950">
        <flux:sidebar sticky collapsible="mobile" class="border-e-0 bg-white shadow-[1px_0_24px_rgb(0,0,0,0.04)] dark:bg-zinc-900">
            <flux:sidebar.header>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-2 py-1" wire:navigate>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#4353ff]">
                        <x-app-logo-icon class="size-6 fill-current text-white" />
                    </span>
                    <span class="grid leading-tight">
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ config('app.name', 'Laravel') }}</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ auth()->user()->role->label() }}</span>
                    </span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group heading="Utama" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="bell" :href="route('notifikasis.index')" :current="request()->routeIs('notifikasis.*')" wire:navigate>
                        Notifikasi
                        @php($belumDibaca = auth()->user()->notifikasis()->whereNull('dibaca_at')->count())
                        @if ($belumDibaca > 0)
                            <flux:badge size="sm" color="red">{{ $belumDibaca }}</flux:badge>
                        @endif
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group heading="Monitoring" class="grid">
                    @if (auth()->user()->isNasabah())
                        <flux:sidebar.item icon="inbox-arrow-down" :href="route('nasabah.pengajuan')" :current="request()->routeIs('nasabah.pengajuan')" wire:navigate>
                            {{ __('Pengajuan Saya') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->isAdmin() || auth()->user()->isOperator())
                        <flux:sidebar.item icon="document-text" :href="route('pengajuans.index')" :current="request()->routeIs('pengajuans.*')" wire:navigate>
                            {{ __('Pengajuan') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                @if (auth()->user()->isAdmin())
                    <flux:sidebar.group heading="Administrasi" class="grid">
                        <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>
                            {{ __('Kelola Pengguna') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.layanans')" :current="request()->routeIs('admin.layanans')" wire:navigate>
                            {{ __('Master Layanan') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="shield-check" :href="route('admin.aturan-validasi')" :current="request()->routeIs('admin.aturan-validasi')" wire:navigate>
                            {{ __('Aturan Validasi') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar" :href="route('admin.laporan')" :current="request()->routeIs('admin.laporan')" wire:navigate>
                            Laporan
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.log-aktivitas')" :current="request()->routeIs('admin.log-aktivitas')" wire:navigate>
                            Log Aktivitas
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- Kartu fungsional (Fase 8): angka hidup sesuai peran, menggantikan kartu promosi pada referensi desain. --}}
            <div class="mx-3 mb-3 rounded-2xl bg-gradient-to-br from-[#4353ff] to-[#6d7cff] p-4 text-white">
                @if (auth()->user()->isNasabah())
                    @php($perluPerbaikan = auth()->user()->pengajuans()->where('status', \App\Enums\StatusPengajuan::PerluPerbaikan)->count())
                    <p class="text-xs uppercase tracking-wide text-white/70">Perlu Perbaikan</p>
                    <p class="mt-1 text-2xl font-bold">{{ $perluPerbaikan }}</p>
                    <p class="mt-1 text-xs text-white/80">Pengajuan Anda yang menunggu diperbaiki.</p>
                    <a href="{{ route('nasabah.pengajuan') }}" class="mt-3 inline-block rounded-lg bg-white/15 px-3 py-1.5 text-xs font-medium hover:bg-white/25" wire:navigate>Lihat Pengajuan</a>
                @else
                    @php($antrean = \App\Models\Pengajuan::where('status', \App\Enums\StatusPengajuan::MenungguVerifikasi)->count())
                    <p class="text-xs uppercase tracking-wide text-white/70">Antrean Verifikasi</p>
                    <p class="mt-1 text-2xl font-bold">{{ $antrean }}</p>
                    <p class="mt-1 text-xs text-white/80">Pengajuan menunggu verifikasi operator.</p>
                    <a href="{{ route('pengajuans.index') }}" class="mt-3 inline-block rounded-lg bg-white/15 px-3 py-1.5 text-xs font-medium hover:bg-white/25" wire:navigate>Buka Antrean</a>
                @endif
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="border-b border-zinc-200 bg-white lg:hidden dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <span class="ml-1 font-semibold text-zinc-900 lg:hidden dark:text-white">{{ config('app.name', 'Laravel') }}</span>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
