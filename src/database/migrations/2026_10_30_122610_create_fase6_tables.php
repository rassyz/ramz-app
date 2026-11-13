<?php

use App\Enums\KanalNotifikasi;
use App\Enums\StatusNotifikasi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 6: notifikasi WhatsApp & email (KF-11).
     * - users.nomor_hp: tujuan pengiriman WhatsApp.
     * - notifikasis: satu record per penerima per kanal per kejadian,
     *   dengan siklus status pengiriman dan hitungan percobaan (KF-12).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nomor_hp', 20)->nullable()->after('email');
        });

        Schema::create('notifikasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pengajuan_id')->nullable()->constrained('pengajuans')->nullOnDelete();
            $table->enum('kanal', KanalNotifikasi::values());
            $table->string('kejadian');
            $table->string('judul');
            $table->text('isi');
            $table->enum('status', StatusNotifikasi::values())->default(StatusNotifikasi::Menunggu->value);
            $table->unsignedInteger('jumlah_percobaan')->default(0);
            $table->timestamp('terkirim_at')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamp('dibaca_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasis');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nomor_hp');
        });
    }
};
