<?php

namespace App\Enums;

/**
 * Status pengiriman sebuah notifikasi. "Menunggu" mencakup antre dan
 * sedang dicoba ulang (KF-12); "Gagal" hanya untuk kegagalan final
 * (percobaan habis atau penerima tidak dapat dikirimi sama sekali).
 */
enum StatusNotifikasi: string
{
    case Menunggu = 'menunggu';
    case Terkirim = 'terkirim';
    case Gagal = 'gagal';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu',
            self::Terkirim => 'Terkirim',
            self::Gagal => 'Gagal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Menunggu => 'zinc',
            self::Terkirim => 'green',
            self::Gagal => 'red',
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
