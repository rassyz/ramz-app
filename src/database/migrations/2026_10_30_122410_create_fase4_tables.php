<?php

use App\Enums\KeputusanVerifikasi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 4: tabel verifikasi_dokumens (entitas VerifikasiDokumen pada
     * dokumen patokan, KF-07) dan kolom diubah_oleh pada pembayarans
     * untuk akuntabilitas perubahan status pembayaran oleh operator.
     */
    public function up(): void
    {
        Schema::create('verifikasi_dokumens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuans')->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hasil_pemeriksaan_id')->nullable()->constrained('hasil_pemeriksaans')->nullOnDelete();
            $table->enum('keputusan', KeputusanVerifikasi::values());
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::table('pembayarans', function (Blueprint $table) {
            $table->foreignId('diubah_oleh')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pembayarans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diubah_oleh');
        });

        Schema::dropIfExists('verifikasi_dokumens');
    }
};
