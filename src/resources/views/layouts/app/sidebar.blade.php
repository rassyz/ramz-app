<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-lavender dark:bg-zinc-950" x-data="{ menuTerbuka: false }">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-line bg-white dark:border-night-line dark:bg-night">
            <flux:sidebar.header>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-2.5 pt-1 pb-4.5" wire:navigate>
                    <span class="grid size-9 shrink-0 place-items-center rounded-[11px] bg-primary text-[17px] font-extrabold text-white">{{ strtoupper(substr(config('app.name', 'R'), 0, 1)) }}</span>
                    <span class="grid leading-tight">
                        <span class="text-base font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ config('app.name', 'Laravel') }}</span>
                        <span class="text-[11px] text-mist dark:text-zinc-400">Monitoring Layanan Perdagangan</span>
                    </span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @php($kelasLabel = 'px-2.5 pt-3.5 pb-1.5 text-[10.5px] font-bold uppercase tracking-[0.08em] text-mist dark:text-zinc-500')
            @php($kelasItem = 'flex items-center gap-2.5 rounded-xl px-2.5 py-2.25 text-[13.5px] ')
            @php($kelasItemAktif = 'bg-primary-tint font-semibold text-primary dark:bg-primary/25 dark:text-indigo-200')
            @php($kelasItemNormal = 'font-medium text-zinc-600 hover:bg-soft hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-night-soft dark:hover:text-white')

            <flux:sidebar.nav class="px-3 py-2">
                <p class="{{ $kelasLabel }}">Utama</p>
                <div class="flex flex-col gap-0.5">
                    <a href="{{ route('dashboard') }}" wire:navigate class="{{ $kelasItem }}{{ request()->routeIs('dashboard') ? $kelasItemAktif : $kelasItemNormal }}">
                        <flux:icon.squares-2x2 class="size-4.25 shrink-0" />
                        Dashboard
                    </a>
                    @if (auth()->user()->isNasabah())
                        @php($perluPerbaikanSidebar = auth()->user()->pengajuans()->where('status', \App\Enums\StatusPengajuan::PerluPerbaikan)->count())
                        <a href="{{ route('nasabah.pengajuan') }}" wire:navigate class="{{ $kelasItem }}{{ request()->routeIs('nasabah.pengajuan') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.inbox-arrow-down class="size-4.25 shrink-0" />
                            Pengajuan Saya
                            @if ($perluPerbaikanSidebar > 0)
                                <span class="ml-auto flex items-center justify-center rounded-full bg-red-600 px-1.5 py-px text-[10px] font-bold text-white">{{ $perluPerbaikanSidebar }}</span>
                            @endif
                        </a>
                    @else
                        @php($antreanSidebar = \App\Models\Pengajuan::where('status', \App\Enums\StatusPengajuan::MenungguVerifikasi)->count())
                        <a href="{{ route('pengajuans.index') }}" wire:navigate class="{{ $kelasItem }}{{ request()->routeIs('pengajuans.*') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.document-text class="size-4.25 shrink-0" />
                            Pantau Pengajuan
                            @if ($antreanSidebar > 0)
                                <span class="ml-auto flex items-center justify-center rounded-full bg-red-600 px-1.5 py-px text-[10px] font-bold text-white">{{ $antreanSidebar }}</span>
                            @endif
                        </a>
                    @endif
                    @php($belumDibacaSidebar = auth()->user()->notifikasis()->whereNull('dibaca_at')->count())
                    <a href="{{ route('notifikasis.index') }}" wire:navigate class="{{ $kelasItem }}{{ request()->routeIs('notifikasis.*') ? $kelasItemAktif : $kelasItemNormal }}">
                        <flux:icon.bell class="size-4.25 shrink-0" />
                        Notifikasi
                        @if ($belumDibacaSidebar > 0)
                            <span class="ml-auto flex items-center justify-center rounded-full bg-red-600 px-1.5 py-px text-[10px] font-bold text-white">{{ $belumDibacaSidebar }}</span>
                        @endif
                    </a>
                </div>

                @if (auth()->user()->isAdmin())
                    <p class="{{ $kelasLabel }}">Administrasi</p>
                    <div class="flex flex-col gap-0.5">
                        <a href="{{ route('admin.users') }}" wire:navigate class="{{ $kelasItem }}{{ request()->routeIs('admin.users') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.users class="size-4.25 shrink-0" />
                            Kelola Pengguna
                        </a>
                        <a href="{{ route('admin.layanans') }}" wire:navigate class="{{ $kelasItem }}{{ request()->routeIs('admin.layanans') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.briefcase class="size-4.25 shrink-0" />
                            Master Layanan
                        </a>
                        <a href="{{ route('admin.aturan-validasi') }}" wire:navigate class="{{ $kelasItem }}{{ request()->routeIs('admin.aturan-validasi') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.clipboard-document-check class="size-4.25 shrink-0" />
                            Aturan Validasi
                        </a>
                        <a href="{{ route('admin.laporan') }}" wire:navigate class="{{ $kelasItem }}{{ request()->routeIs('admin.laporan') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.chart-bar class="size-4.25 shrink-0" />
                            Laporan
                        </a>
                        <a href="{{ route('admin.log-aktivitas') }}" wire:navigate class="{{ $kelasItem }}{{ request()->routeIs('admin.log-aktivitas') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.clock class="size-4.25 shrink-0" />
                            Log Aktivitas
                        </a>
                    </div>
                @endif

                <p class="{{ $kelasLabel }}">Akun</p>
                <div class="flex flex-col gap-0.5">
                    <a href="{{ route('profile.edit') }}" wire:navigate class="{{ $kelasItem }}{{ request()->routeIs('profile.edit') ? $kelasItemAktif : $kelasItemNormal }}">
                        <flux:icon.cog-6-tooth class="size-4.25 shrink-0" />
                        Pengaturan Akun
                    </a>
                </div>
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- Kartu pengguna mengikuti mockup: avatar gradien, nama, peran, tombol keluar. --}}
            <div class="mx-3 mb-3 flex items-center gap-2.5 rounded-[14px] bg-soft p-2.5 dark:bg-night-soft">
                <span class="grid size-8.5 shrink-0 place-items-center rounded-full bg-linear-to-br from-[#4353ff] to-[#7c3aed] text-xs font-bold text-white">{{ auth()->user()->initials() }}</span>
                <span class="grid min-w-0 flex-1 leading-tight">
                    <span class="truncate text-[13px] font-semibold text-zinc-900 dark:text-white">{{ auth()->user()->name }}</span>
                    <span class="truncate text-[11px] text-mist dark:text-zinc-400">{{ auth()->user()->role->label() }}</span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex size-8 cursor-pointer items-center justify-center rounded-lg text-zinc-500 hover:bg-white hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-night-tile dark:hover:text-white" title="Log out" aria-label="Log out" data-test="logout-button">
                        <flux:icon.arrow-right-start-on-rectangle class="size-4.25" />
                    </button>
                </form>
            </div>
        </flux:sidebar>

        <!-- Mobile Header (Fase 13): logo, saklar tema, lonceng. Menu akun pindah ke panel Menu di navigasi bawah. -->
        <flux:header class="border-b border-zinc-200 bg-white lg:hidden dark:border-zinc-800 dark:bg-zinc-900">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5" wire:navigate>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] bg-[#4353ff]">
                    <x-app-logo-icon class="size-5 fill-current text-white" />
                </span>
                <span class="font-semibold text-zinc-900 dark:text-white">{{ config('app.name', 'Laravel') }}</span>
            </a>

            <flux:spacer />

            <button
                type="button"
                x-data
                x-on:click="$flux.dark = !$flux.dark"
                class="flex size-10 cursor-pointer items-center justify-center rounded-xl text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                aria-label="Ganti tema gelap atau terang"
            >
                <flux:icon.moon class="size-5 dark:hidden" />
                <flux:icon.sun class="hidden size-5 dark:block" />
            </button>

            <a
                href="{{ route('notifikasis.index') }}"
                class="relative flex size-10 items-center justify-center rounded-xl text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                wire:navigate
                aria-label="Notifikasi"
            >
                <flux:icon.bell class="size-5" />
                @php($belumDibacaMobile = auth()->user()->notifikasis()->whereNull('dibaca_at')->count())
                @if ($belumDibacaMobile > 0)
                    <span class="absolute top-1.5 right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $belumDibacaMobile }}</span>
                @endif
            </a>
        </flux:header>


        {{ $slot }}

        {{-- Navigasi bawah mobile (Fase 13): 4 item, menggantikan laci sidebar di layar kecil. --}}
        <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden dark:border-zinc-800 dark:bg-zinc-900/95">
            <div class="grid grid-cols-4">
                <a
                    href="{{ route('dashboard') }}"
                    wire:navigate
                    class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ request()->routeIs('dashboard') ? 'text-[#4353ff] dark:text-indigo-300' : 'text-zinc-500 dark:text-zinc-400' }}"
                >
                    <flux:icon.home class="size-6" />
                    Dashboard
                </a>

                @if (auth()->user()->isNasabah())
                    <a
                        href="{{ route('nasabah.pengajuan') }}"
                        wire:navigate
                        class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ request()->routeIs('nasabah.pengajuan') ? 'text-[#4353ff] dark:text-indigo-300' : 'text-zinc-500 dark:text-zinc-400' }}"
                    >
                        <flux:icon.inbox-arrow-down class="size-6" />
                        Pengajuan
                    </a>
                @else
                    <a
                        href="{{ route('pengajuans.index') }}"
                        wire:navigate
                        class="flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ request()->routeIs('pengajuans.*') ? 'text-[#4353ff] dark:text-indigo-300' : 'text-zinc-500 dark:text-zinc-400' }}"
                    >
                        <flux:icon.document-text class="size-6" />
                        Pantau
                    </a>
                @endif

                <a
                    href="{{ route('notifikasis.index') }}"
                    wire:navigate
                    class="relative flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium {{ request()->routeIs('notifikasis.*') ? 'text-[#4353ff] dark:text-indigo-300' : 'text-zinc-500 dark:text-zinc-400' }}"
                >
                    <span class="relative">
                        <flux:icon.bell class="size-6" />
                        @php($belumDibacaNav = auth()->user()->notifikasis()->whereNull('dibaca_at')->count())
                        @if ($belumDibacaNav > 0)
                            <span class="absolute -top-1 -right-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $belumDibacaNav }}</span>
                        @endif
                    </span>
                    Notifikasi
                </a>

                <button
                    type="button"
                    x-on:click="menuTerbuka = true"
                    class="flex cursor-pointer flex-col items-center gap-1 py-2.5 text-[11px] font-medium text-zinc-500 dark:text-zinc-400"
                >
                    <flux:icon.squares-2x2 class="size-6" />
                    Menu
                </button>
            </div>
        </nav>

        {{-- Panel Menu mobile (Fase 13): seluruh navigasi dalam lembar bawah, gaya item mengikuti sidebar mockup. --}}
        <div x-cloak x-show="menuTerbuka" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
            <div x-on:click="menuTerbuka = false" class="absolute inset-0 bg-zinc-950/50"></div>

            <div class="animasi-lembar absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-3xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] dark:bg-night">
                <div class="mb-2 flex items-center gap-2.5 rounded-[14px] bg-soft p-2.5 dark:bg-night-soft">
                    <span class="grid size-8.5 shrink-0 place-items-center rounded-full bg-linear-to-br from-[#4353ff] to-[#7c3aed] text-xs font-bold text-white">{{ auth()->user()->initials() }}</span>
                    <span class="grid min-w-0 flex-1 leading-tight">
                        <span class="truncate text-[13px] font-semibold text-zinc-900 dark:text-white">{{ auth()->user()->name }}</span>
                        <span class="truncate text-[11px] text-mist dark:text-zinc-400">{{ auth()->user()->email }}</span>
                    </span>
                    <button
                        type="button"
                        x-on:click="menuTerbuka = false"
                        class="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-zinc-500 hover:bg-white dark:text-zinc-400 dark:hover:bg-night-tile"
                        aria-label="Tutup menu"
                    >
                        <flux:icon.x-mark class="size-4.25" />
                    </button>
                </div>

                <p class="{{ $kelasLabel }}">Utama</p>
                <div class="flex flex-col gap-0.5">
                    <a href="{{ route('dashboard') }}" wire:navigate x-on:click="menuTerbuka = false" class="{{ $kelasItem }}{{ request()->routeIs('dashboard') ? $kelasItemAktif : $kelasItemNormal }}">
                        <flux:icon.squares-2x2 class="size-4.25 shrink-0" />
                        Dashboard
                    </a>
                    @if (auth()->user()->isNasabah())
                        <a href="{{ route('nasabah.pengajuan') }}" wire:navigate x-on:click="menuTerbuka = false" class="{{ $kelasItem }}{{ request()->routeIs('nasabah.pengajuan') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.inbox-arrow-down class="size-4.25 shrink-0" />
                            Pengajuan Saya
                            @if ($perluPerbaikanSidebar > 0)
                                <span class="ml-auto flex items-center justify-center rounded-full bg-red-600 px-1.5 py-px text-[10px] font-bold text-white">{{ $perluPerbaikanSidebar }}</span>
                            @endif
                        </a>
                    @else
                        <a href="{{ route('pengajuans.index') }}" wire:navigate x-on:click="menuTerbuka = false" class="{{ $kelasItem }}{{ request()->routeIs('pengajuans.*') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.document-text class="size-4.25 shrink-0" />
                            Pantau Pengajuan
                            @if ($antreanSidebar > 0)
                                <span class="ml-auto flex items-center justify-center rounded-full bg-red-600 px-1.5 py-px text-[10px] font-bold text-white">{{ $antreanSidebar }}</span>
                            @endif
                        </a>
                    @endif
                    <a href="{{ route('notifikasis.index') }}" wire:navigate x-on:click="menuTerbuka = false" class="{{ $kelasItem }}{{ request()->routeIs('notifikasis.*') ? $kelasItemAktif : $kelasItemNormal }}">
                        <flux:icon.bell class="size-4.25 shrink-0" />
                        Notifikasi
                        @if ($belumDibacaNav > 0)
                            <span class="ml-auto flex items-center justify-center rounded-full bg-red-600 px-1.5 py-px text-[10px] font-bold text-white">{{ $belumDibacaNav }}</span>
                        @endif
                    </a>
                </div>

                @if (auth()->user()->isAdmin())
                    <p class="{{ $kelasLabel }}">Administrasi</p>
                    <div class="flex flex-col gap-0.5">
                        <a href="{{ route('admin.users') }}" wire:navigate x-on:click="menuTerbuka = false" class="{{ $kelasItem }}{{ request()->routeIs('admin.users') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.users class="size-4.25 shrink-0" />
                            Kelola Pengguna
                        </a>
                        <a href="{{ route('admin.layanans') }}" wire:navigate x-on:click="menuTerbuka = false" class="{{ $kelasItem }}{{ request()->routeIs('admin.layanans') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.briefcase class="size-4.25 shrink-0" />
                            Master Layanan
                        </a>
                        <a href="{{ route('admin.aturan-validasi') }}" wire:navigate x-on:click="menuTerbuka = false" class="{{ $kelasItem }}{{ request()->routeIs('admin.aturan-validasi') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.clipboard-document-check class="size-4.25 shrink-0" />
                            Aturan Validasi
                        </a>
                        <a href="{{ route('admin.laporan') }}" wire:navigate x-on:click="menuTerbuka = false" class="{{ $kelasItem }}{{ request()->routeIs('admin.laporan') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.chart-bar class="size-4.25 shrink-0" />
                            Laporan
                        </a>
                        <a href="{{ route('admin.log-aktivitas') }}" wire:navigate x-on:click="menuTerbuka = false" class="{{ $kelasItem }}{{ request()->routeIs('admin.log-aktivitas') ? $kelasItemAktif : $kelasItemNormal }}">
                            <flux:icon.clock class="size-4.25 shrink-0" />
                            Log Aktivitas
                        </a>
                    </div>
                @endif

                <p class="{{ $kelasLabel }}">Akun</p>
                <div class="flex flex-col gap-0.5">
                    <a href="{{ route('profile.edit') }}" wire:navigate x-on:click="menuTerbuka = false" class="{{ $kelasItem }}{{ request()->routeIs('profile.edit') ? $kelasItemAktif : $kelasItemNormal }}">
                        <flux:icon.cog-6-tooth class="size-4.25 shrink-0" />
                        Pengaturan Akun
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <button type="submit" class="{{ $kelasItem }}{{ $kelasItemNormal }} w-full cursor-pointer" data-test="logout-button">
                            <flux:icon.arrow-right-start-on-rectangle class="size-4.25 shrink-0" />
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
