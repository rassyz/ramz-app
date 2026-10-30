<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('partials.head')
        <style>
            .reveal { opacity: 0; transform: translateY(18px); transition: opacity .7s ease, transform .7s ease; }
            .reveal.revealed { opacity: 1; transform: none; }
            @keyframes melayang { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
            .melayang { animation: melayang 5s ease-in-out infinite; }
            .melayang-lambat { animation: melayang 7s ease-in-out infinite; }
            @keyframes tumbuh { from { transform: scaleY(0); } to { transform: scaleY(1); } }
            .batang-tumbuh { transform-origin: bottom; animation: tumbuh 1s ease both; }
            .pola-titik { background-image: radial-gradient(circle, rgb(67 83 255 / 0.10) 1.2px, transparent 1.2px); background-size: 22px 22px; }
            @media (prefers-reduced-motion: reduce) {
                .reveal { opacity: 1; transform: none; transition: none; }
                .melayang, .melayang-lambat, .batang-tumbuh { animation: none; }
            }
        </style>
    </head>
    <body class="min-h-screen bg-[#f1f1f8] text-zinc-900 antialiased">
        {{-- ============ NAVBAR ============ --}}
        <header id="navbar" class="sticky top-0 z-40 border-b border-white/60 bg-[#f1f1f8]/85 backdrop-blur transition-shadow duration-300">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff]">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#4353ff]">
                        <x-app-logo-icon class="size-6 fill-current text-white" />
                    </span>
                    <span class="leading-tight">
                        <span class="block font-semibold">{{ config('app.name', 'Laravel') }}</span>
                        <span class="block text-xs text-zinc-500">Monitoring Layanan Internasional</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-7 text-sm text-zinc-600 lg:flex">
                    <a href="#fitur" data-spy="fitur" class="rounded-lg px-1 py-1 transition-colors hover:text-[#4353ff] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff]">Fitur</a>
                    <a href="#simulasi" data-spy="simulasi" class="rounded-lg px-1 py-1 transition-colors hover:text-[#4353ff] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff]">Simulasi Checker</a>
                    <a href="#cara-kerja" data-spy="cara-kerja" class="rounded-lg px-1 py-1 transition-colors hover:text-[#4353ff] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff]">Cara Kerja</a>
                    <a href="#peran" data-spy="peran" class="rounded-lg px-1 py-1 transition-colors hover:text-[#4353ff] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff]">Peran</a>
                    <a href="#teknologi" data-spy="teknologi" class="rounded-lg px-1 py-1 transition-colors hover:text-[#4353ff] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff]">Teknologi</a>
                    <a href="#faq" data-spy="faq" class="rounded-lg px-1 py-1 transition-colors hover:text-[#4353ff] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff]">FAQ</a>
                </nav>

                <div class="hidden items-center gap-2 lg:flex">
                    @auth
                        <span class="mr-1 text-sm text-zinc-500">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</span>
                        <a href="{{ route('dashboard') }}" class="rounded-xl bg-[#4353ff] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#3543d6] active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff] focus-visible:ring-offset-2">Buka Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-xl px-4 py-2 text-sm font-medium text-zinc-700 transition hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff]">Masuk</a>
                        <a href="{{ route('register') }}" class="rounded-xl bg-[#4353ff] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#3543d6] active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff] focus-visible:ring-offset-2">Daftar sebagai Nasabah</a>
                    @endauth
                </div>

                <button id="tombol-menu" class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-zinc-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff] lg:hidden" aria-label="Buka menu">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                </button>
            </div>

            <div id="menu-mobile" class="hidden border-t border-zinc-200 bg-white px-4 py-4 lg:hidden">
                <nav class="flex flex-col gap-3 text-sm text-zinc-700">
                    <a href="#fitur">Fitur</a>
                    <a href="#simulasi">Simulasi Checker</a>
                    <a href="#cara-kerja">Cara Kerja</a>
                    <a href="#peran">Peran</a>
                    <a href="#teknologi">Teknologi</a>
                    <a href="#faq">FAQ</a>
                </nav>
                <div class="mt-4 flex gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-xl bg-[#4353ff] px-4 py-2 text-sm font-medium text-white">Buka Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-xl bg-zinc-100 px-4 py-2 text-sm font-medium text-zinc-700">Masuk</a>
                        <a href="{{ route('register') }}" class="rounded-xl bg-[#4353ff] px-4 py-2 text-sm font-medium text-white">Daftar sebagai Nasabah</a>
                    @endauth
                </div>
            </div>
        </header>

        {{-- ============ HERO ============ --}}
        <section class="relative overflow-hidden">
            <div class="pola-titik pointer-events-none absolute inset-x-0 top-0 h-[540px] opacity-70 [mask-image:linear-gradient(to_bottom,black,transparent)]"></div>
            <div class="pointer-events-none absolute -left-32 top-10 h-80 w-80 rounded-full bg-[#4353ff]/15 blur-3xl"></div>
            <div class="pointer-events-none absolute -right-32 top-40 h-80 w-80 rounded-full bg-violet-400/20 blur-3xl"></div>
            <div class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-gradient-to-b from-[#dfe5ff]/70 to-transparent"></div>

            <div class="relative mx-auto max-w-7xl px-4 pb-16 pt-14 sm:px-6 lg:px-8 lg:pt-20">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="inline-block rounded-full bg-white px-3.5 py-1.5 text-xs font-medium text-[#4353ff] shadow-sm ring-1 ring-zinc-950/5">Sentra Layanan Perdagangan &amp; Pembayaran Internasional</span>
                    <h1 class="mt-5 text-4xl font-bold leading-tight tracking-tight sm:text-5xl">
                        Monitoring Layanan Perdagangan &amp; Pembayaran Internasional dalam <span class="bg-gradient-to-r from-[#4353ff] to-[#8b5cf6] bg-clip-text text-transparent">Satu Sistem Terpantau</span>
                    </h1>
                    <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-zinc-600">
                        Pengajuan layanan, pemeriksaan bukti berbasis aturan, verifikasi operator, dan notifikasi status —
                        seluruh proses tercatat dan dapat dipantau oleh nasabah, operator, dan admin secara waktu nyata.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-xl bg-[#4353ff] px-6 py-3 text-sm font-semibold text-white shadow-[0_12px_32px_rgb(67,83,255,0.35)] transition hover:bg-[#3543d6] active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff] focus-visible:ring-offset-2">Buka Dashboard</a>
                        @else
                            <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#4353ff] px-6 py-3 text-sm font-semibold text-white shadow-[0_12px_32px_rgb(67,83,255,0.35)] transition hover:bg-[#3543d6] active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff] focus-visible:ring-offset-2">
                                Mulai Pengajuan
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12l-7.5 7.5M21 12H3" /></svg>
                            </a>
                            <a href="{{ route('login') }}" class="rounded-xl bg-zinc-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-zinc-700 active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 focus-visible:ring-offset-2">Masuk</a>
                        @endauth
                        <a href="#simulasi" class="rounded-xl bg-white px-6 py-3 text-sm font-semibold text-zinc-700 shadow-sm ring-1 ring-zinc-950/5 transition hover:text-[#4353ff] active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff]">Coba Simulasi Checker</a>
                    </div>
                </div>

                {{-- Mockup dashboard + kartu melayang (ilustrasi tampilan) --}}
                <div class="relative mx-auto mt-14 max-w-4xl">
                    <div class="melayang absolute -left-6 top-16 z-10 hidden items-center gap-3 rounded-2xl bg-white/95 p-3.5 pr-5 shadow-[0_16px_40px_rgb(0,0,0,0.10)] ring-1 ring-zinc-950/5 backdrop-blur md:flex lg:-left-16">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                        </span>
                        <span class="leading-tight">
                            <span class="block text-sm font-semibold">Pemeriksaan Sesuai</span>
                            <span class="block text-xs text-zinc-400">Rule-Based Evidence Checker</span>
                        </span>
                    </div>
                    <div class="melayang-lambat absolute -right-6 bottom-20 z-10 hidden items-center gap-3 rounded-2xl bg-white/95 p-3.5 pr-5 shadow-[0_16px_40px_rgb(0,0,0,0.10)] ring-1 ring-zinc-950/5 backdrop-blur md:flex lg:-right-16">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#eef0ff] text-[#4353ff]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 3.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-.42 4.032c.7.933 1.533 1.708 2.563 2.3m5.714 2.25a3 3 0 1 1-5.714 0" /></svg>
                        </span>
                        <span class="leading-tight">
                            <span class="block text-sm font-semibold">Notifikasi Terkirim</span>
                            <span class="block text-xs text-zinc-400">WhatsApp &amp; Email nasabah</span>
                        </span>
                    </div>

                    <div class="reveal relative rounded-3xl bg-white p-5 shadow-[0_24px_60px_rgb(67,83,255,0.12)] ring-1 ring-zinc-950/5 sm:p-7">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-lg font-bold">Dashboard</p>
                                <p class="text-xs text-zinc-400">Ringkasan pengajuan pada periode berjalan</p>
                            </div>
                            <span class="rounded-full bg-[#eef0ff] px-3 py-1 text-xs font-medium text-[#4353ff]">30 Hari Terakhir</span>
                        </div>
                        <div class="mt-5 grid gap-4 sm:grid-cols-3">
                            <div class="rounded-2xl bg-gradient-to-br from-[#4353ff] to-[#6d7cff] p-4 text-white">
                                <p class="text-xs text-white/75">Total Pengajuan</p>
                                <p class="mt-1 text-3xl font-bold">128</p>
                                <span class="mt-2 inline-block rounded-full bg-white/20 px-2 py-0.5 text-[11px]">+12.5% dari periode sebelumnya</span>
                            </div>
                            <div class="rounded-2xl bg-[#f7f7fc] p-4">
                                <p class="text-xs text-zinc-500">Menunggu Verifikasi</p>
                                <p class="mt-1 text-3xl font-bold">14</p>
                                <p class="mt-2 text-[11px] text-zinc-400">Antrean operator saat ini</p>
                            </div>
                            <div class="rounded-2xl bg-[#f7f7fc] p-4">
                                <p class="text-xs text-zinc-500">Selesai</p>
                                <p class="mt-1 text-3xl font-bold">86</p>
                                <span class="mt-2 inline-block rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-600">+8.2% dari periode sebelumnya</span>
                            </div>
                        </div>
                        <div class="mt-4 flex h-20 items-end gap-1.5 rounded-2xl bg-[#f7f7fc] px-4 pb-3 pt-4">
                            @foreach ([35, 55, 40, 70, 50, 85, 62, 95, 72, 100, 66, 88, 58, 78] as $tinggi)
                                <div class="batang-tumbuh flex-1 rounded-t-md {{ $loop->index === 9 ? 'bg-[#4353ff]' : 'bg-[#c9d2ff]' }}" style="height: {{ $tinggi }}%; animation-delay: {{ $loop->index * 60 }}ms"></div>
                            @endforeach
                        </div>
                        <div class="mt-4 grid items-center gap-6 rounded-2xl bg-[#f7f7fc] p-4 sm:grid-cols-[auto_1fr]">
                            <div class="relative mx-auto h-28 w-28">
                                <svg viewBox="0 0 200 200" class="h-28 w-28 -rotate-90">
                                    <circle cx="100" cy="100" r="84" fill="none" stroke-width="26" stroke="#ececf4" />
                                    <circle cx="100" cy="100" r="84" fill="none" stroke-width="26" stroke="#10b981" stroke-dasharray="355 528" />
                                    <circle cx="100" cy="100" r="84" fill="none" stroke-width="26" stroke="#f59e0b" stroke-dasharray="58 528" stroke-dashoffset="-355" />
                                    <circle cx="100" cy="100" r="84" fill="none" stroke-width="26" stroke="#8b5cf6" stroke-dasharray="115 528" stroke-dashoffset="-413" />
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center text-xl font-bold">128</div>
                            </div>
                            <div class="space-y-2 text-sm">
                                <p class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Selesai</span><strong>86</strong></p>
                                <p class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-violet-500"></span>Diproses</span><strong>28</strong></p>
                                <p class="flex items-center justify-between"><span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>Menunggu Verifikasi</span><strong>14</strong></p>
                                <p class="text-xs text-zinc-400">Ilustrasi tampilan — angka contoh, bukan data operasional.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Strip statistik hidup dari database --}}
                <div class="reveal mx-auto mt-10 grid max-w-4xl gap-4 sm:grid-cols-3">
                    <div class="rounded-3xl bg-white p-5 text-center shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-zinc-950/5">
                        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-2xl bg-[#eef0ff] text-[#4353ff]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>
                        </span>
                        <p class="mt-2 text-3xl font-bold text-[#4353ff]"><span data-count="{{ $statistik['layanan'] }}">0</span></p>
                        <p class="mt-1 text-sm text-zinc-500">Layanan aktif terdaftar</p>
                    </div>
                    <div class="rounded-3xl bg-white p-5 text-center shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-zinc-950/5">
                        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-2xl bg-[#eef0ff] text-[#4353ff]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-17.5 0V18a2.25 2.25 0 0 0 2.25 2.25h13.5A2.25 2.25 0 0 0 21.75 18v-4.5m-17.5 0 1.754-6.577A2.25 2.25 0 0 1 6.177 5.25h11.646a2.25 2.25 0 0 1 2.173 1.673L21.75 13.5" /></svg>
                        </span>
                        <p class="mt-2 text-3xl font-bold text-[#4353ff]"><span data-count="{{ $statistik['pengajuan'] }}">0</span></p>
                        <p class="mt-1 text-sm text-zinc-500">Pengajuan tercatat di sistem</p>
                    </div>
                    <div class="rounded-3xl bg-white p-5 text-center shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-zinc-950/5">
                        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-2xl bg-[#eef0ff] text-[#4353ff]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                        </span>
                        <p class="mt-2 text-3xl font-bold text-[#4353ff]"><span data-count="{{ $statistik['pemeriksaan'] }}">0</span></p>
                        <p class="mt-1 text-sm text-zinc-500">Pemeriksaan checker dijalankan</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ MASALAH ============ --}}
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="reveal max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#4353ff]">Latar Belakang</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight">Tantangan pada Proses yang Berjalan</h2>
                <p class="mt-3 text-zinc-600">Sebelum sistem ini, pengajuan layanan perdagangan dan pembayaran internasional berjalan manual dengan pemantauan yang terbatas.</p>
            </div>
            <div class="mt-8 grid gap-4 md:grid-cols-3">
                <div class="reveal rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-zinc-950/5 transition duration-200 hover:-translate-y-1 hover:shadow-[0_20px_44px_rgb(0,0,0,0.08)]">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#eef0ff] text-[#4353ff]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    </span>
                    <h3 class="mt-4 font-semibold">Dokumen Tersebar &amp; Manual</h3>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-600">Berkas pengajuan berpindah secara manual sehingga kelengkapannya sulit dipastikan dan riwayatnya tidak terdokumentasi dalam satu tempat.</p>
                </div>
                <div class="reveal rounded-3xl bg-gradient-to-br from-[#4353ff] to-[#6d7cff] p-6 text-white shadow-[0_12px_32px_rgb(67,83,255,0.30)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_20px_44px_rgb(67,83,255,0.40)]">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </span>
                    <h3 class="mt-4 font-semibold">Pemeriksaan Bukti Memakan Waktu</h3>
                    <p class="mt-2 text-sm leading-relaxed text-white/85">Pemeriksaan kelengkapan dan kesesuaian dokumen dilakukan satu per satu secara manual — lambat dan rawan terlewat, terutama pada jam sibuk.</p>
                </div>
                <div class="reveal rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-zinc-950/5 transition duration-200 hover:-translate-y-1 hover:shadow-[0_20px_44px_rgb(0,0,0,0.08)]">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#eef0ff] text-[#4353ff]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    </span>
                    <h3 class="mt-4 font-semibold">Status Sulit Dipantau</h3>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-600">Nasabah tidak dapat melihat sampai di mana pengajuannya diproses tanpa bertanya langsung, dan pimpinan tidak memiliki ringkasan kinerja yang siap dilihat.</p>
                </div>
            </div>
        </section>

        {{-- ============ FITUR (pita putih) ============ --}}
        <section id="fitur" class="scroll-mt-24 border-y border-zinc-950/5 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#4353ff]">Fitur Utama</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight">Semua yang Dibutuhkan untuk Memantau Layanan</h2>
                    <p class="mt-3 text-zinc-600">Enam kemampuan inti yang bekerja sebagai satu alur, dari pengajuan sampai laporan.</p>
                </div>
                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3h-6m5.25-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z', 'Pengajuan Layanan Online', 'Nasabah memilih layanan, mengisi data pembayaran, dan mengunggah dokumen persyaratan secara mandiri. Setiap pengajuan bernomor resmi dan tercatat sejak detik pertama.'],
                        ['M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z', 'Rule-Based Evidence Checker', 'Mesin aturan IF–THEN memeriksa kelengkapan dokumen per jenis layanan dan mencocokkan lima field terstruktur pada Invoice dengan data pengajuan & pembayaran.'],
                        ['M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z', 'Verifikasi Operator & Riwayat Status', 'Operator memverifikasi dokumen dan memutuskan hasil akhir. Setiap perubahan status tercatat sebagai riwayat yang dapat ditelusuri kembali kapan pun.'],
                        ['M14.857 17.082a23.848 23.848 0 0 0 3.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-.42 4.032c.7.933 1.533 1.708 2.563 2.3m5.714 2.25a3 3 0 1 1-5.714 0', 'Notifikasi WhatsApp & Email', 'Setiap keputusan dan perubahan status diberitahukan ke nasabah melalui WhatsApp dan email, dengan antrean pengiriman serta mekanisme retry dan backoff bila gagal.'],
                        ['M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z', 'Dashboard Dinamis & Personalisasi', 'Ringkasan, tren, distribusi status, dan antrean verifikasi tersaji per peran dan per periode — tampilannya dapat dipersonalisasi oleh masing-masing pengguna.'],
                        ['M9 12h3.75M9 15h3.75M11.25 5.25h-6A2.25 2.25 0 0 0 3 7.5v12A2.25 2.25 0 0 0 5.25 21.75h13.5A2.25 2.25 0 0 0 21 19.5V7.5a2.25 2.25 0 0 0-2.25-2.25h-6M9 3.75A1.125 1.125 0 0 1 10.125 2.625h3.75A1.125 1.125 0 0 1 15 3.75v1.5a1.125 1.125 0 0 1-1.125 1.125h-3.75A1.125 1.125 0 0 1 9 5.25v-1.5Z', 'Laporan & Log Aktivitas', 'Admin menyusun laporan periode dan mengunduhnya, sementara seluruh aktivitas penting pengguna tercatat dalam log yang hanya dapat dibaca, tidak diubah.'],
                    ] as [$ikon, $judul, $isi])
                        <div class="reveal rounded-3xl bg-[#fbfbfe] p-6 ring-1 ring-zinc-950/5 transition duration-200 hover:-translate-y-1 hover:bg-white hover:shadow-[0_20px_44px_rgb(0,0,0,0.08)]">
                            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#4353ff] text-white shadow-[0_8px_20px_rgb(67,83,255,0.35)]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon }}" /></svg>
                            </span>
                            <h3 class="mt-4 font-semibold">{{ $judul }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-zinc-600">{{ $isi }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ============ SIMULASI CHECKER ============ --}}
        <section id="simulasi" class="mx-auto max-w-7xl scroll-mt-24 px-4 py-16 sm:px-6 lg:px-8">
            <div class="reveal overflow-hidden rounded-3xl bg-white shadow-[0_8px_30px_rgb(0,0,0,0.05)] ring-1 ring-zinc-950/5">
                <div class="border-b border-zinc-100 p-6 sm:p-8">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#4353ff]">Simulasi Interaktif</p>
                            <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Coba Cara Kerja Evidence Checker</h2>
                        </div>
                        <span class="inline-block rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-600 ring-1 ring-amber-200">Simulasi — bukan pemeriksaan sungguhan</span>
                    </div>
                    <p class="mt-3 max-w-3xl text-sm leading-relaxed text-zinc-600">
                        Checker sungguhan di dalam sistem menjalankan aturan yang sama seperti simulasi ini: kelengkapan dokumen sesuai
                        persyaratan layanan, lalu pencocokan lima field terstruktur pada dokumen Invoice. Ubah isi field atau hilangkan
                        centang dokumen, lalu tekan Periksa untuk melihat hasilnya.
                    </p>
                    <ol class="mt-5 flex flex-wrap items-center gap-2 text-xs font-medium">
                        <li class="flex items-center gap-2 rounded-full bg-[#eef0ff] px-3 py-1.5 text-[#4353ff]"><span class="flex h-5 w-5 items-center justify-center rounded-full bg-[#4353ff] text-[11px] font-bold text-white">1</span> Centang Dokumen</li>
                        <li class="text-zinc-300">→</li>
                        <li class="flex items-center gap-2 rounded-full bg-[#eef0ff] px-3 py-1.5 text-[#4353ff]"><span class="flex h-5 w-5 items-center justify-center rounded-full bg-[#4353ff] text-[11px] font-bold text-white">2</span> Isi Data Invoice</li>
                        <li class="text-zinc-300">→</li>
                        <li class="flex items-center gap-2 rounded-full bg-[#eef0ff] px-3 py-1.5 text-[#4353ff]"><span class="flex h-5 w-5 items-center justify-center rounded-full bg-[#4353ff] text-[11px] font-bold text-white">3</span> Lihat Hasil</li>
                    </ol>
                </div>

                <div class="grid gap-0 lg:grid-cols-2">
                    <div class="space-y-6 p-6 sm:p-8">
                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Data Pengajuan (acuan)</h3>
                            <dl class="mt-3 space-y-1.5 rounded-2xl bg-[#f7f7fc] p-4 text-sm">
                                <div class="flex justify-between gap-4"><dt class="text-zinc-500">Layanan</dt><dd class="font-medium">Penerbitan LC Impor (LC-IMP)</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-zinc-500">Nasabah</dt><dd class="font-medium" id="acuan-nasabah">PT Arunika Ekspor</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-zinc-500">Nomor invoice</dt><dd class="font-medium" id="acuan-invoice">INV-2026-0117</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-zinc-500">Nominal pembayaran</dt><dd class="font-medium" id="acuan-nominal">25000.00</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-zinc-500">Mata uang</dt><dd class="font-medium" id="acuan-matauang">USD</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-zinc-500">Jenis layanan</dt><dd class="font-medium" id="acuan-layanan">LC-IMP</dd></div>
                            </dl>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Dokumen yang diunggah</h3>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach (['Invoice', 'Purchase Order', 'Packing List', 'Bill of Lading'] as $dok)
                                    <label class="flex cursor-pointer items-center gap-2.5 rounded-2xl border border-zinc-200 px-4 py-3 text-sm transition hover:border-[#4353ff] hover:bg-[#f7f8ff]">
                                        <input type="checkbox" class="dok-simulasi size-4 accent-[#4353ff]" value="{{ $dok }}" checked>
                                        {{ $dok }} <span class="text-xs text-zinc-400">(wajib)</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Data terstruktur pada Invoice</h3>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label class="block text-sm">Nomor invoice
                                    <input id="fld-invoice" type="text" value="INV-2026-0117" class="mt-1 w-full rounded-xl border-zinc-300 text-sm focus:border-[#4353ff] focus:ring-[#4353ff]">
                                </label>
                                <label class="block text-sm">Identitas nasabah
                                    <input id="fld-nasabah" type="text" value="PT Arunika Ekspor" class="mt-1 w-full rounded-xl border-zinc-300 text-sm focus:border-[#4353ff] focus:ring-[#4353ff]">
                                </label>
                                <label class="block text-sm">Nominal pembayaran
                                    <input id="fld-nominal" type="text" value="25000.00" class="mt-1 w-full rounded-xl border-zinc-300 text-sm focus:border-[#4353ff] focus:ring-[#4353ff]">
                                </label>
                                <label class="block text-sm">Mata uang
                                    <input id="fld-matauang" type="text" value="USD" class="mt-1 w-full rounded-xl border-zinc-300 text-sm focus:border-[#4353ff] focus:ring-[#4353ff]">
                                </label>
                                <label class="block text-sm">Jenis layanan
                                    <input id="fld-layanan" type="text" value="LC-IMP" class="mt-1 w-full rounded-xl border-zinc-300 text-sm focus:border-[#4353ff] focus:ring-[#4353ff]">
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button id="tombol-periksa" class="rounded-xl bg-[#4353ff] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#3543d6] active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff] focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-70">Periksa</button>
                            <button id="tombol-atur-ulang" class="rounded-xl bg-white px-6 py-3 text-sm font-semibold text-zinc-600 ring-1 ring-zinc-200 transition hover:text-[#4353ff] active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff]">Atur Ulang</button>
                        </div>
                    </div>

                    <div class="border-t border-zinc-100 bg-[#fbfbfe] p-6 sm:p-8 lg:border-l lg:border-t-0">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-500">Hasil Pemeriksaan</h3>
                        <div id="hasil-simulasi" class="mt-4">
                            <p class="rounded-2xl bg-white p-5 text-sm text-zinc-500 shadow-sm ring-1 ring-zinc-950/5">Hasil akan tampil di sini setelah tombol Periksa ditekan. Pada sistem sungguhan, hasil ini menjadi bahan pertimbangan operator — keputusan akhir selalu di tangan operator.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ CARA KERJA ============ --}}
        <section id="cara-kerja" class="mx-auto max-w-7xl scroll-mt-24 px-4 py-16 sm:px-6 lg:px-8">
            <div class="reveal mx-auto max-w-2xl text-center">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#4353ff]">Alur Proses</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight">Cara Kerja</h2>
                <p class="mt-3 text-zinc-600">Satu alur terpantau dari pengajuan sampai selesai.</p>
            </div>
            <div class="relative mt-10 grid gap-4 md:grid-cols-4">
                <div class="pointer-events-none absolute left-0 right-0 top-11 hidden border-t-2 border-dashed border-[#c9d2ff] md:block"></div>
                @foreach ([
                    ['1', 'Nasabah Mengajukan', 'Memilih layanan, mengisi data pembayaran, dan mengunggah dokumen persyaratan.'],
                    ['2', 'Checker Memeriksa', 'Kelengkapan dan kesesuaian bukti diperiksa otomatis oleh aturan IF–THEN.'],
                    ['3', 'Operator Memverifikasi', 'Hasil checker ditinjau; operator memutuskan terverifikasi atau perlu perbaikan.'],
                    ['4', 'Status & Notifikasi Berjalan', 'Nasabah menerima pemberitahuan, dan seluruh proses tercatat untuk admin.'],
                ] as [$no, $judul, $isi])
                    <div class="reveal relative rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-zinc-950/5 transition duration-200 hover:-translate-y-1 hover:shadow-[0_20px_44px_rgb(0,0,0,0.08)]">
                        <span class="relative z-10 flex h-11 w-11 items-center justify-center rounded-full bg-[#4353ff] text-lg font-bold text-white shadow-[0_8px_20px_rgb(67,83,255,0.40)] ring-4 ring-[#f1f1f8]">{{ $no }}</span>
                        <h3 class="mt-4 font-semibold">{{ $judul }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-600">{{ $isi }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ============ PERAN (pita putih) ============ --}}
        <section id="peran" class="scroll-mt-24 border-y border-zinc-950/5 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#4353ff]">Peran Pengguna</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight">Tiga Peran, Kewenangan yang Jelas</h2>
                </div>
                <div class="mt-10 grid gap-4 md:grid-cols-3">
                    <div class="reveal rounded-3xl bg-[#fbfbfe] p-6 ring-1 ring-zinc-950/5 transition duration-200 hover:-translate-y-1 hover:bg-white hover:shadow-[0_20px_44px_rgb(0,0,0,0.08)]">
                        <span class="inline-block rounded-full bg-[#eef0ff] px-3 py-1 text-xs font-semibold text-[#4353ff]">Nasabah</span>
                        <p class="mt-3 text-sm leading-relaxed text-zinc-600">Mendaftar secara mandiri, mengajukan layanan, mengunggah dan memperbaiki dokumen, serta memantau status dan riwayat pengajuannya sendiri.</p>
                    </div>
                    <div class="reveal rounded-3xl bg-gradient-to-br from-[#4353ff] to-[#6d7cff] p-6 text-white shadow-[0_12px_32px_rgb(67,83,255,0.30)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_20px_44px_rgb(67,83,255,0.40)]">
                        <span class="inline-block rounded-full bg-white/20 px-3 py-1 text-xs font-semibold">Operator</span>
                        <p class="mt-3 text-sm leading-relaxed text-white/85">Memantau antrean pengajuan, meninjau hasil checker, memverifikasi dokumen, memperbarui status pengajuan dan pembayaran, serta memberi catatan perbaikan.</p>
                    </div>
                    <div class="reveal rounded-3xl bg-[#fbfbfe] p-6 ring-1 ring-zinc-950/5 transition duration-200 hover:-translate-y-1 hover:bg-white hover:shadow-[0_20px_44px_rgb(0,0,0,0.08)]">
                        <span class="inline-block rounded-full bg-[#eef0ff] px-3 py-1 text-xs font-semibold text-[#4353ff]">Admin</span>
                        <p class="mt-3 text-sm leading-relaxed text-zinc-600">Mengelola pengguna dan master layanan, mengatur aturan validasi checker, serta memantau dashboard, laporan periode, dan log aktivitas seluruh sistem.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ TEKNOLOGI ============ --}}
        <section id="teknologi" class="mx-auto max-w-7xl scroll-mt-24 px-4 py-16 sm:px-6 lg:px-8">
            <div class="reveal relative overflow-hidden rounded-3xl bg-zinc-900 p-8 text-white sm:p-10">
                <div class="pola-titik pointer-events-none absolute inset-0 opacity-20 [filter:invert(1)]"></div>
                <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-[#4353ff]/30 blur-3xl"></div>
                <div class="relative">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#b9c2ff]">Teknologi</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Dibangun dengan Teknologi Web Modern</h2>
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-zinc-300">Dikembangkan dengan metode Rapid Application Development (RAD) — dibangun bertahap per kebutuhan fungsional, diuji pada setiap tahapnya.</p>
                    <div class="mt-7 flex flex-wrap gap-2.5">
                        @foreach (['Laravel 13', 'Livewire', 'Tailwind CSS', 'Flux UI', 'MySQL / MariaDB', 'Docker'] as $tek)
                            <span class="rounded-full bg-white/10 px-4 py-2 text-sm font-medium ring-1 ring-white/15 transition hover:bg-white/20">{{ $tek }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ FAQ ============ --}}
        <section id="faq" class="mx-auto max-w-4xl scroll-mt-24 px-4 pb-16 sm:px-6 lg:px-8">
            <div class="reveal text-center">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#4353ff]">FAQ</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight">Pertanyaan yang Sering Diajukan</h2>
            </div>
            <div class="mt-8 space-y-3">
                @foreach ([
                    ['Apakah sistem ini memproses transaksi keuangan secara langsung?', 'Tidak. Sistem ini adalah sistem monitoring: pengajuan, data pembayaran, dan dokumen tercatat dan terpantau di dalamnya, tetapi pemrosesan transaksi keuangan tetap berada pada sistem perbankan. Tidak ada integrasi payment gateway maupun core banking.'],
                    ['Apakah Evidence Checker menggunakan AI atau OCR?', 'Tidak. Checker bekerja dengan aturan IF–THEN (rule-based): memeriksa kelengkapan dokumen sesuai persyaratan layanan dan mencocokkan lima field terstruktur yang diisi nasabah saat mengunggah Invoice. Checker tidak membaca isi berkas dan tidak menilai keaslian dokumen secara hukum.'],
                    ['Siapa yang memutuskan hasil akhir pengajuan?', 'Operator. Hasil checker adalah pendukung keputusan yang menunjukkan kelengkapan dan kesesuaian data; keputusan terverifikasi atau perlu perbaikan selalu diambil operator dan tercatat beserta alasannya.'],
                    ['Siapa saja yang boleh mendaftar?', 'Pendaftaran publik terbuka untuk nasabah. Akun operator dan admin tidak dapat dibuat melalui pendaftaran — keduanya dibuat oleh admin demi keamanan akses.'],
                    ['Bagaimana saya tahu status pengajuan saya berubah?', 'Sistem mengirim notifikasi melalui WhatsApp dan email pada setiap keputusan verifikasi dan perubahan status, ditambah kotak masuk notifikasi di dalam aplikasi. Pengiriman yang gagal otomatis dicoba ulang dengan mekanisme retry dan backoff.'],
                ] as [$tanya, $jawab])
                    <details class="reveal group rounded-3xl bg-white px-6 py-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] ring-1 ring-zinc-950/5 transition hover:shadow-[0_14px_36px_rgb(0,0,0,0.07)]">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-lg font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff]">
                            {{ $tanya }}
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#eef0ff] text-[#4353ff] transition-transform duration-300 group-open:rotate-45">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            </span>
                        </summary>
                        <p class="mt-3 text-sm leading-relaxed text-zinc-600">{{ $jawab }}</p>
                    </details>
                @endforeach
            </div>
        </section>

        {{-- ============ CTA PENUTUP ============ --}}
        <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
            <div class="reveal relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#4353ff] to-[#6d7cff] p-8 text-center text-white shadow-[0_24px_60px_rgb(67,83,255,0.30)] sm:p-12">
                <div class="pola-titik pointer-events-none absolute inset-0 opacity-25 [filter:invert(1)]"></div>
                <div class="relative">
                    <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight">Siap Mengajukan Layanan dengan Proses yang Terpantau?</h2>
                    <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-white/85">Daftar sebagai nasabah dan ajukan layanan perdagangan atau pembayaran internasional Anda — setiap tahapnya dapat Anda pantau sampai selesai.</p>
                    <div class="mt-7 flex flex-wrap justify-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-xl bg-white px-6 py-3 text-sm font-semibold text-[#4353ff] transition hover:bg-zinc-100 active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#4353ff]">Buka Dashboard</a>
                        @else
                            <a href="{{ route('register') }}" class="rounded-xl bg-white px-6 py-3 text-sm font-semibold text-[#4353ff] transition hover:bg-zinc-100 active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#4353ff]">Daftar Sekarang</a>
                            <a href="{{ route('login') }}" class="rounded-xl bg-white/15 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/25 active:scale-[.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">Masuk</a>
                        @endauth
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ FOOTER ============ --}}
        <footer class="border-t border-zinc-200 bg-white">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#4353ff]">
                            <x-app-logo-icon class="size-6 fill-current text-white" />
                        </span>
                        <p class="font-semibold">{{ config('app.name', 'Laravel') }}</p>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-zinc-500">Aplikasi Monitoring Layanan Perdagangan dan Pembayaran Internasional Berbasis Web dengan Rule-Based Evidence Checker.</p>
                </div>
                <div>
                    <p class="text-sm font-semibold">Navigasi</p>
                    <nav class="mt-3 flex flex-col gap-2 text-sm text-zinc-600">
                        <a href="#fitur" class="transition hover:text-[#4353ff]">Fitur</a>
                        <a href="#simulasi" class="transition hover:text-[#4353ff]">Simulasi Checker</a>
                        <a href="#cara-kerja" class="transition hover:text-[#4353ff]">Cara Kerja</a>
                        <a href="#faq" class="transition hover:text-[#4353ff]">FAQ</a>
                    </nav>
                </div>
                <div>
                    <p class="text-sm font-semibold">Kemampuan Inti</p>
                    <nav class="mt-3 flex flex-col gap-2 text-sm text-zinc-600">
                        <a href="#fitur" class="transition hover:text-[#4353ff]">Pengajuan Layanan Online</a>
                        <a href="#simulasi" class="transition hover:text-[#4353ff]">Rule-Based Evidence Checker</a>
                        <a href="#fitur" class="transition hover:text-[#4353ff]">Notifikasi WhatsApp &amp; Email</a>
                        <a href="#fitur" class="transition hover:text-[#4353ff]">Laporan &amp; Log Aktivitas</a>
                    </nav>
                </div>
                <div>
                    <p class="text-sm font-semibold">Akses</p>
                    <nav class="mt-3 flex flex-col gap-2 text-sm text-zinc-600">
                        @auth
                            <a href="{{ route('dashboard') }}" class="transition hover:text-[#4353ff]">Buka Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="transition hover:text-[#4353ff]">Masuk</a>
                            <a href="{{ route('register') }}" class="transition hover:text-[#4353ff]">Daftar sebagai Nasabah</a>
                        @endauth
                        <a href="#peran" class="transition hover:text-[#4353ff]">Peran Pengguna</a>
                        <a href="#teknologi" class="transition hover:text-[#4353ff]">Teknologi</a>
                    </nav>
                </div>
            </div>
            <div class="border-t border-zinc-100">
                <p class="mx-auto max-w-7xl px-4 py-5 text-xs text-zinc-400 sm:px-6 lg:px-8">© {{ date('Y') }} {{ config('app.name', 'Laravel') }} — Sistem Monitoring Layanan Perdagangan dan Pembayaran Internasional.</p>
            </div>
        </footer>

        {{-- Tombol kembali ke atas --}}
        <button id="tombol-ke-atas" class="fixed bottom-6 right-6 z-40 flex h-11 w-11 translate-y-24 items-center justify-center rounded-full bg-[#4353ff] text-white shadow-[0_12px_32px_rgb(67,83,255,0.40)] transition-all duration-300 hover:bg-[#3543d6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#4353ff] focus-visible:ring-offset-2" aria-label="Kembali ke atas">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75 12 8.25l7.5 7.5" /></svg>
        </button>

        <script>
            const gerakDikurangi = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // Menu mobile
            document.getElementById('tombol-menu').addEventListener('click', () => {
                document.getElementById('menu-mobile').classList.toggle('hidden');
            });
            document.querySelectorAll('#menu-mobile a').forEach(a => a.addEventListener('click', () => {
                document.getElementById('menu-mobile').classList.add('hidden');
            }));

            // Navbar berbayang + tombol kembali ke atas mengikuti guliran
            const navbar = document.getElementById('navbar');
            const tombolKeAtas = document.getElementById('tombol-ke-atas');
            window.addEventListener('scroll', () => {
                navbar.classList.toggle('shadow-[0_8px_30px_rgb(0,0,0,0.06)]', window.scrollY > 8);
                tombolKeAtas.classList.toggle('translate-y-24', window.scrollY <= 600);
            }, { passive: true });
            tombolKeAtas.addEventListener('click', () => window.scrollTo({ top: 0, behavior: gerakDikurangi ? 'auto' : 'smooth' }));

            // Scrollspy: tandai tautan section yang sedang terlihat
            const tautanSpy = document.querySelectorAll('[data-spy]');
            const pengamatSpy = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;
                    tautanSpy.forEach(t => {
                        const aktif = t.dataset.spy === entry.target.id;
                        t.classList.toggle('text-[#4353ff]', aktif);
                        t.classList.toggle('font-semibold', aktif);
                    });
                });
            }, { rootMargin: '-40% 0px -55% 0px' });
            ['fitur', 'simulasi', 'cara-kerja', 'peran', 'teknologi', 'faq'].forEach(id => {
                const el = document.getElementById(id);
                if (el) pengamatSpy.observe(el);
            });

            // Reveal saat masuk viewport + count-up statistik
            const pengamat = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('revealed');
                    entry.target.querySelectorAll('[data-count]').forEach(el => {
                        const target = parseInt(el.dataset.count, 10) || 0;
                        if (gerakDikurangi) { el.textContent = target.toLocaleString('id-ID'); return; }
                        const mulai = performance.now(), durasi = 900;
                        const langkah = sekarang => {
                            const kemajuan = Math.min((sekarang - mulai) / durasi, 1);
                            el.textContent = Math.round(target * (1 - Math.pow(1 - kemajuan, 3))).toLocaleString('id-ID');
                            if (kemajuan < 1) requestAnimationFrame(langkah);
                        };
                        requestAnimationFrame(langkah);
                    });
                    pengamat.unobserve(entry.target);
                });
            }, { threshold: 0.15 });
            document.querySelectorAll('.reveal').forEach(el => pengamat.observe(el));

            // ===== Simulasi Rule-Based Evidence Checker (client-side) =====
            const norm = s => (s || '').trim().toLowerCase();

            function jalankanPemeriksaan() {
                const wajib = ['Invoice', 'Purchase Order', 'Packing List', 'Bill of Lading'];
                const tercentang = [...document.querySelectorAll('.dok-simulasi:checked')].map(i => i.value);
                const baris = [];
                let lengkap = true;

                wajib.forEach(nama => {
                    const ada = tercentang.includes(nama);
                    if (!ada) lengkap = false;
                    baris.push({ label: `Kelengkapan: dokumen ${nama} terunggah`, lolos: ada });
                });

                const invoiceAda = tercentang.includes('Invoice');
                let cocok = null;
                if (invoiceAda) {
                    cocok = true;
                    const cek = (label, isi, acuan) => {
                        const sama = norm(isi) === norm(acuan);
                        if (!sama) cocok = false;
                        baris.push({ label: `Pencocokan: ${label}`, lolos: sama });
                    };
                    cek('nomor invoice', document.getElementById('fld-invoice').value, document.getElementById('acuan-invoice').textContent);
                    cek('identitas nasabah', document.getElementById('fld-nasabah').value, document.getElementById('acuan-nasabah').textContent);
                    const nominalIsi = parseFloat(document.getElementById('fld-nominal').value.replace(',', '.'));
                    const nominalAcuan = parseFloat(document.getElementById('acuan-nominal').textContent);
                    const nominalSama = !isNaN(nominalIsi) && Math.abs(nominalIsi - nominalAcuan) <= 0.01;
                    if (!nominalSama) cocok = false;
                    baris.push({ label: 'Pencocokan: nominal pembayaran', lolos: nominalSama });
                    cek('mata uang', document.getElementById('fld-matauang').value, document.getElementById('acuan-matauang').textContent);
                    cek('jenis layanan', document.getElementById('fld-layanan').value.toUpperCase(), document.getElementById('acuan-layanan').textContent);
                } else {
                    baris.push({ label: 'Pencocokan lima field: belum dapat diperiksa — dokumen Invoice belum diunggah', lolos: null });
                }

                let status, kelas, keterangan;
                if (lengkap && cocok) {
                    status = 'Sesuai'; kelas = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                    keterangan = 'Seluruh dokumen wajib tersedia dan kelima field Invoice cocok dengan data pengajuan.';
                } else if (!invoiceAda && lengkap === false && tercentang.length === 0) {
                    status = 'Belum Dapat Diperiksa'; kelas = 'bg-zinc-100 text-zinc-600 border-zinc-200';
                    keterangan = 'Belum ada dokumen yang diunggah, sehingga pemeriksaan belum dapat dilakukan.';
                } else {
                    status = 'Tidak Sesuai'; kelas = 'bg-red-50 text-red-700 border-red-200';
                    keterangan = 'Ditemukan dokumen yang belum lengkap dan/atau field yang tidak cocok — pengajuan akan diarahkan untuk perbaikan.';
                }

                const ikon = lolos => lolos === null
                    ? '<span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-zinc-500 text-xs font-bold">?</span>'
                    : lolos
                        ? '<span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor" class="size-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg></span>'
                        : '<span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor" class="size-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span>';

                document.getElementById('hasil-simulasi').innerHTML = `
                    <div class="rounded-2xl border ${kelas} p-5">
                        <p class="text-lg font-bold">${status}</p>
                        <p class="mt-1 text-sm opacity-80">${keterangan}</p>
                    </div>
                    <ul class="mt-4 space-y-2.5">
                        ${baris.map(b => `<li class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-zinc-950/5">${ikon(b.lolos)}<span>${b.label}</span></li>`).join('')}
                    </ul>
                    <p class="mt-4 text-xs text-zinc-400">Simulasi di peramban Anda — pada sistem sungguhan hasil seperti ini menjadi pendukung keputusan operator.</p>`;
                document.getElementById('hasil-simulasi').scrollIntoView({ behavior: gerakDikurangi ? 'auto' : 'smooth', block: 'nearest' });
            }

            const tombolPeriksa = document.getElementById('tombol-periksa');
            tombolPeriksa.addEventListener('click', () => {
                tombolPeriksa.disabled = true;
                tombolPeriksa.textContent = 'Memeriksa…';
                setTimeout(() => {
                    jalankanPemeriksaan();
                    tombolPeriksa.disabled = false;
                    tombolPeriksa.textContent = 'Periksa';
                }, gerakDikurangi ? 0 : 600);
            });

            document.getElementById('tombol-atur-ulang').addEventListener('click', () => {
                document.querySelectorAll('.dok-simulasi').forEach(i => { i.checked = true; });
                document.getElementById('fld-invoice').value = 'INV-2026-0117';
                document.getElementById('fld-nasabah').value = 'PT Arunika Ekspor';
                document.getElementById('fld-nominal').value = '25000.00';
                document.getElementById('fld-matauang').value = 'USD';
                document.getElementById('fld-layanan').value = 'LC-IMP';
                document.getElementById('hasil-simulasi').innerHTML = '<p class="rounded-2xl bg-white p-5 text-sm text-zinc-500 shadow-sm ring-1 ring-zinc-950/5">Hasil akan tampil di sini setelah tombol Periksa ditekan. Pada sistem sungguhan, hasil ini menjadi bahan pertimbangan operator — keputusan akhir selalu di tangan operator.</p>';
            });
        </script>
    </body>
</html>
