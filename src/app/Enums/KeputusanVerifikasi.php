<?php

namespace App\Enums;

/**
 * Keputusan verifikasi dokumen oleh Operator (KF-07): pengajuan
 * dinyatakan terverifikasi, atau dikembalikan untuk perbaikan.
 * Nilainya selaras dengan StatusPengajuan tujuan.
 */
enum KeputusanVerifikasi: string
{
    case Terverifikasi = 'terverifikasi';
    case PerluPerbaikan = 'perlu_perbaikan';

    public function label(): string
    {
        return match ($this) {
            self::Terverifikasi => 'Terverifikasi',
            self::PerluPerbaikan => 'Perlu Perbaikan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Terverifikasi => 'blue',
            self::PerluPerbaikan => 'red',
        };
    }

    /**
     * Status pengajuan yang dihasilkan keputusan ini.
     */
    public function statusPengajuan(): StatusPengajuan
    {
        return match ($this) {
            self::Terverifikasi => StatusPengajuan::Terverifikasi,
            self::PerluPerbaikan => StatusPengajuan::PerluPerbaikan,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
