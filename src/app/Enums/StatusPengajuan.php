<?php

namespace App\Enums;

enum StatusPengajuan: string
{
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case PerluPerbaikan = 'perlu_perbaikan';
    case Terverifikasi = 'terverifikasi';
    case Diproses = 'diproses';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::MenungguVerifikasi => 'Menunggu Verifikasi',
            self::PerluPerbaikan => 'Perlu Perbaikan',
            self::Terverifikasi => 'Terverifikasi',
            self::Diproses => 'Diproses',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /**
     * Nama warna badge Flux untuk status ini.
     */
    public function color(): string
    {
        return match ($this) {
            self::MenungguVerifikasi => 'amber',
            self::PerluPerbaikan => 'red',
            self::Terverifikasi => 'blue',
            self::Diproses => 'violet',
            self::Selesai => 'green',
            self::Dibatalkan => 'zinc',
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
