<x-layouts::auth :title="__('Masuk')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Masuk ke Ramz App')" :description="__('Gunakan akun yang dibuat oleh Admin.')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        {{-- @chisel-passkeys --}}
        <x-passkey-verify />
        {{-- @end-chisel-passkeys --}}

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Kata Sandi')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Kata Sandi')"
                viewable
            />

            <!-- Remember Me + lupa kata sandi -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:checkbox name="remember" :label="__('Ingat saya')" :checked="old('remember')" />

                @if (Route::has('password.request'))
                    <flux:link class="text-sm" :href="route('password.request')" wire:navigate>
                        {{ __('Lupa kata sandi?') }}
                    </flux:link>
                @endif
            </div>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Masuk') }}
                </flux:button>
            </div>
        </form>

        <p class="text-center text-[12.5px] text-zinc-500 dark:text-zinc-400">
            Tidak ada pendaftaran publik — akun nasabah dibuat oleh Admin saat LC terbit.
        </p>

        {{-- @chisel-registration --}}
        {{-- <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div> --}}
        {{-- @end-chisel-registration --}}
    </div>
</x-layouts::auth>
