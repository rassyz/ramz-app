<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="flex flex-col gap-1">
            <flux:heading level="1" size="xl">{{ __('Dashboard') }}</flux:heading>
            <flux:text>
                Halo, {{ auth()->user()->name }}. Anda masuk sebagai
                <flux:badge>{{ auth()->user()->role->label() }}</flux:badge>
            </flux:text>

            @if (auth()->user()->isAdmin())
                <flux:text>Ringkasan seluruh pengajuan layanan, pembayaran, hasil verifikasi, dan log aktivitas akan tampil di sini.</flux:text>
            @elseif (auth()->user()->isOperator())
                <flux:text>Antrean pengajuan yang perlu diperiksa beserta hasil Rule-Based Evidence Checker akan tampil di sini.</flux:text>
            @else
                <flux:text>Status pengajuan layanan dan hasil verifikasi dokumen Anda akan tampil di sini.</flux:text>
            @endif
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        </div>
        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div>
    </div>
</x-layouts::app>
