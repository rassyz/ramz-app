<?php

use App\Enums\PeriodeDashboard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 5: preferensi personalisasi dashboard per pengguna
     * (KF-10; entitas PreferensiDashboard pada dokumen patokan).
     * Satu record untuk setiap pengguna yang pernah menyimpan
     * preferensi; tanpa record berarti memakai nilai bawaan.
     */
    public function up(): void
    {
        Schema::create('preferensi_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('tampilkan_tren')->default(true);
            $table->boolean('tampilkan_distribusi_status')->default(true);
            $table->boolean('tampilkan_per_layanan')->default(true);
            $table->boolean('tampilkan_pemeriksaan')->default(true);
            $table->boolean('tampilkan_antrean')->default(true);
            $table->enum('periode_default', PeriodeDashboard::values())->default(PeriodeDashboard::TigaPuluhHari->value);
            $table->unsignedInteger('batas_terbaru')->default(5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferensi_dashboards');
    }
};
