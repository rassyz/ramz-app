<?php

namespace App\Enums;

/**
 * Status keseluruhan satu kali pemeriksaan oleh Rule-Based
 * Evidence Checker terhadap sebuah pengajuan.
 */
enum StatusHasilPemeriksaan: string
{
    case Sesuai = 'sesuai';
    case TidakSesuai = 'tidak_sesuai';
    case BelumDapatDiperiksa = 'belum_dapat_diperiksa';

    public function label(): string
    {
        return match ($this) {
            self::Sesuai => 'Sesuai',
            self::TidakSesuai => 'Tidak Sesuai',
            self::BelumDapatDiperiksa => 'Belum Dapat Diperiksa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sesuai => 'green',
            self::TidakSesuai => 'red',
            self::BelumDapatDiperiksa => 'zinc',
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
