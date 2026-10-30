<?php

namespace App\Enums;

/**
 * Kategori aturan pemeriksaan pada Rule-Based Evidence Checker:
 * kelengkapan dokumen dan pencocokan data terstruktur.
 */
enum KategoriAturan: string
{
    case Kelengkapan = 'kelengkapan';
    case Pencocokan = 'pencocokan';

    public function label(): string
    {
        return match ($this) {
            self::Kelengkapan => 'Kelengkapan',
            self::Pencocokan => 'Pencocokan',
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
