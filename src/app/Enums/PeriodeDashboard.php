<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * Pilihan periode tampilan dashboard monitoring (KF-09) dan nilai
 * yang dapat disimpan sebagai periode bawaan personalisasi (KF-10).
 */
enum PeriodeDashboard: string
{
    case TujuhHari = '7_hari';
    case TigaPuluhHari = '30_hari';
    case BulanIni = 'bulan_ini';
    case BulanLalu = 'bulan_lalu';
    case Semua = 'semua';

    public function label(): string
    {
        return match ($this) {
            self::TujuhHari => '7 Hari Terakhir',
            self::TigaPuluhHari => '30 Hari Terakhir',
            self::BulanIni => 'Bulan Ini',
            self::BulanLalu => 'Bulan Lalu',
            self::Semua => 'Semua Waktu',
        };
    }

    /**
     * Rentang tanggal [mulai, selesai] untuk periode ini.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function rentang(): array
    {
        return match ($this) {
            self::TujuhHari => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            self::TigaPuluhHari => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            self::BulanIni => [now()->startOfMonth(), now()->endOfMonth()],
            self::BulanLalu => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            self::Semua => [Carbon::create(2000, 1, 1)->startOfDay(), now()->endOfDay()],
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
