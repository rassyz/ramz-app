<?php

namespace App\Services;

use App\Enums\HasilDetail;
use App\Enums\KategoriAturan;
use App\Enums\StatusHasilPemeriksaan;
use App\Models\AturanValidasi;
use App\Models\Dokumen;
use App\Models\HasilPemeriksaan;
use App\Models\Pengajuan;
use Illuminate\Support\Facades\DB;


class RuleBasedEvidenceChecker
{
    /**
     * Menjalankan pemeriksaan dan menyimpan HasilPemeriksaan beserta
     * seluruh DetailPemeriksaan-nya sebagai record baru.
     */
    public function periksa(Pengajuan $pengajuan): HasilPemeriksaan
    {
        return DB::transaction(function () use ($pengajuan) {
            $pengajuan->loadMissing(['layanan', 'user', 'pembayaran', 'dokumens']);

            $aturanAktif = AturanValidasi::where('is_active', true)
                ->get()
                ->keyBy('kode');

            $details = [];

            if ($aturanAktif->has('KELENGKAPAN-DOKUMEN')) {
                array_push($details, ...$this->periksaKelengkapan($pengajuan, $aturanAktif->get('KELENGKAPAN-DOKUMEN')));
            }

            $dokumenInvoice = $pengajuan->dokumens->first(fn(Dokumen $dokumen) => $dokumen->isDokumenInvoice());

            $aturanPencocokan = [
                'COCOK-NOMOR-INVOICE' => fn() => $this->cocokkanTeks(
                    'Nomor invoice',
                    $pengajuan->nomor_invoice,
                    $dokumenInvoice?->doc_nomor_invoice,
                    $dokumenInvoice
                ),
                'COCOK-IDENTITAS' => fn() => $this->cocokkanTeks(
                    'Identitas nasabah',
                    $pengajuan->user->name,
                    $dokumenInvoice?->doc_identitas_nasabah,
                    $dokumenInvoice
                ),
                'COCOK-NOMINAL' => fn() => $this->cocokkanNominal($pengajuan, $dokumenInvoice),
                'COCOK-MATA-UANG' => fn() => $this->cocokkanTeks(
                    'Mata uang pembayaran',
                    $pengajuan->pembayaran?->mata_uang->value,
                    $dokumenInvoice?->doc_mata_uang?->value,
                    $dokumenInvoice
                ),
                'COCOK-JENIS-LAYANAN' => fn() => $this->cocokkanTeks(
                    'Jenis layanan',
                    $pengajuan->layanan->kode,
                    $dokumenInvoice?->doc_jenis_layanan,
                    $dokumenInvoice,
                    strtoupper(...)
                ),
            ];

            foreach ($aturanPencocokan as $kode => $pemeriksaan) {
                if ($aturanAktif->has($kode)) {
                    $detail = $pemeriksaan();
                    $detail['aturan_id'] = $aturanAktif->get($kode)->id;
                    $detail['kategori'] = KategoriAturan::Pencocokan;
                    $details[] = $detail;
                }
            }

            $totalSesuai = collect($details)->where('hasil', HasilDetail::Sesuai)->count();
            $totalTidakSesuai = collect($details)->where('hasil', HasilDetail::TidakSesuai)->count();
            $dapatDinilai = $totalSesuai + $totalTidakSesuai;

            $status = match (true) {
                $dapatDinilai === 0 => StatusHasilPemeriksaan::BelumDapatDiperiksa,
                $totalTidakSesuai > 0 => StatusHasilPemeriksaan::TidakSesuai,
                default => StatusHasilPemeriksaan::Sesuai,
            };

            $hasil = HasilPemeriksaan::create([
                'pengajuan_id' => $pengajuan->id,
                'status' => $status,
                'total_pemeriksaan' => count($details),
                'total_sesuai' => $totalSesuai,
                'total_tidak_sesuai' => $totalTidakSesuai,
            ]);

            $hasil->details()->createMany($details);

            return $hasil;
        });
    }

    /**
     * Aturan kelengkapan: satu detail untuk setiap dokumen wajib pada
     * persyaratan layanan.
     *
     * @return list<array<string, mixed>>
     */
    private function periksaKelengkapan(Pengajuan $pengajuan, AturanValidasi $aturan): array
    {
        $persyaratan = collect($pengajuan->layanan->persyaratan_dokumen ?? [])
            ->filter(fn($syarat) => (bool) ($syarat['wajib'] ?? false));

        if ($persyaratan->isEmpty()) {
            return [[
                'aturan_id' => $aturan->id,
                'kategori' => KategoriAturan::Kelengkapan,
                'nama_pemeriksaan' => 'Kelengkapan dokumen wajib',
                'nilai_pengajuan' => 'Layanan tidak menetapkan dokumen wajib',
                'nilai_dokumen' => null,
                'hasil' => HasilDetail::TidakDapatDiperiksa,
                'keterangan' => 'Tidak ada dokumen wajib yang perlu diperiksa.',
            ]];
        }

        $jenisTerunggah = $pengajuan->dokumens->pluck('jenis_dokumen')
            ->map(fn(string $jenis) => strtolower(trim($jenis)));

        return $persyaratan->map(function ($syarat) use ($aturan, $pengajuan, $jenisTerunggah) {
            $namaDokumen = $syarat['nama'];
            $terunggah = $jenisTerunggah->contains(strtolower(trim($namaDokumen)));
            $dokumen = $pengajuan->dokumens->first(
                fn(Dokumen $d) => strtolower(trim($d->jenis_dokumen)) === strtolower(trim($namaDokumen))
            );

            return [
                'aturan_id' => $aturan->id,
                'kategori' => KategoriAturan::Kelengkapan,
                'nama_pemeriksaan' => 'Kelengkapan dokumen: ' . $namaDokumen,
                'nilai_pengajuan' => 'Wajib diunggah',
                'nilai_dokumen' => $terunggah ? $dokumen->nama_file_asli : 'Belum diunggah',
                'hasil' => $terunggah ? HasilDetail::Sesuai : HasilDetail::TidakSesuai,
                'keterangan' => $terunggah
                    ? 'Dokumen wajib sudah terunggah.'
                    : 'Dokumen wajib belum diunggah oleh nasabah.',
            ];
        })->values()->all();
    }

