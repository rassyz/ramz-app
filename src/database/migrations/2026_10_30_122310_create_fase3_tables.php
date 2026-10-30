<?php

use App\Enums\HasilDetail;
use App\Enums\KategoriAturan;
use App\Enums\MataUang;
use App\Enums\StatusHasilPemeriksaan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel-tabel Fase 3 sesuai rancangan basis data dokumen patokan:
     * Dokumen (KF-05) serta AturanValidasi, HasilPemeriksaan, dan
     * DetailPemeriksaan untuk Rule-Based Evidence Checker (KF-06).
     */
    public function up(): void
    {
        Schema::create('dokumens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuans')->cascadeOnDelete();
            $table->string('jenis_dokumen');
            $table->string('nama_file_asli');
            $table->string('path_file');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('ukuran_file')->nullable();
            $table->foreignId('diupload_oleh')->constrained('users')->cascadeOnDelete();
            // Data terstruktur yang tertera pada dokumen (khusus dokumen
            // Invoice), diisi nasabah saat mengunggah — bahan pencocokan
            // checker karena sistem tidak memakai OCR.
            $table->string('doc_nomor_invoice')->nullable();
            $table->string('doc_identitas_nasabah')->nullable();
            $table->decimal('doc_nominal', 15, 2)->nullable();
            $table->enum('doc_mata_uang', MataUang::values())->nullable();
            $table->string('doc_jenis_layanan')->nullable();
            $table->timestamps();

            // Satu dokumen aktif per jenis untuk setiap pengajuan.
            $table->unique(['pengajuan_id', 'jenis_dokumen']);
        });

        Schema::create('aturan_validasis', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->enum('kategori', KategoriAturan::values());
            $table->string('field')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hasil_pemeriksaans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuans')->cascadeOnDelete();
            $table->enum('status', StatusHasilPemeriksaan::values());
            $table->unsignedInteger('total_pemeriksaan')->default(0);
            $table->unsignedInteger('total_sesuai')->default(0);
            $table->unsignedInteger('total_tidak_sesuai')->default(0);
            $table->timestamps();
        });

        Schema::create('detail_pemeriksaans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hasil_pemeriksaan_id')->constrained('hasil_pemeriksaans')->cascadeOnDelete();
            $table->foreignId('aturan_id')->nullable()->constrained('aturan_validasis')->nullOnDelete();
            $table->enum('kategori', KategoriAturan::values());
            $table->string('nama_pemeriksaan');
            $table->text('nilai_pengajuan')->nullable();
            $table->text('nilai_dokumen')->nullable();
            $table->enum('hasil', HasilDetail::values());
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_pemeriksaans');
        Schema::dropIfExists('hasil_pemeriksaans');
        Schema::dropIfExists('aturan_validasis');
        Schema::dropIfExists('dokumens');
    }
};
