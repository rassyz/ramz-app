<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas antialiased dark:bg-night">
        <div class="grid min-h-svh place-items-center px-5.5 py-5.5">
            <div class="w-full max-w-102.5">
                {{-- Baris brand di atas kartu (selaras mockup Login) --}}
                <a href="{{ route('home') }}" class="mb-5.5 flex items-center justify-center gap-2.5" wire:navigate>
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-[11px] bg-primary text-[17px] font-extrabold text-white">{{ strtoupper(substr(config('app.name', 'R'), 0, 1)) }}</span>
                    <span class="text-left">
                        <span class="block text-base leading-tight font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ config('app.name', 'Laravel') }}</span>
                        <span class="block text-[11px] leading-tight text-mist dark:text-zinc-400">Monitoring Layanan Perdagangan</span>
                    </span>
                </a>

                <section class="tile p-7">
                    {{ $slot }}
                </section>
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
