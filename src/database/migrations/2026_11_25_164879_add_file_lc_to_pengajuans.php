<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 11 Perbaikan 3 — lampiran dokumen LC pada pengajuan: berkas
 * LC asli (acuan) yang diunggah staf dan dapat diunduh nasabah.
 * LC adalah patokan, BUKAN dokumen yang diperiksa checker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->string('path_file_lc')->nullable()->after('tanggal_kedaluwarsa_lc');
            $table->string('nama_file_lc')->nullable()->after('path_file_lc');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->dropColumn(['path_file_lc', 'nama_file_lc']);
        });
    }
};
