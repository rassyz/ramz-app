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

/**
 * Rule-Based Evidence Checker v2 (KF-06, Fase 11).
 *
 * Memeriksa pengajuan dengan aturan IF–THEN pada tabel aturan_validasis:
 *  1. Kelengkapan — setiap dokumen wajib pada snapshot persyaratan
 *     pengajuan (wujud klausul dokumen LC) harus sudah terunggah.
 *  2. Pencocokan per jenis dokumen wajib — field terstruktur yang
 *     diisi pengunggah pada SETIAP dokumen wajib dibandingkan dengan
 *     data acuan LC yang tercatat pada pengajuan (nomor LC, nomor PO,
 *     nominal, mata uang, identitas nasabah, kuantitas), dengan
 *     toleransi persen milik LC itu sendiri untuk nilai angka.
 *
 * Sistem tidak memakai OCR/AI: yang dibandingkan adalah data
 * terstruktur yang dimasukkan ke aplikasi. Hasil pemeriksaan bersifat
 * pendukung keputusan — checker tidak mengubah status pengajuan;
 * keputusan verifikasi akhir tetap pada Operator.
 */
class RuleBasedEvidenceChecker
{
    /**
     * Menjalankan pemeriksaan dan menyimpan HasilPemeriksaan beserta
     * seluruh DetailPemeriksaan-nya sebagai record baru.
     */
    public function periksa(Pengajuan $pengajuan): HasilPemeriksaan
    {
        return DB::transaction(function () use ($pengajuan) {
            $pengajuan->loadMissing(['layanan', 'user', 'dokumens']);

            $aturanAktif = AturanValidasi::where('is_active', true)->get();

            $details = [];

            $aturanKelengkapan = $aturanAktif->firstWhere('kode', 'KELENGKAPAN-DOKUMEN');
            if ($aturanKelengkapan) {
                array_push($details, ...$this->periksaKelengkapan($pengajuan, $aturanKelengkapan));
            }

            foreach ($aturanAktif->where('kategori', KategoriAturan::Pencocokan) as $aturan) {
                // Aturan pencocokan v1 (tanpa jenis dokumen) tidak lagi
                // dievaluasi oleh checker v2.
                if ($aturan->jenis_dokumen === null || $aturan->field === null) {
                    continue;
                }

                $detail = $this->periksaPencocokan($pengajuan, $aturan);
                $detail['aturan_id'] = $aturan->id;
                $detail['kategori'] = KategoriAturan::Pencocokan;
                $details[] = $detail;
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
     * snapshot persyaratan pengajuan.
     *
     * @return list<array<string, mixed>>
     */
    private function periksaKelengkapan(Pengajuan $pengajuan, AturanValidasi $aturan): array
    {
        $persyaratan = collect($pengajuan->persyaratanBerlaku())
            ->filter(fn($syarat) => (bool) ($syarat['wajib'] ?? false));

        if ($persyaratan->isEmpty()) {
            return [[
                'aturan_id' => $aturan->id,
                'kategori' => KategoriAturan::Kelengkapan,
                'nama_pemeriksaan' => 'Kelengkapan dokumen wajib',
                'nilai_pengajuan' => 'Tidak ada dokumen wajib yang ditetapkan',
                'nilai_dokumen' => null,
                'hasil' => HasilDetail::TidakDapatDiperiksa,
                'keterangan' => 'Snapshot persyaratan pengajuan tidak menetapkan dokumen wajib.',
            ]];
        }

        return $persyaratan->map(function ($syarat) use ($aturan, $pengajuan) {
            $namaDokumen = $syarat['nama'];
            $dokumen = $pengajuan->dokumens->first(fn(Dokumen $d) => $d->isJenis($namaDokumen));

            return [
                'aturan_id' => $aturan->id,
                'kategori' => KategoriAturan::Kelengkapan,
                'nama_pemeriksaan' => 'Kelengkapan dokumen: ' . $namaDokumen,
                'nilai_pengajuan' => 'Wajib diunggah',
                'nilai_dokumen' => $dokumen ? $dokumen->nama_file_asli : 'Belum diunggah',
                'hasil' => $dokumen ? HasilDetail::Sesuai : HasilDetail::TidakSesuai,
                'keterangan' => $dokumen
                    ? 'Dokumen wajib sudah terunggah.'
                    : 'Dokumen wajib belum diunggah.',
            ];
        })->values()->all();
    }

    /**
     * Satu aturan pencocokan per jenis dokumen: ambil nilai field dari
     * dokumen jenis itu, ambil nilai referensi dari data acuan
     * pengajuan, lalu bandingkan sesuai jenis field-nya.
     *
     * @return array<string, mixed>
     */
    private function periksaPencocokan(Pengajuan $pengajuan, AturanValidasi $aturan): array
    {
        $namaPemeriksaan = $aturan->nama;
        $dokumen = $pengajuan->dokumens->first(fn(Dokumen $d) => $d->isJenis($aturan->jenis_dokumen));

        $nilaiAcuan = $this->nilaiReferensi($pengajuan, $aturan->referensi);
        $labelAcuan = $this->formatNilai($pengajuan, $aturan, $nilaiAcuan, true);

        if ($dokumen === null) {
            return $this->detailTidakDapatDiperiksa(
                $namaPemeriksaan,
                $labelAcuan,
                null,
                'Dokumen ' . $aturan->jenis_dokumen . ' belum diunggah, sehingga pemeriksaan ini belum dapat dilakukan.'
            );
        }

        $nilaiDokumen = $this->nilaiDokumen($dokumen, $aturan->field);

        if ($nilaiDokumen === null || (is_string($nilaiDokumen) && trim($nilaiDokumen) === '')) {
            return $this->detailTidakDapatDiperiksa(
                $namaPemeriksaan,
                $labelAcuan,
                null,
                'Data ' . $aturan->field . ' pada dokumen ' . $aturan->jenis_dokumen . ' belum diisi oleh pengunggah.'
            );
        }

        if ($nilaiAcuan === null || (is_string($nilaiAcuan) && trim($nilaiAcuan) === '')) {
            return $this->detailTidakDapatDiperiksa(
                $namaPemeriksaan,
                null,
                $this->formatNilai($pengajuan, $aturan, $nilaiDokumen, false, $dokumen),
                'Data acuan (' . $aturan->referensi . ') pada pengajuan belum diisi, sehingga belum dapat dicocokkan.'
            );
        }

        [$sesuai, $keterangan] = $this->bandingkan($pengajuan, $aturan, $nilaiAcuan, $nilaiDokumen, $dokumen);

        return [
            'nama_pemeriksaan' => $namaPemeriksaan,
            'nilai_pengajuan' => $labelAcuan,
            'nilai_dokumen' => $this->formatNilai($pengajuan, $aturan, $nilaiDokumen, false, $dokumen),
            'hasil' => $sesuai ? HasilDetail::Sesuai : HasilDetail::TidakSesuai,
            'keterangan' => $keterangan,
        ];
    }

    /**
     * Nilai field terstruktur dari sebuah dokumen.
     */
    private function nilaiDokumen(Dokumen $dokumen, string $field): mixed
    {
        return match ($field) {
            'nomor_invoice' => $dokumen->doc_nomor_invoice,
            'nomor_lc' => $dokumen->doc_nomor_lc,
            'nomor_po' => $dokumen->doc_nomor_po,
            'nomor_dokumen' => $dokumen->doc_nomor_dokumen,
            'identitas_nasabah' => $dokumen->doc_identitas_nasabah,
            'identitas_pengirim' => $dokumen->doc_identitas_pengirim,
            'nominal' => $dokumen->doc_nominal !== null ? (float) $dokumen->doc_nominal : null,
            'mata_uang' => $dokumen->doc_mata_uang?->value,
            'kuantitas' => $dokumen->doc_kuantitas !== null ? (float) $dokumen->doc_kuantitas : null,
            'tanggal_dokumen' => $dokumen->doc_tanggal_dokumen?->toDateString(),
            default => null,
        };
    }

    /**
     * Nilai referensi dari data acuan yang tercatat pada pengajuan.
     */
    private function nilaiReferensi(Pengajuan $pengajuan, ?string $referensi): mixed
    {
        return match ($referensi) {
            'nomor_invoice' => $pengajuan->nomor_invoice,
            'nomor_lc' => $pengajuan->nomor_lc,
            'nomor_po' => $pengajuan->nomor_po,
            'nasabah' => $pengajuan->user->name,
            'nominal_acuan' => $pengajuan->nominal_acuan !== null ? (float) $pengajuan->nominal_acuan : null,
            'mata_uang_acuan' => $pengajuan->mata_uang_acuan?->value,
            'kuantitas_acuan' => $pengajuan->kuantitas_acuan !== null ? (float) $pengajuan->kuantitas_acuan : null,
            'tanggal_kedaluwarsa_lc' => $pengajuan->tanggal_kedaluwarsa_lc?->toDateString(),
            default => null,
        };
    }

    /**
     * Perbandingan sesuai jenis field:
     * - nominal & kuantitas: toleransi persen milik LC (0 = persis);
     *   khusus Asuransi, nilai dokumen adalah nilai pertanggungan
     *   sehingga yang dinilai adalah "sekurang-kurangnya sebesar acuan".
     * - tanggal_dokumen: tanggal dokumen tidak melampaui tanggal acuan.
     * - selainnya: teks dengan normalisasi trim + huruf kecil.
     *
     * @return array{bool, string}
     */
    private function bandingkan(Pengajuan $pengajuan, AturanValidasi $aturan, mixed $nilaiAcuan, mixed $nilaiDokumen, ?Dokumen $dokumen = null): array
    {
        if ($aturan->field === 'nominal' || $aturan->field === 'kuantitas') {
            $acuan = (float) $nilaiAcuan;
            $dokumenNilai = (float) $nilaiDokumen;

            // Faktur Pajak (Perbaikan 5): mata uang digabungkan ke
            // aturan nominalnya — nilai ditampilkan dan dinilai
            // sebagai satu kesatuan nominal + mata uang.
            if (
                $aturan->field === 'nominal'
                && strtolower(trim((string) $aturan->jenis_dokumen)) === 'faktur pajak'
                && $dokumen !== null
            ) {
                $mataUangDokumen = strtolower(trim((string) $dokumen->doc_mata_uang?->value));
                $mataUangAcuan = strtolower(trim((string) $pengajuan->mata_uang_acuan?->value));

                if ($mataUangDokumen !== '' && $mataUangAcuan !== '' && $mataUangDokumen !== $mataUangAcuan) {
                    return [false, 'Mata uang pada dokumen (' . $dokumen->doc_mata_uang?->value . ') berbeda dengan mata uang acuan (' . $pengajuan->mata_uang_acuan?->value . ').'];
                }
            }

            if ($aturan->field === 'nominal' && strtolower(trim((string) $aturan->jenis_dokumen)) === 'asuransi') {
                $sesuai = $dokumenNilai >= $acuan;

                return [$sesuai, $sesuai
                    ? 'Nilai pertanggungan pada dokumen sekurang-kurangnya sebesar nominal acuan.'
                    : 'Nilai pertanggungan pada dokumen lebih kecil dari nominal acuan.'];
            }

            $toleransi = (float) ($pengajuan->toleransi_persen ?? 0);
            $batas = abs($acuan) * $toleransi / 100;
            $sesuai = abs($dokumenNilai - $acuan) <= $batas;

            if (! $sesuai) {
                return [false, 'Nilai pada dokumen melampaui toleransi ' . $toleransi . '% dari data acuan.'];
            }

            // Pendamping kuantitas (Perbaikan 5): satuan dokumen ikut
            // dibandingkan dengan satuan acuan bila keduanya terisi —
            // angka yang sama dengan satuan berbeda bukan kesesuaian.
            if ($aturan->field === 'kuantitas' && $dokumen !== null) {
                $satuanDokumen = strtolower(trim((string) $dokumen->doc_satuan));
                $satuanAcuan = strtolower(trim((string) $pengajuan->satuan_acuan));

                if ($satuanDokumen !== '' && $satuanAcuan !== '' && $satuanDokumen !== $satuanAcuan) {
                    return [false, 'Satuan kuantitas pada dokumen (' . $dokumen->doc_satuan . ') berbeda dengan satuan acuan (' . $pengajuan->satuan_acuan . ').'];
                }
            }

            return [true, 'Nilai pada dokumen berada dalam toleransi ' . $toleransi . '% dari data acuan.'];
        }

        if ($aturan->field === 'tanggal_dokumen') {
            $sesuai = (string) $nilaiDokumen <= (string) $nilaiAcuan;

            return [$sesuai, $sesuai
                ? 'Tanggal dokumen tidak melampaui tanggal kedaluwarsa LC.'
                : 'Tanggal dokumen melampaui tanggal kedaluwarsa LC.'];
        }

        $sesuai = strtolower(trim((string) $nilaiAcuan)) === strtolower(trim((string) $nilaiDokumen));

        return [$sesuai, $sesuai
            ? 'Nilai pada dokumen sama dengan data acuan.'
            : 'Nilai pada dokumen berbeda dengan data acuan.'];
    }

    /**
     * Label tampilan nilai untuk snapshot detail pemeriksaan.
     */
    private function formatNilai(Pengajuan $pengajuan, AturanValidasi $aturan, mixed $nilai, bool $isAcuan, ?Dokumen $dokumen = null): ?string
    {
        if ($nilai === null) {
            return null;
        }

        if ($aturan->field === 'nominal') {
            $mataUang = $isAcuan
                ? $pengajuan->mata_uang_acuan?->value
                : $dokumen?->doc_mata_uang?->value;

            return trim(($mataUang ? $mataUang . ' ' : '') . number_format((float) $nilai, 2, ',', '.'));
        }

        if ($aturan->field === 'kuantitas') {
            $satuan = $isAcuan ? $pengajuan->satuan_acuan : $dokumen?->doc_satuan;

            return trim(number_format((float) $nilai, 3, ',', '.') . ($satuan ? ' ' . $satuan : ''));
        }

        return (string) $nilai;
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
