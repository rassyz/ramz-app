<?php

use App\Http\Controllers\DokumenController;
use App\Http\Controllers\LaporanController;
use Illuminate\Support\Facades\Route;

// Landing page publik (Fase 9) — statistik agregat publik saja,
// tanpa data pribadi: layanan aktif, total pengajuan, total pemeriksaan.
Route::get('/', function () {
    return view('landing', [
        'statistik' => [
            'layanan' => \App\Models\Layanan::where('is_active', true)->count(),
            'pengajuan' => \App\Models\Pengajuan::count(),
            'pemeriksaan' => \App\Models\HasilPemeriksaan::count(),
        ],
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard monitoring dinamis + personalisasi (KF-09/KF-10, Fase 5).
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    // Kotak masuk notifikasi (KF-11, Fase 6).
    Route::livewire('notifikasi', 'pages::notifikasis.index')->name('notifikasis.index');

    // Laporan (KF-13) dan Log Aktivitas (KF-14) — khusus Admin (Fase 7).
    Route::middleware('role:admin')->group(function () {
        Route::livewire('admin/laporan', 'pages::admin.laporan')->name('admin.laporan');
        Route::livewire('admin/log-aktivitas', 'pages::admin.log-aktivitas')->name('admin.log-aktivitas');
        Route::get('admin/laporan/unduh', LaporanController::class)->name('admin.laporan.unduh');
    });

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
