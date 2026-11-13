<?php

use App\Enums\MataUang;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPengajuan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel-tabel Fase 2 sesuai rancangan basis data dokumen patokan:
     * Layanan, Pengajuan (entitas pusat), Pembayaran, dan RiwayatStatus.
     * Digabung dalam satu migration agar urutan foreign key aman.
     */
    public function up(): void
    {
        Schema::create('layanans', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            // Daftar syarat dokumen: [{"nama": "Invoice", "wajib": true}, ...]
            $table->json('persyaratan_dokumen')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pengajuans', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pengajuan')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('layanan_id')->constrained('layanans')->restrictOnDelete();
            $table->string('nomor_invoice');
            $table->enum('status', StatusPengajuan::values())->default(StatusPengajuan::MenungguVerifikasi->value);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('pembayarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuans')->cascadeOnDelete();
            $table->decimal('nominal', 15, 2);
            $table->enum('mata_uang', MataUang::values())->default(MataUang::USD->value);
            $table->date('tanggal_pembayaran')->nullable();
            $table->enum('status', StatusPembayaran::values())->default(StatusPembayaran::MenungguPembayaran->value);
            $table->timestamps();
        });

        Schema::create('riwayat_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuans')->cascadeOnDelete();
            $table->enum('status_dari', StatusPengajuan::values())->nullable();
            $table->enum('status_ke', StatusPengajuan::values());
            $table->text('catatan')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_statuses');
        Schema::dropIfExists('pembayarans');
        Schema::dropIfExists('pengajuans');
        Schema::dropIfExists('layanans');
    }
};