    /**
     * Pencocokan teks dengan normalisasi trim + huruf kecil, kecuali
     * bila penormal khusus diberikan (mis. huruf besar untuk kode).
     *
     * @return array<string, mixed>
     */
    private function cocokkanTeks(
        string $namaField,
        ?string $nilaiPengajuan,
        ?string $nilaiDokumen,
        ?Dokumen $dokumenInvoice,
        ?callable $normalisasi = null
    ): array {
        $namaPemeriksaan = 'Pencocokan ' . $namaField;

        if ($dokumenInvoice === null) {
            return $this->detailTidakDapatDiperiksa($namaPemeriksaan, $nilaiPengajuan, null, 'Dokumen Invoice belum diunggah, sehingga ' . $namaField . ' belum dapat dicocokkan.');
        }

        if ($nilaiDokumen === null || trim($nilaiDokumen) === '') {
            return $this->detailTidakDapatDiperiksa($namaPemeriksaan, $nilaiPengajuan, null, 'Data ' . $namaField . ' pada dokumen belum diisi oleh nasabah.');
        }

        $normalisasi ??= fn(?string $nilai) => strtolower(trim((string) $nilai));
        $sesuai = $normalisasi($nilaiPengajuan) === $normalisasi($nilaiDokumen);

        return [
            'nama_pemeriksaan' => $namaPemeriksaan,
            'nilai_pengajuan' => $nilaiPengajuan,
            'nilai_dokumen' => $nilaiDokumen,
            'hasil' => $sesuai ? HasilDetail::Sesuai : HasilDetail::TidakSesuai,
            'keterangan' => $sesuai
                ? $namaField . ' pada dokumen sama dengan data pengajuan.'
                : $namaField . ' pada dokumen berbeda dengan data pengajuan.',
        ];
    }

    /**
     * Pencocokan nominal pembayaran dengan toleransi selisih 0,01.
     *
     * @return array<string, mixed>
     */
    private function cocokkanNominal(Pengajuan $pengajuan, ?Dokumen $dokumenInvoice): array
    {
        $namaPemeriksaan = 'Pencocokan nominal pembayaran';
        $nilaiPengajuan = $pengajuan->pembayaran
            ? $pengajuan->pembayaran->mata_uang->value . ' ' . number_format((float) $pengajuan->pembayaran->nominal, 2, ',', '.')
            : null;

        if ($dokumenInvoice === null) {
            return $this->detailTidakDapatDiperiksa($namaPemeriksaan, $nilaiPengajuan, null, 'Dokumen Invoice belum diunggah, sehingga nominal belum dapat dicocokkan.');
        }

        if ($dokumenInvoice->doc_nominal === null) {
            return $this->detailTidakDapatDiperiksa($namaPemeriksaan, $nilaiPengajuan, null, 'Data nominal pada dokumen belum diisi oleh nasabah.');
        }

        $nilaiDokumen = ($dokumenInvoice->doc_mata_uang?->value ?? '') . ' ' . number_format((float) $dokumenInvoice->doc_nominal, 2, ',', '.');
        $sesuai = abs((float) $pengajuan->pembayaran->nominal - (float) $dokumenInvoice->doc_nominal) <= 0.01;

        return [
            'nama_pemeriksaan' => $namaPemeriksaan,
            'nilai_pengajuan' => $nilaiPengajuan,
            'nilai_dokumen' => $nilaiDokumen,
            'hasil' => $sesuai ? HasilDetail::Sesuai : HasilDetail::TidakSesuai,
            'keterangan' => $sesuai
                ? 'Nominal pada dokumen sama dengan data pembayaran pengajuan.'
                : 'Nominal pada dokumen berbeda dengan data pembayaran pengajuan.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailTidakDapatDiperiksa(string $namaPemeriksaan, ?string $nilaiPengajuan, ?string $nilaiDokumen, string $keterangan): array
    {
        return [
            'nama_pemeriksaan' => $namaPemeriksaan,
            'nilai_pengajuan' => $nilaiPengajuan,
            'nilai_dokumen' => $nilaiDokumen,
            'hasil' => HasilDetail::TidakDapatDiperiksa,
            'keterangan' => $keterangan,
        ];
    }
}
