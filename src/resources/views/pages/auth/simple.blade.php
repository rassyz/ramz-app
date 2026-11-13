<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#f1f1f8] antialiased dark:bg-zinc-950">
        <div class="flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            <div class="grid w-full max-w-4xl overflow-hidden rounded-3xl bg-white shadow-[0_20px_60px_rgb(67,83,255,0.10)] dark:bg-zinc-900 lg:grid-cols-5">
                {{-- Panel brand (Fase 8): biru primer, deskripsi sistem yang faktual. --}}
                <div class="relative flex flex-col gap-6 bg-gradient-to-br from-[#4353ff] to-[#6d7cff] p-8 text-white lg:col-span-2">
                    <a href="{{ route('home') }}" class="flex items-center gap-3 font-medium" wire:navigate>
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15">
                            <x-app-logo-icon class="size-7 fill-current text-white" />
                        </span>
                        <span class="text-lg font-semibold">{{ config('app.name', 'Laravel') }}</span>
                    </a>

                    <div>
                        <p class="text-xl font-semibold leading-snug">Monitoring Layanan Perdagangan &amp; Pembayaran Internasional</p>
                        <p class="mt-2 text-sm leading-relaxed text-white/80">
                            Pengajuan layanan, pemeriksaan bukti berbasis aturan, dan verifikasi operator dalam satu sistem terpantau.
                        </p>
                    </div>

                    <div class="mt-auto hidden flex-col gap-3 text-sm lg:flex">
                        <div class="flex items-center gap-3">
                            <flux:icon name="shield-check" class="size-5 text-white/90" />
                            <span>Rule-Based Evidence Checker</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <flux:icon name="bell" class="size-5 text-white/90" />
                            <span>Notifikasi WhatsApp &amp; Email</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <flux:icon name="chart-bar" class="size-5 text-white/90" />
                            <span>Dashboard, Laporan &amp; Log Aktivitas</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col justify-center p-8 md:p-10 lg:col-span-3">
                    <div class="mx-auto w-full max-w-sm">
                        {{ $slot }}
                    </div>
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
