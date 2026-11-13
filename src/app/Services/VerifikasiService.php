<?php

namespace App\Services;

use App\Enums\KeputusanVerifikasi;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPengajuan;
use App\Models\LogAktivitas;
use App\Models\Pengajuan;
use App\Models\User;
use App\Models\VerifikasiDokumen;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mesin transisi status & verifikasi pengajuan (KF-07).
 *
 * Seluruh perubahan status pengajuan melewati kelas ini agar peta
 * transisi yang sah ditegakkan di satu tempat:
 *
 *   menunggu_verifikasi -> terverifikasi | perlu_perbaikan
 *                          (hanya lewat keputusan verifikasi)
 *   perlu_perbaikan     -> menunggu_verifikasi
 *                          (hanya lewat pengajuan kembali oleh nasabah)
 *   terverifikasi       -> diproses        (operator)
 *   diproses            -> selesai         (operator)
 *   menunggu_verifikasi -> dibatalkan      (nasabah, sejak Fase 2
 *                          ditangani langsung di halaman detail)
 *
 * Setiap transisi menulis baris riwayat_statuses beserta pelakunya.
 * Otorisasi peran ditegakkan PengajuanPolicy di lapisan halaman;
 * kelas ini menegakkan kesahihan transisinya.
 */
class VerifikasiService
{
    /**
     * Notifikasi (KF-11) dipicu dari titik terpusat ini (Fase 6):
     * setiap keputusan dan perubahan status yang melewati service
     * ini otomatis menghasilkan pemberitahuan untuk penerimanya.
     */
    public function __construct(
        private NotifikasiService $notifikasi,
        private LogAktivitasService $log,
    ) {}
    /**
     * Mencatat keputusan verifikasi operator dan memindahkan status
     * pengajuan sesuai keputusan, dalam satu transaksi.
     */
    public function putuskanVerifikasi(
        Pengajuan $pengajuan,
        User $operator,
        KeputusanVerifikasi $keputusan,
        ?string $catatan
    ): VerifikasiDokumen {
        if ($pengajuan->status !== StatusPengajuan::MenungguVerifikasi) {
            throw new InvalidArgumentException('Pengajuan ini tidak sedang menunggu verifikasi.');
        }

        $catatan = $catatan !== null ? trim($catatan) : null;

        if ($keputusan === KeputusanVerifikasi::PerluPerbaikan && ($catatan === null || $catatan === '')) {
            throw new InvalidArgumentException('Catatan wajib diisi untuk keputusan Perlu Perbaikan, agar nasabah mengetahui yang harus diperbaiki.');
        }

        $verifikasi = DB::transaction(function () use ($pengajuan, $operator, $keputusan, $catatan) {
            $verifikasi = VerifikasiDokumen::create([
                'pengajuan_id' => $pengajuan->id,
                'operator_id' => $operator->id,
                'hasil_pemeriksaan_id' => $pengajuan->hasilPemeriksaans()->latest('id')->value('id'),
                'keputusan' => $keputusan,
                'catatan' => $catatan,
            ]);

            $this->pindahkanStatus(
                $pengajuan,
                $keputusan->statusPengajuan(),
                $operator,
                $catatan
            );

            return $verifikasi;
        });

        $this->notifikasi->keputusanVerifikasi($pengajuan, $verifikasi);

        $this->log->catat(
            LogAktivitas::VERIFIKASI_KEPUTUSAN,
            "Operator {$operator->name} memutuskan {$keputusan->label()} untuk pengajuan {$pengajuan->nomor_pengajuan}.",
            $operator,
            $pengajuan,
            ['keputusan' => $keputusan->value, 'catatan' => $catatan]
        );

        return $verifikasi;
    }

    /**
     * Nasabah mengajukan kembali pengajuan yang perlu perbaikan,
     * setelah memperbaiki dokumennya.
     */
    public function ajukanKembali(Pengajuan $pengajuan, User $nasabah): void
    {
        if ($pengajuan->status !== StatusPengajuan::PerluPerbaikan) {
            throw new InvalidArgumentException('Hanya pengajuan berstatus Perlu Perbaikan yang dapat diajukan kembali.');
        }

        DB::transaction(function () use ($pengajuan, $nasabah) {
            $this->pindahkanStatus(
                $pengajuan,
                StatusPengajuan::MenungguVerifikasi,
                $nasabah,
                'Pengajuan diajukan kembali oleh nasabah setelah perbaikan.'
            );
        });

        $this->notifikasi->diajukanKembali($pengajuan);

        $this->log->catat(
            LogAktivitas::PENGAJUAN_DIAJUKAN_KEMBALI,
            "Nasabah {$nasabah->name} mengajukan kembali pengajuan {$pengajuan->nomor_pengajuan} setelah perbaikan.",
            $nasabah,
            $pengajuan
        );
    }

