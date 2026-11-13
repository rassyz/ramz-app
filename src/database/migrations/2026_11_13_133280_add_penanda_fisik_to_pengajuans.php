<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda tahap penyetaraan dokumen fisik (Fase 10, D5):
     * dokumen fisik diterima staf dan disetarakan dengan data
     * masukan nasabah — informatif, bukan gerbang verifikasi.
     */
    public function up(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->timestamp('dokumen_fisik_diterima_at')->nullable()->after('catatan');
            $table->foreignId('disetarakan_oleh')->nullable()->after('dokumen_fisik_diterima_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disetarakan_oleh');
            $table->dropColumn('dokumen_fisik_diterima_at');
        });
    }
};
