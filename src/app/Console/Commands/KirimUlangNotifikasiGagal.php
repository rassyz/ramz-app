<?php

namespace App\Console\Commands;

use App\Enums\KanalNotifikasi;
use App\Enums\StatusNotifikasi;
use App\Jobs\KirimNotifikasiJob;
use App\Models\Notifikasi;
use Illuminate\Console\Command;

/**
 * Jalur pemulihan KF-12: mengantrekan kembali seluruh notifikasi
 * berstatus gagal setelah penyebab kegagalan diperbaiki. Notifikasi
 * WhatsApp ke pengguna yang tetap belum memiliki nomor HP dilewati
 * (akan gagal-cepat lagi tanpa manfaat).
 */
class KirimUlangNotifikasiGagal extends Command
{
    protected $signature = 'notifikasi:kirim-ulang-gagal';

    protected $description = 'Antrekan kembali seluruh notifikasi yang berstatus gagal';

    public function handle(): int
    {
        $dikirimUlang = 0;
        $dilewati = 0;

        Notifikasi::query()
            ->with('user')
            ->where('status', StatusNotifikasi::Gagal)
            ->each(function (Notifikasi $notifikasi) use (&$dikirimUlang, &$dilewati) {
                if ($notifikasi->kanal === KanalNotifikasi::WhatsApp && blank($notifikasi->user->nomor_hp)) {
                    $dilewati++;

                    return;
                }

                $notifikasi->update([
                    'status' => StatusNotifikasi::Menunggu,
                    'jumlah_percobaan' => 0,
                    'keterangan' => null,
                ]);

                KirimNotifikasiJob::dispatch($notifikasi);
                $dikirimUlang++;
            });

        $this->info("Notifikasi diantrekan ulang: {$dikirimUlang}; dilewati (nomor HP kosong): {$dilewati}.");
        $this->info('Pengiriman berjalan melalui antrean — pastikan proses queue:work sedang berjalan.');

        return self::SUCCESS;
    }
}
