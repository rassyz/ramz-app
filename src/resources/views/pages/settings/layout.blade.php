<div>
    {{-- Navigasi pengaturan sebagai chip (selaras mockup Pengaturan) --}}
    <div class="flex flex-wrap gap-1.5" aria-label="{{ __('Settings') }}">
        <a href="{{ route('profile.edit') }}" wire:navigate class="chip {{ request()->routeIs('profile.edit') ? 'chip-aktif' : '' }}">Profil</a>
        <a href="{{ route('security.edit') }}" wire:navigate class="chip {{ request()->routeIs('security.edit') ? 'chip-aktif' : '' }}">Keamanan</a>
        <a href="{{ route('appearance.edit') }}" wire:navigate class="chip {{ request()->routeIs('appearance.edit') ? 'chip-aktif' : '' }}">Tampilan</a>
    </div>

    <section class="tile mt-4.5">
        <div class="mb-5">
            <h2 class="text-[15px] font-bold tracking-[-0.01em] text-zinc-900 dark:text-white">{{ $heading ?? '' }}</h2>
            @if (! empty($subheading))
                <p class="mt-0.5 text-[13px] text-mist dark:text-zinc-400">{{ $subheading }}</p>
            @endif
        </div>

        <div class="w-full max-w-lg">
            {{ $slot }}
        </div>
    </section>
</div>
