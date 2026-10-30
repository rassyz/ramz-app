<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Admin: Kelola pengguna dan hak akses
    Route::livewire('admin/users', 'pages::admin.users')
        ->middleware('role:admin')
        ->name('admin.users');
});

require __DIR__ . '/settings.php';
