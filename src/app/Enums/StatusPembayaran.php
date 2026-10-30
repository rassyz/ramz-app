<?php

namespace App\Enums;

/**
 * Status informasi pembayaran yang terhubung dengan pengajuan.
 * Sistem ini hanya memantau informasi pembayaran, bukan melakukan
 * transaksi keuangan (batasan masalah pada dokumen patokan).
 */
enum StatusPembayaran: string
{
    case MenungguPembayaran = 'menunggu_pembayaran';
    case SudahBayar = 'sudah_bayar';
    case Terverifikasi = 'terverifikasi';

    public function label(): string
    {
        return match ($this) {
            self::MenungguPembayaran => 'Menunggu Pembayaran',
            self::SudahBayar => 'Sudah Bayar',
            self::Terverifikasi => 'Terverifikasi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MenungguPembayaran => 'amber',
            self::SudahBayar => 'blue',
            self::Terverifikasi => 'green',
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
