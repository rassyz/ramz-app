<?php

namespace App\Enums;

/**
 * Hasil pemeriksaan per aturan (per baris detail pemeriksaan).
 */
enum HasilDetail: string
{
    case Sesuai = 'sesuai';
    case TidakSesuai = 'tidak_sesuai';
    case TidakDapatDiperiksa = 'tidak_dapat_diperiksa';

    public function label(): string
    {
        return match ($this) {
            self::Sesuai => 'Sesuai',
            self::TidakSesuai => 'Tidak Sesuai',
            self::TidakDapatDiperiksa => 'Tidak Dapat Diperiksa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sesuai => 'green',
            self::TidakSesuai => 'red',
            self::TidakDapatDiperiksa => 'zinc',
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
