<?php

namespace App\Jobs;

use App\Enums\KanalNotifikasi;
use App\Enums\StatusNotifikasi;
use App\Models\Notifikasi;
use App\Services\WhatsApp\PengirimWhatsApp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Pengiriman satu notifikasi pada satu kanal (KF-11) dengan
 * percobaan ulang berjenjang (KF-12): maksimal 4 kali percobaan
 * (1 awal + 3 ulang) dengan jeda membesar 60, 300, lalu 1800 detik
 * (exponential backoff) yang dikelola antrean Laravel.
 *
 * Setiap percobaan tercatat pada record notifikasinya; status tetap
 * "menunggu" selama masih akan dicoba ulang, dan hook failed()
 * menandai kegagalan final setelah percobaan habis.
 */
class KirimNotifikasiJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 1800];

    public function __construct(
        public Notifikasi $notifikasi,
    ) {}

    public function handle(PengirimWhatsApp $pengirimWhatsApp): void
    {
        $notifikasi = $this->notifikasi;
        $penerima = $notifikasi->user;

        $notifikasi->increment('jumlah_percobaan');

        try {
            if ($notifikasi->kanal === KanalNotifikasi::Email) {
                Mail::raw($notifikasi->isi, function ($message) use ($penerima, $notifikasi): void {
                    $message->to($penerima->email)
                        ->subject($notifikasi->judul);
                });
            } else {
                // Pertahanan berlapis: nomor kosong seharusnya sudah
                // disaring NotifikasiService sebelum pengantrean.
                if (blank($penerima->nomor_hp)) {
                    $notifikasi->update([
                        'status' => StatusNotifikasi::Gagal,
                        'keterangan' => 'Nomor HP belum diisi — pengiriman WhatsApp dilewati.',
                    ]);

                    return;
                }

                $pengirimWhatsApp->kirim($penerima->nomor_hp, $notifikasi->judul, $notifikasi->isi);
            }
        } catch (Throwable $e) {
            $notifikasi->update(['keterangan' => $e->getMessage()]);

            throw $e;
        }

        $notifikasi->update([
            'status' => StatusNotifikasi::Terkirim,
            'terkirim_at' => now(),
            'keterangan' => null,
        ]);
    }

    /**
     * Dipanggil antrean setelah seluruh percobaan habis.
     */
    public function failed(?Throwable $exception): void
    {
        $this->notifikasi->update([
            'status' => StatusNotifikasi::Gagal,
            'keterangan' => $exception?->getMessage() ?? $this->notifikasi->keterangan,
        ]);
    }
}