    /**
     * Operator memajukan status pengajuan mengikuti peta transisi:
     * terverifikasi -> diproses -> selesai.
     */
    public function ubahStatus(Pengajuan $pengajuan, User $operator, StatusPengajuan $statusBaru): void
    {
        $transisiSah = [
            StatusPengajuan::Terverifikasi->value => [StatusPengajuan::Diproses],
            StatusPengajuan::Diproses->value => [StatusPengajuan::Selesai],
        ];

        $statusLama = $pengajuan->status;

        $diizinkan = $transisiSah[$pengajuan->status->value] ?? [];

        if (! in_array($statusBaru, $diizinkan, true)) {
            throw new InvalidArgumentException(
                'Transisi status dari ' . $pengajuan->status->label() . ' ke ' . $statusBaru->label() . ' tidak diizinkan.'
            );
        }

        DB::transaction(function () use ($pengajuan, $operator, $statusBaru) {
            $this->pindahkanStatus($pengajuan, $statusBaru, $operator, null);
        });

        $this->notifikasi->statusBerubah($pengajuan);

        $this->log->catat(
            LogAktivitas::PENGAJUAN_STATUS_BERUBAH,
            "Operator {$operator->name} mengubah status pengajuan {$pengajuan->nomor_pengajuan} dari {$statusLama->label()} menjadi {$statusBaru->label()}.",
            $operator,
            $pengajuan,
            ['status_dari' => $statusLama->value, 'status_ke' => $statusBaru->value]
        );
    }

    /**
     * Operator mengubah status pembayaran: hanya boleh berpindah ke
     * langkah yang bersebelahan (maju atau mundur satu langkah) agar
     * tidak ada lompatan, selama pengajuan belum dibatalkan. Pelaku
     * perubahan terakhir dicatat pada kolom diubah_oleh.
     */
    public function ubahStatusPembayaran(Pengajuan $pengajuan, User $operator, StatusPembayaran $statusBaru): void
    {
        if ($pengajuan->status === StatusPengajuan::Dibatalkan) {
            throw new InvalidArgumentException('Status pembayaran pengajuan yang dibatalkan tidak dapat diubah.');
        }

        $pembayaran = $pengajuan->pembayaran;
        $statusPembayaranLama = $pembayaran?->status;

        if ($pembayaran === null) {
            throw new InvalidArgumentException('Pengajuan ini tidak memiliki data pembayaran.');
        }

        $urutan = StatusPembayaran::cases();
        $indeksLama = array_search($pembayaran->status, $urutan, true);
        $indeksBaru = array_search($statusBaru, $urutan, true);

        if ($indeksLama === $indeksBaru) {
            return; // Tidak ada perubahan.
        }

        if (abs($indeksBaru - $indeksLama) !== 1) {
            throw new InvalidArgumentException('Status pembayaran hanya dapat berpindah satu langkah dari status saat ini.');
        }

        $pembayaran->update([
            'status' => $statusBaru,
            'diubah_oleh' => $operator->id,
        ]);

        $this->notifikasi->pembayaranBerubah($pengajuan);

        $this->log->catat(
            LogAktivitas::PEMBAYARAN_STATUS_BERUBAH,
            "Operator {$operator->name} mengubah status pembayaran pengajuan {$pengajuan->nomor_pengajuan} dari {$statusPembayaranLama?->label()} menjadi {$statusBaru->label()}.",
            $operator,
            $pengajuan,
            ['status_dari' => $statusPembayaranLama?->value, 'status_ke' => $statusBaru->value]
        );
    }

    /**
     * Memindahkan status pengajuan, memperbarui catatan terakhir,
     * dan menulis baris riwayat status. Dipakai di dalam transaksi
     * milik pemanggil.
     */
    private function pindahkanStatus(Pengajuan $pengajuan, StatusPengajuan $statusBaru, User $pelaku, ?string $catatan): void
    {
        $statusLama = $pengajuan->status;

        $pengajuan->update([
            'status' => $statusBaru,
            'catatan' => $statusBaru === StatusPengajuan::PerluPerbaikan ? $catatan : null,
        ]);

        $pengajuan->riwayatStatuses()->create([
            'status_dari' => $statusLama,
            'status_ke' => $statusBaru,
            'catatan' => $catatan,
            'changed_by' => $pelaku->id,
        ]);
    }
}
