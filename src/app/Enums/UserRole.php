<?php

namespace App\Enums;

/**
 * Peran pengguna sesuai dokumen patokan (Tabel 3.3):
 * Admin, Operator, dan Nasabah. Satu pengguna memiliki tepat satu peran.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Nasabah = 'nasabah';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Operator => 'Operator',
            self::Nasabah => 'Nasabah',
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
