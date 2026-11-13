<?php

namespace App\Enums;

/**
 * Mata uang yang didukung untuk data pembayaran internasional.
 */
enum MataUang: string
{
    case IDR = 'IDR';
    case USD = 'USD';
    case SGD = 'SGD';
    case EUR = 'EUR';
    case JPY = 'JPY';
    case CNY = 'CNY';
    case AUD = 'AUD';
    case GBP = 'GBP';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
