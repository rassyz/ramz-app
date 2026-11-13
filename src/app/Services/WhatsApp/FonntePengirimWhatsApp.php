<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Implementasi gerbang WhatsApp sungguhan via Fonnte (KF-11).
 *
 * Mengirim pesan teks ke API Fonnte (POST {base_url}/send, header
 * Authorization berisi token device, tanpa awalan Bearer). Nomor
 * tujuan dinormalisasi sendiri ke format internasional Indonesia
 * (62...) lalu Fonnte diminta tidak menulis ulang nomor itu dengan
 * countryCode=0.
 *
 * Fonnte menjawab HTTP 200 sekalipun pengiriman ditolak; penolakan
 * dibaca dari field "status"/"reason" pada badan JSON. Setiap
 * kegagalan dilempar sebagai pengecualian agar KirimNotifikasiJob
 * dapat mencatat alasannya dan memutuskan percobaan ulang (KF-12),
 * persis kontrak antarmuka PengirimWhatsApp.
 */
class FonntePengirimWhatsApp implements PengirimWhatsApp
{
    public function kirim(string $nomorTujuan, string $judul, string $isi): void
    {
        $token = (string) config('services.whatsapp.fonnte.token', '');

        if (blank($token)) {
            throw new RuntimeException('Token Fonnte belum diatur: isi FONNTE_TOKEN pada .env lalu muat ulang aplikasi.');
        }

        $target = $this->normalisasiNomor($nomorTujuan);

        $pesan = "*{$judul}*\n\n{$isi}";

        $baseUrl = rtrim((string) config('services.whatsapp.fonnte.base_url', 'https://api.fonnte.com'), '/');

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->timeout(15)->asForm()->post("{$baseUrl}/send", [
                'target' => $target,
                'message' => $pesan,
                'countryCode' => '0',
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Gagal menghubungi Fonnte: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException("Fonnte menjawab HTTP {$response->status()} saat mengirim WhatsApp ke {$target}.");
        }

        $hasil = $response->json();

        if (! is_array($hasil) || ($hasil['status'] ?? false) !== true) {
            $alasan = is_array($hasil) && ! blank($hasil['reason'] ?? null)
                ? (string) $hasil['reason']
                : 'alasan tidak diketahui';

            throw new RuntimeException("Fonnte menolak pengiriman WhatsApp ke {$target}: {$alasan}.");
        }

        Log::info('Notifikasi WhatsApp terkirim via Fonnte', [
            'tujuan' => $target,
            'judul' => $judul,
            'id_fonnte' => $hasil['id'] ?? null,
        ]);
    }

    /**
     * Samakan nomor HP Indonesia ke format 62...: buang semua karakter
     * selain digit, ubah awalan 0 menjadi 62, dan tambahkan 62 pada
     * nomor yang ditulis langsung mulai dari 8.
     */
    private function normalisasiNomor(string $nomor): string
    {
        $digit = preg_replace('/\D+/', '', $nomor) ?? '';

        if (str_starts_with($digit, '0')) {
            $digit = '62'.substr($digit, 1);
        } elseif (str_starts_with($digit, '8')) {
            $digit = '62'.$digit;
        }

        if (! str_starts_with($digit, '62') || strlen($digit) < 11) {
            throw new InvalidArgumentException("Nomor tujuan WhatsApp tidak valid: {$nomor}.");
        }

        return $digit;
    }
}
