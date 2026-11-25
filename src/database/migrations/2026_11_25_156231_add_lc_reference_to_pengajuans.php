<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 11 — model LC sebenarnya:
     * - pengajuans: data acuan LC (patokan checker) + snapshot
     *   persyaratan dokumen per pengajuan (wujud klausul 46A MT700).
     * - dokumens: field terstruktur untuk semua dokumen wajib.
     * - aturan_validasis: aturan menjadi per jenis dokumen + referensi.
     */
    public function up(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->string('nomor_lc')->nullable()->after('nomor_invoice');
            $table->string('nama_applicant')->nullable()->after('nomor_lc');
            $table->string('nomor_po')->nullable()->after('nama_applicant');
            $table->decimal('nominal_acuan', 18, 2)->nullable()->after('nomor_po');
            $table->string('mata_uang_acuan', 3)->nullable()->after('nominal_acuan');
            $table->decimal('toleransi_persen', 5, 2)->nullable()->after('mata_uang_acuan');
            $table->decimal('kuantitas_acuan', 18, 3)->nullable()->after('toleransi_persen');
            $table->string('satuan_acuan')->nullable()->after('kuantitas_acuan');
            $table->date('tanggal_terbit_lc')->nullable()->after('satuan_acuan');
            $table->date('tanggal_kedaluwarsa_lc')->nullable()->after('tanggal_terbit_lc');
            $table->json('persyaratan_dokumen')->nullable()->after('tanggal_kedaluwarsa_lc');
        });

        Schema::table('dokumens', function (Blueprint $table) {
            $table->string('doc_nomor_dokumen')->nullable()->after('doc_jenis_layanan');
            $table->date('doc_tanggal_dokumen')->nullable()->after('doc_nomor_dokumen');
            $table->string('doc_nomor_lc')->nullable()->after('doc_tanggal_dokumen');
            $table->string('doc_nomor_po')->nullable()->after('doc_nomor_lc');
            $table->decimal('doc_kuantitas', 18, 3)->nullable()->after('doc_nomor_po');
            $table->string('doc_satuan')->nullable()->after('doc_kuantitas');
            $table->string('doc_identitas_pengirim')->nullable()->after('doc_satuan');
        });

        Schema::table('aturan_validasis', function (Blueprint $table) {
            $table->string('jenis_dokumen')->nullable()->after('field');
            $table->string('referensi')->nullable()->after('jenis_dokumen');
        });
    }

    public function down(): void
    {
        Schema::table('aturan_validasis', function (Blueprint $table) {
            $table->dropColumn(['jenis_dokumen', 'referensi']);
        });

        Schema::table('dokumens', function (Blueprint $table) {
            $table->dropColumn([
                'doc_nomor_dokumen', 'doc_tanggal_dokumen', 'doc_nomor_lc',
                'doc_nomor_po', 'doc_kuantitas', 'doc_satuan', 'doc_identitas_pengirim',
            ]);
        });

        Schema::table('pengajuans', function (Blueprint $table) {
            $table->dropColumn([
                'nomor_lc', 'nama_applicant', 'nomor_po', 'nominal_acuan',
                'mata_uang_acuan', 'toleransi_persen', 'kuantitas_acuan',
                'satuan_acuan', 'tanggal_terbit_lc', 'tanggal_kedaluwarsa_lc',
                'persyaratan_dokumen',
            ]);
        });
    }
};
