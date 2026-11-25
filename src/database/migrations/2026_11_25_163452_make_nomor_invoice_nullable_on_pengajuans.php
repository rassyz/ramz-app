<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 11 (penyesuaian alur): nomor invoice pada pengajuan menjadi
     * boleh kosong. Pada alur sebenarnya invoice baru terbit setelah
     * LC turun dan barang diserahkan — operator membuat pengajuan
     * cukup dengan nasabah + layanan, lalu nomor invoice dilengkapi
     * menyusul dari dokumen invoice yang diterima (form Data Acuan).
     */
    public function up(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->string('nomor_invoice')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->string('nomor_invoice')->nullable(false)->change();
        });
    }
};
