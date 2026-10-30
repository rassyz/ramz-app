<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 7: log aktivitas (KF-14; entitas LogAktivitas pada dokumen
     * patokan). Baris log tidak pernah diubah — hanya created_at.
     * Di sinilah pula riwayat lengkap perubahan status pembayaran
     * dicatat (konteks berisi status dari dan ke), melengkapi kolom
     * diubah_oleh pada pembayarans sejak Fase 4.
     */
    public function up(): void
    {
        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pengajuan_id')->nullable()->constrained('pengajuans')->nullOnDelete();
            $table->string('aktivitas')->index();
            $table->text('deskripsi');
            $table->json('konteks')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
    }
};
