<x-layouts::app :title="__('Dashboard')">
    @php
        $user = auth()->user();

        // Cakupan data mengikuti peran: nasabah hanya pengajuannya
        // sendiri, admin & operator seluruh pengajuan (fondasi KF-09).
        $queryPengajuan = $user->isNasabah()
            ? \App\Models\Pengajuan::where('user_id', $user->id)
            : \App\Models\Pengajuan::query();

        $totalPengajuan = (clone $queryPengajuan)->count();
        $menungguVerifikasi = (clone $queryPengajuan)
            ->where('status', \App\Enums\StatusPengajuan::MenungguVerifikasi)
            ->count();

        // Kartu ketiga: nasabah memantau yang perlu perbaikan,
        // staf memantau yang sudah selesai.
        if ($user->isNasabah()) {
            $labelKetiga = __('Perlu Perbaikan');
            $nilaiKetiga = (clone $queryPengajuan)
                ->where('status', \App\Enums\StatusPengajuan::PerluPerbaikan)
                ->count();
        } else {
            $labelKetiga = __('Selesai');
            $nilaiKetiga = (clone $queryPengajuan)
                ->where('status', \App\Enums\StatusPengajuan::Selesai)
                ->count();
        }

        $pengajuanTerbaru = (clone $queryPengajuan)
            ->with(['layanan', 'user', 'pembayaran'])
            ->latest()
            ->limit(5)
            ->get();
    @endphp

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div>
            <flux:heading level="1" size="xl">{{ __('Dashboard') }}</flux:heading>
            <flux:subheading>
                {{ __('Selamat datang, :name.', ['name' => $user->name]) }}
            </flux:subheading>
            <div class="mt-2">
                <flux:badge color="blue">{{ $user->role->label() }}</flux:badge>
            </div>
            <flux:text class="mt-2">
                @if ($user->isAdmin())
                    {{ __('Anda masuk sebagai Admin: pantau seluruh pengajuan, kelola layanan, dan kelola pengguna dari menu di samping.') }}
                @elseif ($user->isOperator())
                    {{ __('Anda masuk sebagai Operator: pantau antrean pengajuan dari nasabah melalui menu Pengajuan.') }}
                @else
                    {{ __('Anda masuk sebagai Nasabah: buat pengajuan baru dan pantau statusnya melalui menu Pengajuan Saya.') }}
                @endif
            </flux:text>
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
                <flux:text>{{ $user->isNasabah() ? __('Total Pengajuan Saya') : __('Total Pengajuan') }}</flux:text>
                <flux:heading level="2" size="xl" class="mt-1">{{ $totalPengajuan }}</flux:heading>
            </div>
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
                <flux:text>{{ __('Menunggu Verifikasi') }}</flux:text>
                <flux:heading level="2" size="xl" class="mt-1">{{ $menungguVerifikasi }}</flux:heading>
            </div>
            <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
                <flux:text>{{ $labelKetiga }}</flux:text>
                <flux:heading level="2" size="xl" class="mt-1">{{ $nilaiKetiga }}</flux:heading>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading level="2">{{ __('Pengajuan Terbaru') }}</flux:heading>

            @if ($pengajuanTerbaru->isEmpty())
                <flux:text class="mt-3 block">{{ __('Belum ada pengajuan.') }}</flux:text>
            @else
                <flux:table class="mt-3">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Nomor Pengajuan') }}</flux:table.column>
                        @unless ($user->isNasabah())
                            <flux:table.column>{{ __('Nasabah') }}</flux:table.column>
                        @endunless
                        <flux:table.column>{{ __('Layanan') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($pengajuanTerbaru as $pengajuan)
                            <flux:table.row :key="$pengajuan->id">
                                <flux:table.cell class="font-medium">{{ $pengajuan->nomor_pengajuan }}</flux:table.cell>
                                @unless ($user->isNasabah())
                                    <flux:table.cell>{{ $pengajuan->user->name }}</flux:table.cell>
                                @endunless
                                <flux:table.cell>{{ $pengajuan->layanan->nama }}</flux:table.cell>
                                <flux:table.cell><flux:badge :color="$pengajuan->status->color()">{{ $pengajuan->status->label() }}</flux:badge></flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex justify-end">
                                        <flux:button size="sm" :href="route('pengajuans.show', $pengajuan)">{{ __('Detail') }}</flux:button>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </div>
</x-layouts::app>
