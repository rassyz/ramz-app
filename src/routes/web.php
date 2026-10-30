<?php

use App\Http\Controllers\DokumenController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Khusus Admin: pengelolaan pengguna dan hak akses (KF-02).
    Route::livewire('admin/users', 'pages::admin.users')
        ->middleware('role:admin')
        ->name('admin.users');

    // Khusus Admin: master data layanan (KF-03).
    Route::livewire('admin/layanans', 'pages::admin.layanans')
        ->middleware('role:admin')
        ->name('admin.layanans');

    // Khusus Admin: aturan validasi Rule-Based Evidence Checker (KF-06).
    Route::livewire('admin/aturan-validasi', 'pages::admin.aturan-validasi')
        ->middleware('role:admin')
        ->name('admin.aturan-validasi');

    // Unduh berkas dokumen pendukung (KF-05) — akses dijaga DokumenPolicy
    // di dalam controller, berkas disimpan privat bukan URL publik.
    Route::get('dokumens/{dokumen}/download', [DokumenController::class, 'download'])
        ->name('dokumens.download');

    // Khusus Nasabah: pengajuan milik sendiri (KF-03).
    Route::livewire('pengajuan-saya', 'pages::nasabah.pengajuan-saya')
        ->name('nasabah.pengajuan');

    // Admin & Operator: pantauan seluruh pengajuan (KF-03/KF-08).
    // Penegakan peran ganda ada di mount() halaman dan policy.
    Route::livewire('pengajuans', 'pages::pengajuans.index')
        ->name('pengajuans.index');

    // Detail pengajuan: akses per record ditegakkan PengajuanPolicy.
    Route::livewire('pengajuans/{pengajuan}', 'pages::pengajuans.show')
        ->name('pengajuans.show');
});

require __DIR__ . '/settings.php';
