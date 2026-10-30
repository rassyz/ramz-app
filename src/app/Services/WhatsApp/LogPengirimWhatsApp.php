<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Implementasi gerbang WhatsApp untuk lingkungan pengembangan:
 * pesan dicatat ke log aplikasi lalu dianggap terkirim — pola yang
 * sama dengan MAIL_MAILER=log untuk email. Lingkungan ini belum
 * memiliki akun WhatsApp Business API; saluran sungguhan dipasang
 * dengan mengikat implementasi lain pada antarmuka PengirimWhatsApp.
 */
class LogPengirimWhatsApp implements PengirimWhatsApp
{
    public function kirim(string $nomorTujuan, string $judul, string $isi): void
    {
        Log::info('Notifikasi WhatsApp (saluran log)', [
            'tujuan' => $nomorTujuan,
            'judul' => $judul,
            'isi' => $isi,
        ]);
    }
}
