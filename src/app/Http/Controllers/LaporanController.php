<?php

namespace App\Http\Controllers;

use App\Services\LaporanService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan CSV laporan pengajuan per periode (KF-13). Route dilindungi
 * middleware role:admin; berkas disusun dari sumber data yang sama
 * dengan pratinjau laporan (LaporanService).
 */
class LaporanController extends Controller
{
    public function __invoke(Request $request, LaporanService $laporan): StreamedResponse
    {
        $validated = $request->validate([
            'mulai' => ['required', 'date'],
            'selesai' => ['required', 'date', 'after_or_equal:mulai'],
        ]);

        $mulai = Carbon::parse($validated['mulai'])->startOfDay();
        $selesai = Carbon::parse($validated['selesai'])->endOfDay();

        $pengajuans = $laporan->daftarPengajuan($mulai, $selesai);

        $namaBerkas = 'laporan-pengajuan-' . $mulai->format('Ymd') . '-' . $selesai->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($pengajuans) {
            $keluaran = fopen('php://output', 'w');

            fputcsv($keluaran, [
                'Nomor Pengajuan',
                'Tanggal',
                'Nasabah',
                'Email Nasabah',
                'Layanan',
                'Nomor Invoice',
                'Status Pengajuan',
                'Nominal',
                'Mata Uang',
                'Status Pembayaran',
            ]);

            foreach ($pengajuans as $pengajuan) {
                fputcsv($keluaran, [
                    $pengajuan->nomor_pengajuan,
                    $pengajuan->created_at->format('Y-m-d H:i'),
                    $pengajuan->user->name,
                    $pengajuan->user->email,
                    $pengajuan->layanan->nama,
                    $pengajuan->nomor_invoice,
                    $pengajuan->status->label(),
                    $pengajuan->pembayaran ? number_format((float) $pengajuan->pembayaran->nominal, 2, '.', '') : '',
                    $pengajuan->pembayaran?->mata_uang->value ?? '',
                    $pengajuan->pembayaran?->status->label() ?? '',
                ]);
            }

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv']);
    }
}
