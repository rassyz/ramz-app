<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-8 rounded-[14px] bg-red-50 p-4 dark:bg-[#331314]">
    <div class="relative mb-3.5">
        <flux:heading>{{ __('Delete account') }}</flux:heading>
        <flux:subheading>{{ __('Delete your account and all of its resources') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" data-test="delete-user-button">
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>

    <livewire:pages::settings.delete-user-modal />
</section>
