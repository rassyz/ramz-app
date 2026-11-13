<?php

namespace App\Services\WhatsApp;

/**
 * Gerbang pengiriman WhatsApp (KF-11). Seluruh pengiriman WhatsApp
 * melewati antarmuka ini agar penyedia sungguhan (mis. WhatsApp
 * Business API) dapat dipasang kemudian dengan mengganti satu
 * implementasi dan kredensialnya, tanpa mengubah pemanggil.
 *
 * Implementasi melempar pengecualian bila pengiriman gagal; pemanggil
 * (KirimNotifikasiJob) yang memutuskan percobaan ulang (KF-12).
 */
interface PengirimWhatsApp
{
    public function kirim(string $nomorTujuan, string $judul, string $isi): void;
}
