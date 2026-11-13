<?php

namespace App\Services;

use App\Enums\KanalNotifikasi;
use App\Enums\KeputusanVerifikasi;
use App\Enums\StatusNotifikasi;
use App\Enums\UserRole;
use App\Models\Notifikasi;
use App\Models\Pengajuan;
use App\Models\User;
use App\Models\VerifikasiDokumen;

/**
 * Titik tunggal pembuatan notifikasi (KF-11). Setiap kejadian membuat
 * satu record per kanal untuk tiap penerima lalu mengantrekannya ke
 * KirimNotifikasiJob (KF-12). Pengecualian gagal-cepat: WhatsApp ke
 * pengguna tanpa nomor HP langsung berstatus gagal tanpa antre —
 * nomor yang tidak ada bukan kegagalan yang dapat diobati retry.
 */
class NotifikasiService
{
    public function kirimKe(User $penerima, ?Pengajuan $pengajuan, string $kejadian, string $judul, string $isi): void
    {
        foreach (KanalNotifikasi::cases() as $kanal) {
            $notifikasi = Notifikasi::create([
                'user_id' => $penerima->id,
                'pengajuan_id' => $pengajuan?->id,
                'kanal' => $kanal,
                'kejadian' => $kejadian,
                'judul' => $judul,
                'isi' => $isi,
                'status' => StatusNotifikasi::Menunggu,
            ]);

            if ($kanal === KanalNotifikasi::WhatsApp && blank($penerima->nomor_hp)) {
                $notifikasi->update([
                    'status' => StatusNotifikasi::Gagal,
                    'keterangan' => 'Nomor HP belum diisi — pengiriman WhatsApp dilewati.',
                ]);

                continue;
            }

            dispatch(app()->make('App\\Jobs\\KirimNotifikasiJob', [
                'notifikasi' => $notifikasi,
            ]));
        }
    }

    /**
     * Pengajuan baru: konfirmasi untuk nasabah, pemberitahuan untuk
     * seluruh operator yang bertugas memverifikasi.
     */
    public function pengajuanDibuat(Pengajuan $pengajuan): void
    {
        $pengajuan->loadMissing(['user', 'layanan']);

        $this->kirimKe(
            $pengajuan->user,
            $pengajuan,
            'pengajuan_dibuat',
            'Pengajuan Anda Diterima',
            "Pengajuan {$pengajuan->nomor_pengajuan} untuk layanan {$pengajuan->layanan->nama} telah diterima dan menunggu verifikasi operator."
        );

        foreach (User::query()->where('role', UserRole::Operator)->get() as $operator) {
            $this->kirimKe(
                $operator,
                $pengajuan,
                'pengajuan_dibuat',
                'Pengajuan Baru Menunggu Verifikasi',
                "Pengajuan {$pengajuan->nomor_pengajuan} dari {$pengajuan->user->name} untuk layanan {$pengajuan->layanan->nama} menunggu verifikasi."
            );
        }
    }

    /**
     * Keputusan verifikasi operator untuk nasabah pemilik pengajuan.
     */
    public function keputusanVerifikasi(Pengajuan $pengajuan, VerifikasiDokumen $verifikasi): void
    {
        $pengajuan->loadMissing('user');

        if ($verifikasi->keputusan === KeputusanVerifikasi::PerluPerbaikan) {
            $judul = 'Pengajuan Perlu Perbaikan';
            $isi = "Pengajuan {$pengajuan->nomor_pengajuan} dikembalikan untuk diperbaiki. Catatan operator: {$verifikasi->catatan} Silakan perbaiki dokumen Anda lalu ajukan kembali.";
        } else {
            $judul = 'Pengajuan Terverifikasi';
            $isi = "Pengajuan {$pengajuan->nomor_pengajuan} telah terverifikasi oleh operator dan akan diproses lebih lanjut.";
        }

        $this->kirimKe($pengajuan->user, $pengajuan, 'keputusan_verifikasi', $judul, $isi);
    }

    /**
     * Pengajuan diajukan kembali oleh nasabah: pemberitahuan operator.
     */
    public function diajukanKembali(Pengajuan $pengajuan): void
    {
        $pengajuan->loadMissing('user');

        foreach (User::query()->where('role', UserRole::Operator)->get() as $operator) {
            $this->kirimKe(
                $operator,
                $pengajuan,
                'pengajuan_diajukan_kembali',
                'Pengajuan Diajukan Kembali',
                "Pengajuan {$pengajuan->nomor_pengajuan} dari {$pengajuan->user->name} telah diajukan kembali setelah perbaikan dan menunggu verifikasi."
            );
        }
    }

    /**
     * Perubahan status pengajuan (diproses, selesai, dibatalkan)
     * untuk nasabah pemilik pengajuan.
     */
    public function statusBerubah(Pengajuan $pengajuan): void
    {
        $pengajuan->loadMissing('user');

        $this->kirimKe(
            $pengajuan->user,
            $pengajuan,
            'status_berubah',
            'Status Pengajuan Berubah: ' . $pengajuan->status->label(),
            "Status pengajuan {$pengajuan->nomor_pengajuan} sekarang adalah {$pengajuan->status->label()}."
        );
    }

    /**
     * Perubahan status pembayaran untuk nasabah pemilik pengajuan.
     */
    public function pembayaranBerubah(Pengajuan $pengajuan): void
    {
        $pengajuan->loadMissing('user');
        $pengajuan->load('pembayaran');

        $this->kirimKe(
            $pengajuan->user,
            $pengajuan,
            'pembayaran_berubah',
            'Status Pembayaran Berubah: ' . $pengajuan->pembayaran->status->label(),
            "Status pembayaran pengajuan {$pengajuan->nomor_pengajuan} sekarang adalah {$pengajuan->pembayaran->status->label()}."
        );
    }
}
