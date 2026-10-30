# Product Requirements Document (PRD)

## Aplikasi Monitoring Layanan Perdagangan dan Pembayaran Internasional

**Status:** Draft untuk pengembangan awal  
**Dokumen sumber:** `PROPOSAL_RAMA PURNAMA.pdf`  
**Bahasa aplikasi:** Bahasa Indonesia  
**Jenis aplikasi:** Web application  
**Metode pengembangan yang direncanakan:** Rapid Application Development (RAD)

> **Instruksi utama untuk Coding Agent:** Gunakan dokumen ini sebagai spesifikasi produk awal. Jangan mengarang aturan bisnis, integrasi, field, status, atau teknologi yang tidak disebutkan. Jika detail penting belum tersedia, dokumentasikan asumsi dan tanyakan kepada pengguna sebelum mengunci implementasi. Bangun aplikasi secara bertahap, jalankan pengujian, dan jelaskan perubahan setiap tahap.

---

## 1. Ringkasan Produk

Aplikasi Monitoring Layanan Perdagangan dan Pembayaran Internasional adalah aplikasi berbasis web untuk memusatkan pencatatan dan pemantauan data layanan perdagangan, data pembayaran internasional, dokumen layanan, status verifikasi, status layanan, status pembayaran, notifikasi, serta laporan monitoring.

Aplikasi ditujukan untuk membantu pekerjaan administrasi menjadi lebih terstruktur, mudah dicari, mudah dipantau, dan terdokumentasi. Aplikasi **bukan** sistem untuk memproses transfer atau transaksi perbankan secara langsung.

### 1.1 Masalah yang ingin diselesaikan

- Data layanan dan pembayaran belum dikelola secara terpusat.
- Pencatatan dan pembaruan status berpotensi tidak konsisten atau sulit ditelusuri.
- Pencarian informasi perkembangan layanan memerlukan waktu.
- Informasi perubahan status bisa terlambat diterima pihak terkait.
- Pembuatan laporan monitoring membutuhkan pekerjaan manual.
- Riwayat aktivitas pengguna perlu terdokumentasi untuk mendukung pengawasan.

### 1.2 Tujuan produk

1. Memusatkan data layanan perdagangan dan pembayaran internasional.
2. Mendukung pencatatan serta pembaruan status layanan, pembayaran, dan verifikasi dokumen.
3. Menampilkan dashboard monitoring yang dapat difilter.
4. Menyediakan laporan yang dapat dibuat dan diekspor.
5. Mengirim notifikasi melalui WhatsApp dan email untuk perubahan yang relevan.
6. Menyimpan log aktivitas penting pengguna.
7. Mendukung pengaturan personalisasi dashboard.
8. Menangani kegagalan pengiriman notifikasi dengan mekanisme retry dan backoff.

---

## 2. Batasan dan Hal yang Belum Dipastikan

Proposal merupakan rancangan penelitian; fitur di bawah ini adalah kebutuhan yang direncanakan, bukan bukti bahwa sistem sudah dibangun atau diuji.

### 2.1 Batasan ruang lingkup

- Aplikasi diakses melalui browser oleh pengguna yang memiliki hak akses.
- Fokus pada administrasi, monitoring, dokumen, status, notifikasi, dan pelaporan.
- Tidak memproses transaksi pembayaran melalui sistem perbankan.
- Tidak mengintegrasikan bank, payment gateway, bea cukai, atau sistem perdagangan eksternal lainnya.
- Integrasi eksternal yang direncanakan dibatasi pada layanan WhatsApp dan email.
- Tidak membahas analisis kebijakan ekspor-impor secara mendalam.

### 2.2 Keputusan ruang lingkup aktor

Untuk versi PRD ini, ruang lingkup ditetapkan menjadi **tiga aktor aktif: Admin, Operator, dan Nasabah**. Keputusan ini mengikuti use case yang menggambarkan Nasabah dapat login, mengunggah dokumen, dan melihat status layanan.

Proposal sumber memiliki ketidakkonsistenan: abstrak dan beberapa bagian menyebut aktivitas Nasabah secara langsung, sedangkan bagian batasan masalah menyebut hanya Admin dan Operator. PRD revisi ini menetapkan tiga aktor sebagai keputusan produk untuk implementasi, sehingga portal Nasabah termasuk dalam ruang lingkup.

Detail berikut masih belum ditentukan dan jangan ditebak:

- Framework/backend, frontend, database engine, dan pola arsitektur.
- Daftar field lengkap setiap entitas.
- Daftar nilai/status resmi beserta aturan transisinya.
- Jenis dokumen dan aturan validasi masing-masing.
- Definisi operasional dan checklist untuk **Evidence Checker**.
- Format laporan, kolom ekspor, dan jadwal laporan otomatis.
- Provider WhatsApp/email, kredensial, template pesan, dan batas retry.
- Aturan retensi dokumen dan log, batas ukuran file, serta kebijakan keamanan spesifik.
- Apakah dashboard personalisasi disimpan per pengguna dan widget apa saja yang tersedia.

Jika informasi tersebut diperlukan untuk melanjutkan, buat daftar pertanyaan singkat untuk pengguna. Jangan menanam kredensial atau data sensitif di source code.

---

## 3. Pengguna dan Hak Akses

### 3.1 Admin

Admin bertanggung jawab terhadap pengelolaan dan pengawasan sistem.

Kebutuhan:

- Login dan logout.
- Mengelola data operator.
- Mengelola data layanan perdagangan.
- Mengelola data pembayaran internasional.
- Melihat dashboard monitoring dinamis.
- Membuat, melihat, dan mengekspor/mencetak laporan.
- Melihat log aktivitas.
- Mengelola konfigurasi notifikasi yang diizinkan sistem.
- Melakukan pemantauan terhadap status layanan, pembayaran, dan verifikasi dokumen.
- Mengatur personalisasi dashboard jika fitur ini tersedia untuk admin.

### 3.2 Operator

Operator bertanggung jawab terhadap pencatatan data dan pembaruan proses operasional.

Kebutuhan:

- Login dan logout.
- Input dan memperbarui data layanan.
- Input dan memperbarui data pembayaran.
- Memperbarui status layanan.
- Memperbarui status pembayaran.
- Melihat dokumen yang terkait dengan layanan sesuai hak akses.
- Melakukan verifikasi dokumen dan Evidence Checker sesuai aturan yang disepakati.
- Memperbarui status verifikasi dokumen.
- Mencari dan melihat data monitoring sesuai hak akses.
- Tidak boleh mengakses atau mengubah data di luar kewenangan yang diberikan.

### 3.3 Nasabah

Nasabah adalah pengguna eksternal yang memiliki akun sendiri dan hanya boleh mengakses data serta dokumen miliknya atau layanan yang terkait dengan akunnya.

Kebutuhan:

- Registrasi atau aktivasi akun melalui mekanisme yang akan ditetapkan.
- Login dan logout.
- Melihat dan memperbarui informasi profil yang diizinkan.
- Melihat daftar layanan/pengajuan miliknya.
- Mengunggah dokumen ke layanan/pengajuan miliknya.
- Melihat status verifikasi dokumen dan status layanan miliknya.
- Menerima notifikasi WhatsApp dan/atau email jika kanal kontak dan peristiwa notifikasinya telah dikonfigurasi.
- Tidak boleh melihat data, dokumen, pembayaran, atau status milik nasabah lain.
- Tidak dapat mengubah status verifikasi, status layanan internal, atau status pembayaran yang hanya boleh dikelola petugas.

Mekanisme pembuatan akun nasabah (registrasi mandiri atau akun dibuat/diaktifkan Admin) belum ditentukan dan harus dikonfirmasi sebelum implementasi final.

### 3.4 Matriks akses awal

| Fitur                          | Admin                             | Operator                               | Nasabah                                                       |
| ------------------------------ | --------------------------------- | -------------------------------------- | ------------------------------------------------------------- |
| Login/logout                   | Ya                                | Ya                                     | Ya                                                            |
| Kelola data operator           | Ya                                | Tidak                                  | Tidak                                                         |
| Kelola akun nasabah            | Ya, sesuai kebijakan akun         | Tidak secara default                   | Mengelola profil terbatas                                     |
| Kelola data layanan            | Ya                                | Ya, sesuai hak akses                   | Melihat layanan milik sendiri                                 |
| Kelola data pembayaran         | Ya                                | Ya, sesuai hak akses                   | Melihat informasi/status yang diizinkan untuk layanan sendiri |
| Upload dokumen                 | Akses pengawasan sesuai kebutuhan | Dapat membantu sesuai alur operasional | Ya, hanya untuk layanan sendiri                               |
| Verifikasi/Evidence Checker    | Akses pengawasan sesuai kebutuhan | Ya                                     | Tidak                                                         |
| Ubah status layanan/pembayaran | Ya                                | Ya, sesuai hak akses                   | Tidak                                                         |
| Ubah status verifikasi         | Ya, sesuai kebutuhan pengawasan   | Ya                                     | Tidak                                                         |
| Dashboard monitoring           | Ya, cakupan administrasi          | Ya, sesuai hak akses                   | Ringkasan layanan milik sendiri                               |
| Laporan dan ekspor internal    | Ya                                | Sesuai kebijakan                       | Tidak                                                         |
| Log aktivitas                  | Ya                                | Tidak secara default                   | Tidak                                                         |
| Konfigurasi notifikasi         | Ya                                | Tidak secara default                   | Mengatur kanal kontak sesuai kebijakan                        |
| Lihat dokumen                  | Sesuai kewenangan                 | Sesuai layanan yang ditangani          | Hanya dokumen miliknya sesuai kebijakan                       |

Matriks ini adalah default implementasi awal. Terapkan otorisasi di backend, bukan hanya menyembunyikan menu di UI. Untuk Nasabah, pemeriksaan kepemilikan data wajib dilakukan pada setiap endpoint, termasuk unduh/lihat dokumen; jangan hanya mengandalkan ID yang dikirim dari browser.

---

## 4. Alur Bisnis Utama

### 4.1 Akun dan akses Nasabah

1. Nasabah memperoleh akun melalui mekanisme registrasi atau aktivasi yang telah diputuskan.
2. Nasabah login.
3. Sistem memverifikasi identitas akun dan perannya.
4. Nasabah hanya melihat layanan, status, dan dokumen yang terhubung ke akun miliknya.
5. Sistem menolak akses ke data nasabah lain, termasuk bila URL atau ID objek diubah secara manual.

### 4.2 Pencatatan layanan dan pembayaran

1. Operator login.
2. Operator membuka modul layanan atau pembayaran.
3. Operator membuat atau memperbarui data sesuai field yang telah disepakati, lalu menghubungkan layanan ke akun Nasabah yang tepat bila relevan.
4. Sistem memvalidasi input dan relasi kepemilikan.
5. Sistem menyimpan data ke database.
6. Sistem mencatat aktivitas penting ke log.
7. Data terbaru muncul pada daftar dan dashboard sesuai filter serta hak akses. Nasabah hanya melihat informasi yang diperuntukkan baginya.

### 4.3 Unggah dan verifikasi dokumen

1. Dokumen digital diunggah sesuai data layanan terkait.
2. Sistem memeriksa validitas dasar file berdasarkan aturan yang dikonfigurasi.
3. Operator memeriksa dokumen dan menjalankan Evidence Checker.
4. Operator memperbarui status verifikasi dan menambahkan keterangan bila diperlukan.
5. Sistem menyimpan perubahan dan mencatat aktivitas.
6. Sistem mengirim notifikasi kepada pihak terkait jika perubahan tersebut termasuk peristiwa yang dikonfigurasi.

Proposal menyebut dokumen fisik dapat datang melalui penyedia pengiriman dan operator membandingkan dokumen fisik, dokumen digital, serta data input. Jangan membuat integrasi kurir; proses perbandingan dapat dicatat sebagai aktivitas/manual workflow kecuali ada kebutuhan tambahan.

### 4.4 Perubahan status dan notifikasi

1. Pengguna yang berwenang mengubah status layanan, pembayaran, atau verifikasi.
2. Sistem memvalidasi perubahan.
3. Sistem menyimpan perubahan status.
4. Sistem mencatat aktivitas ke log.
5. Sistem membuat tugas pengiriman notifikasi jika peristiwa tersebut dikonfigurasi.
6. Jika pengiriman gagal, sistem mencatat kegagalan dan mencoba kembali menggunakan retry/backoff.
7. Status pengiriman notifikasi dapat ditelusuri oleh admin.

### 4.5 Pelaporan dan monitoring

1. Pengguna membuka dashboard atau halaman laporan.
2. Pengguna memilih filter yang tersedia, seperti periode, status layanan, status pembayaran, atau status verifikasi dokumen.
3. Sistem menampilkan data dan visualisasi berdasarkan filter.
4. Admin membuat atau mengekspor laporan sesuai format yang ditetapkan.
5. Sistem mencatat aktivitas pembuatan/ekspor laporan.

---

## 5. Kebutuhan Fungsional

Gunakan ID berikut sebagai referensi implementasi dan pengujian.

### FR-01 — Autentikasi dan akun tiga peran

- Sistem menyediakan login dan logout untuk Admin, Operator, dan Nasabah.
- Sistem membatasi halaman dan tindakan berdasarkan peran pengguna.
- Nasabah harus memiliki akun yang terhubung secara aman dengan data/layanan miliknya.
- Mekanisme pendaftaran/aktivasi Nasabah harus dikonfirmasi: registrasi mandiri atau akun dibuat oleh Admin.
- Input login yang tidak valid harus menghasilkan pesan yang jelas tanpa membocorkan informasi sensitif.
- Sesi/token harus dikelola secara aman sesuai stack yang dipilih.

### FR-02 — Pengelolaan akun internal dan Nasabah

- Admin dapat melihat, menambah, memperbarui, dan menonaktifkan data Operator.
- Pengelolaan akun Nasabah mengikuti kebijakan registrasi/aktivasi yang dikonfirmasi.
- Detail field akun dan profil perlu dikonfirmasi.
- Jangan mengizinkan akun yang dinonaktifkan untuk login.
- Nasabah hanya dapat memperbarui field profil yang secara eksplisit diizinkan.

### FR-03 — Pengelolaan data layanan perdagangan

- Pengguna berwenang dapat membuat, melihat, mencari, memfilter, dan memperbarui data layanan.
- Data minimal yang disebutkan proposal mencakup informasi pemohon/nasabah, jenis layanan, tanggal pengajuan, status/proses, dan keterangan layanan.
- Field wajib dan aturan unik harus dikonfirmasi sebelum implementasi final.
- Sistem harus menampilkan status terbaru secara konsisten.

### FR-04 — Pengelolaan data pembayaran internasional

- Pengguna berwenang dapat membuat, melihat, mencari, memfilter, dan memperbarui catatan pembayaran.
- Modul ini hanya mencatat dan memonitor status pembayaran; tidak memproses transaksi bank.
- Field, referensi relasi ke layanan, dan pilihan status harus dikonfirmasi.

### FR-05 — Pengunggahan dokumen

- Sistem menyediakan fitur unggah dokumen digital terkait layanan.
- Nasabah dapat mengunggah dokumen hanya ke layanan/pengajuan miliknya; Operator dapat mengunggah atau membantu proses dokumen sesuai hak akses.
- Sistem memvalidasi tipe dan ukuran file berdasarkan konfigurasi.
- Sistem menyimpan metadata dokumen dan relasinya dengan layanan serta akun Nasabah terkait.
- Dokumen tidak boleh dapat diakses oleh pengguna yang tidak berwenang.
- Aturan tipe file, ukuran maksimal, jumlah dokumen, versi dokumen, dan retensi harus dikonfirmasi.

### FR-06 — Verifikasi dokumen

- Operator dapat melihat dokumen yang terkait dengan layanan.
- Operator dapat mencatat hasil pemeriksaan dan memperbarui status verifikasi.
- Sistem menyimpan waktu dan pengguna yang melakukan perubahan.
- Nilai status dan aturan transisi harus disepakati sebelum diimplementasikan.

### FR-07 — Evidence Checker

- Sediakan area/komponen untuk membantu operator memeriksa kelengkapan, validitas, dan konsistensi dokumen.
- Proposal menyebut Evidence Checker, tetapi tidak mendefinisikan checklist atau aturan validasinya.
- Karena itu, buat checklist yang dapat dikonfigurasi atau placeholder yang jelas; jangan mengarang aturan substantif.
- Hasil pemeriksaan harus dapat disimpan dan ditelusuri jika aturan final telah disepakati.

### FR-08 — Pembaruan status layanan

- Pengguna berwenang dapat memperbarui status layanan dan keterangan terkait.
- Sistem menyimpan riwayat perubahan status atau setidaknya mencatat perubahan dalam activity log.
- Perubahan yang memenuhi aturan notifikasi memicu proses pengiriman notifikasi.

### FR-09 — Pembaruan status pembayaran

- Pengguna berwenang dapat memperbarui status pembayaran.
- Sistem menyimpan perubahan dan mencatat aktivitas.
- Perubahan yang dikonfigurasi memicu notifikasi.

### FR-10 — Dashboard monitoring dinamis

- Dashboard internal menampilkan ringkasan data layanan, pembayaran, dan verifikasi dokumen.
- Filter yang disebutkan: periode, status layanan, status pembayaran, dan status verifikasi dokumen.
- Grafik dan angka ringkasan harus mengikuti filter yang aktif.
- Nasabah mendapat tampilan sederhana untuk melihat layanan, dokumen, dan status miliknya sendiri; jangan tampilkan data agregat atau data nasabah lain.
- Sediakan empty state jika tidak ada data.
- Jangan menampilkan data yang tidak boleh diakses oleh pengguna tersebut.

### FR-11 — Laporan monitoring

- Sistem menyediakan laporan monitoring berdasarkan data layanan, pembayaran, dan status verifikasi.
- Admin dapat mencetak atau mengekspor laporan.
- Format file, isi kolom, pengurutan, rentang tanggal, dan nama laporan harus dikonfirmasi.
- Data laporan harus konsisten dengan filter dan data sumber.

### FR-12 — Laporan otomatis

- Sistem mendukung pembuatan laporan otomatis sesuai konfigurasi.
- Jadwal, penerima, format, dan pemicu laporan belum ditentukan.
- Jangan mengasumsikan laporan terjadwal sebelum aturan tersebut dikonfirmasi.
- Jika jadwal belum disepakati, implementasikan pembuatan laporan manual terlebih dahulu dan siapkan desain untuk penjadwalan.

### FR-13 — Notifikasi WhatsApp dan email

- Sistem dapat mengirim notifikasi untuk perubahan status layanan, pembayaran, atau verifikasi yang dikonfigurasi.
- Konten pesan harus informatif dan tidak menyertakan dokumen sensitif secara langsung.
- Provider, template, penerima, serta aturan opt-in/consent perlu ditentukan.
- Kegagalan provider tidak boleh membatalkan penyimpanan perubahan status yang telah berhasil.
- Catat status pengiriman dan informasi error yang aman untuk ditampilkan kepada admin.

### FR-14 — Retry dan backoff notifikasi

- Jika pengiriman notifikasi gagal, sistem dapat menjadwalkan percobaan ulang.
- Jeda percobaan ulang harus bertambah secara bertahap (backoff).
- Jumlah maksimal percobaan, strategi backoff, dan penanganan kegagalan permanen harus dapat dikonfigurasi setelah disepakati.
- Hindari pengiriman duplikat yang tidak perlu; gunakan identifier/idempotency yang sesuai bila didukung provider.
- Kegagalan dan hasil retry harus tercatat.

### FR-15 — Log aktivitas

- Sistem mencatat aktivitas penting, setidaknya login/logout, pembuatan/pembaruan data, perubahan status, dan pembuatan/ekspor laporan.
- Log menyimpan aktor, jenis aktivitas, waktu, dan referensi objek terkait jika tersedia.
- Log hanya dapat dilihat oleh peran yang berwenang.
- Hindari menyimpan password, token, atau isi dokumen sensitif dalam log.

### FR-16 — Personalisasi dashboard

- Sistem mendukung personalisasi dashboard.
- Bentuk personalisasi (misalnya pilihan widget atau susunan widget) belum ditentukan.
- Jangan membangun pengaturan yang kompleks sebelum kebutuhan dikonfirmasi.
- Preferensi pengguna tidak boleh mengubah hak akses atau hasil data dasar.

### FR-17 — Pencarian dan filter

- Sediakan pencarian dan filter pada daftar data yang relevan.
- Filter harus dapat dikombinasikan bila masuk akal dan hasilnya harus konsisten.
- Field pencarian spesifik ditentukan setelah skema data dikonfirmasi.
- Pencarian Nasabah hanya mengembalikan data yang terhubung dengan akun Nasabah yang sedang login.

### FR-18 — Validasi dan umpan balik UI

- Form harus menampilkan validasi field yang mudah dipahami.
- Tindakan simpan, gagal, berhasil, loading, dan data kosong harus memiliki feedback.
- Tindakan berisiko seperti menonaktifkan pengguna atau menghapus data (jika penghapusan diizinkan) memerlukan konfirmasi.

---

## 6. Kebutuhan Non-Fungsional

### NFR-01 — Keamanan

- Terapkan autentikasi dan otorisasi di sisi server.
- Validasi semua input di server; jangan hanya mengandalkan validasi browser.
- Simpan password menggunakan mekanisme hashing yang disediakan framework.
- Lindungi upload file dan unduhan dengan pemeriksaan hak akses.
- Simpan kredensial/provider secrets di environment variables atau secret manager, bukan repository.
- Hindari menampilkan stack trace atau detail internal kepada pengguna akhir.
- Gunakan HTTPS pada deployment produksi.

### NFR-02 — Integritas data

- Perubahan data harus divalidasi sebelum disimpan.
- Gunakan relasi dan constraint database yang sesuai setelah model data disepakati.
- Hindari status yang saling bertentangan akibat pembaruan yang tidak valid.
- Operasi perubahan status dan pencatatan terkait sebaiknya konsisten secara transaksional.

### NFR-03 — Kemudahan penggunaan

- Gunakan Bahasa Indonesia untuk UI.
- Navigasi harus jelas bagi admin dan operator.
- Daftar data menyediakan loading state, empty state, error state, serta pagination bila diperlukan.
- Tanggal dan waktu ditampilkan secara konsisten; zona waktu aplikasi perlu dikonfirmasi (default awal dapat menggunakan Asia/Jakarta).

### NFR-04 — Keandalan

- Kegagalan pengiriman notifikasi tidak boleh menghilangkan data utama.
- Retry/backoff harus terkontrol dan dapat ditelusuri.
- Error aplikasi perlu dicatat tanpa membocorkan data sensitif.

### NFR-05 — Maintainability

- Gunakan struktur kode modular sesuai framework yang dipilih.
- Pisahkan logika bisnis, akses data, integrasi notifikasi, dan presentasi sejauh sesuai stack.
- Sediakan file konfigurasi contoh tanpa secret.
- Dokumentasikan cara instalasi, menjalankan aplikasi, migrasi, seed data, dan pengujian.

### NFR-06 — Pengujian

- Gunakan Black Box Testing untuk menguji input dan output setiap fitur utama.
- Gunakan Equivalence Partitioning untuk membagi kelas input valid dan tidak valid bila relevan.
- Sediakan pengujian otomatis untuk logika bisnis kritis jika stack mendukung.
- Jangan mengklaim fitur telah lulus pengujian tanpa menjalankan dan melaporkan hasilnya.

---

## 7. Entitas Data Awal

Ini adalah kandidat entitas, bukan skema final. Coding Agent harus membuat ERD/skema awal dan meminta konfirmasi field yang belum diketahui.

| Entitas                                 | Tujuan                            | Catatan                                                                                      |
| --------------------------------------- | --------------------------------- | -------------------------------------------------------------------------------------------- |
| User                                    | Akun pengguna dan peran           | Mendukung Admin, Operator, dan Nasabah; detail profil perlu dikonfirmasi                     |
| CustomerProfile (atau relasi pada User) | Identitas/profil Nasabah          | Hubungkan akun Nasabah ke data layanan; hindari duplikasi data yang tidak perlu              |
| Service/TradeService                    | Data layanan perdagangan          | Pemohon/Nasabah, jenis layanan, tanggal pengajuan, status, keterangan disebut dalam proposal |
| PaymentRecord                           | Catatan pembayaran internasional  | Monitoring status saja; bukan transaksi bank                                                 |
| ServiceDocument                         | File dan metadata dokumen layanan | Relasi ke layanan dan akun Nasabah terkait; pembatasan akses wajib                           |
| DocumentVerification                    | Hasil/status verifikasi dokumen   | Status dan checklist belum didefinisikan                                                     |
| EvidenceCheck                           | Hasil checklist Evidence Checker  | Struktur mengikuti aturan yang dikonfirmasi                                                  |
| ActivityLog                             | Jejak aktivitas pengguna          | Aktor, aktivitas, waktu, objek terkait                                                       |
| Notification                            | Riwayat notifikasi                | Channel, penerima, status, percobaan, error yang aman                                        |
| DashboardPreference                     | Preferensi dashboard pengguna     | Bentuk personalisasi perlu dikonfirmasi                                                      |
| Report/ReportRun (opsional)             | Catatan pembuatan/ekspor laporan  | Dibutuhkan bila laporan otomatis/riwayatnya perlu ditelusuri                                 |

### Prinsip data

- Gunakan primary key dan foreign key yang konsisten.
- Tentukan aturan penghapusan/arsip sebelum menerapkan cascading delete.
- Simpan metadata file terpisah dari isi file jika sesuai arsitektur.
- Jangan menyimpan data pembayaran sensitif yang tidak diperlukan.
- Status harus berasal dari nilai yang tervalidasi, bukan teks bebas, setelah daftar status disepakati.
- Tentukan audit trail untuk perubahan status penting.

---

## 8. Halaman dan Navigasi Awal

Struktur berikut merupakan rancangan awal yang boleh disesuaikan setelah rancangan UI ditinjau.

### Umum

- Login
- Profil/pengaturan akun
- Logout

### Admin

- Dashboard Monitoring
- Data Operator
- Data Layanan
- Data Pembayaran
- Dokumen & Verifikasi
- Laporan Monitoring
- Riwayat Notifikasi
- Log Aktivitas
- Pengaturan Notifikasi
- Personalisasi Dashboard

### Operator

- Dashboard Monitoring
- Data Layanan
- Data Pembayaran
- Dokumen & Verifikasi
- Evidence Checker
- Profil/pengaturan akun

### Nasabah

- Dashboard/Status Layanan Saya
- Daftar Layanan Saya
- Detail Layanan
- Dokumen Saya / Unggah Dokumen
- Status Verifikasi Dokumen
- Profil dan Kontak Notifikasi

Menu harus mengikuti hak akses. Jangan menampilkan menu sebagai pengganti enforcement otorisasi backend.

---

## 9. Kriteria Penerimaan (Acceptance Criteria)

### AC-01 — Login dan hak akses tiga peran

- Admin, Operator, dan Nasabah dengan kredensial valid dapat login.
- Kredensial salah ditolak dengan pesan yang aman.
- Operator tidak dapat mengakses endpoint khusus Admin hanya dengan mengetik URL secara langsung.
- Nasabah tidak dapat mengakses endpoint internal Admin/Operator.
- Nasabah tidak dapat melihat atau mengubah layanan, dokumen, atau informasi pembayaran milik Nasabah lain, termasuk dengan mengganti ID/URL.
- Logout mengakhiri sesi secara benar.

### AC-02 — Data layanan

- Pengguna berwenang dapat membuat dan memperbarui data dengan input valid.
- Input tidak valid ditolak dan field terkait menampilkan pesan.
- Data tersimpan muncul pada daftar dan dapat ditemukan menggunakan pencarian/filter yang tersedia.

### AC-03 — Data pembayaran

- Pengguna berwenang dapat mencatat dan memperbarui status pembayaran.
- Sistem tidak menjalankan transfer atau transaksi perbankan.
- Perubahan tercatat dalam log aktivitas.

### AC-04 — Dokumen dan verifikasi

- Nasabah dapat mengunggah file hanya ke layanan miliknya.
- File yang memenuhi aturan yang telah dikonfigurasi dapat diunggah; file yang tidak memenuhi aturan ditolak.
- Pengguna tanpa izin tidak dapat mengakses dokumen.
- Nasabah dapat melihat status verifikasi dokumen miliknya, tetapi tidak dapat mengubah status internal.
- Operator dapat mencatat hasil pemeriksaan dan status verifikasi sesuai pilihan yang disepakati.

### AC-05 — Dashboard dan status Nasabah

- Filter periode dan status memengaruhi ringkasan/grafik dashboard internal secara konsisten.
- Dashboard menampilkan kondisi kosong bila tidak ada hasil.
- Dashboard Nasabah hanya menampilkan layanan dan status yang terhubung ke akun yang sedang login.
- Nasabah tidak dapat memperoleh data Nasabah lain melalui pencarian, filter, URL, atau endpoint API.

### AC-06 — Notifikasi

- Perubahan status yang dikonfigurasi menghasilkan tugas notifikasi.
- Status pengiriman dapat ditelusuri.
- Kegagalan notifikasi tidak membatalkan perubahan status utama.
- Percobaan ulang menggunakan retry/backoff dan berhenti sesuai batas konfigurasi.

### AC-07 — Laporan

- Laporan mengikuti filter dan rentang tanggal yang dipilih.
- Hasil cetak/ekspor sesuai format yang disepakati.
- Pembuatan atau ekspor laporan dicatat dalam activity log.

### AC-08 — Log aktivitas

- Aktivitas penting mencatat aktor, waktu, aksi, dan objek terkait bila tersedia.
- Pengguna yang tidak berwenang tidak dapat melihat log.

### AC-09 — Personalisasi dashboard

- Preferensi dashboard tersimpan sesuai aturan yang disepakati.
- Personalisasi tidak mengubah data sumber atau hak akses.

### AC-10 — Isolasi data Nasabah

- Nasabah A hanya dapat melihat layanan dan dokumen yang terhubung ke akun Nasabah A.
- Mengganti identifier pada URL/request tidak memberikan akses ke data Nasabah B.
- Upaya akses yang tidak sah ditolak dan dicatat sesuai kebijakan log keamanan.

---

## 10. Rencana Pengembangan Bertahap

Ikuti semangat RAD: bangun prototipe, evaluasi, lalu lanjutkan berdasarkan persetujuan pengguna.

### Tahap 0 — Klarifikasi dan perencanaan

- Periksa repository dan stack yang sudah ada sebelum mengubah file.
- Catat pertanyaan yang belum terjawab.
- Tetapkan aktor, status, field, dan batasan file.
- Buat `docs/assumptions.md` dan `docs/open-questions.md`.
- Buat use case, activity flow, ERD awal, dan sketsa halaman.

**Definition of Done:** keputusan awal dan asumsi terdokumentasi serta disetujui sebelum pembangunan fitur besar.

### Tahap 1 — Fondasi aplikasi

- Siapkan konfigurasi, koneksi database, autentikasi, peran Admin/Operator/Nasabah, layout utama, dan navigasi.
- Implementasikan strategi akun Nasabah yang sudah dikonfirmasi (registrasi mandiri atau dibuat/diaktifkan Admin).
- Siapkan relasi akun Nasabah dengan layanan secara aman.
- Tambahkan migrasi serta seed data development yang tidak sensitif.
- Buat test untuk autentikasi dan authorization.

**Definition of Done:** aplikasi dapat dijalankan sesuai instruksi repository; login dan hak akses ketiga peran teruji, termasuk uji bahwa Nasabah tidak dapat mengakses data Nasabah lain.

### Tahap 2 — Data inti

- CRUD data layanan.
- CRUD catatan pembayaran.
- Pencarian, filter, validasi, dan log aktivitas.
- Relasi antar data sesuai keputusan yang disepakati.

**Definition of Done:** operasi CRUD dan validasi berjalan serta diuji.

### Tahap 3 — Dokumen dan verifikasi

- Upload oleh Nasabah pada layanan miliknya dan oleh Operator sesuai hak akses.
- Penyimpanan metadata, relasi kepemilikan, dan kontrol akses file.
- Portal Nasabah untuk melihat dokumen serta status verifikasi miliknya.
- Proses verifikasi dokumen oleh Operator.
- Evidence Checker setelah checklist dikonfirmasi.

**Definition of Done:** alur dokumen dapat dijalankan dan akses tidak sah ditolak.

### Tahap 4 — Dashboard dan laporan

- Ringkasan data.
- Filter periode/status.
- Laporan monitoring dan ekspor/cetak sesuai format final.

**Definition of Done:** hasil dashboard/laporan sesuai data sumber dan filter.

### Tahap 5 — Notifikasi

- Integrasi provider WhatsApp/email setelah provider dan kredensial tersedia.
- Template pesan dan pemicu.
- Retry/backoff, pencatatan status pengiriman, dan penanganan error.

**Definition of Done:** pengiriman sukses dan gagal dapat diuji tanpa mengganggu penyimpanan data utama.

### Tahap 6 — Personalisasi dan hardening

- Personalisasi dashboard yang telah disepakati.
- Review keamanan, validasi, performa dasar, dokumentasi, dan regresi.
- Uji Black Box untuk seluruh fitur yang telah dibangun.

**Definition of Done:** seluruh test case yang direncanakan dijalankan; hasil pass/fail didokumentasikan.

---

## 11. Strategi Pengujian

Buat test case Black Box untuk setiap fitur. Minimal cakupan:

- Login valid, password salah, field kosong, akun nonaktif.
- Pembatasan hak akses Admin dan Operator.
- Input data layanan valid/tidak valid.
- Input data pembayaran valid/tidak valid.
- Upload file yang diizinkan dan ditolak.
- Akses dokumen oleh pengguna berizin/tidak berizin.
- Perubahan status valid dan transisi status tidak valid.
- Evidence Checker untuk item lengkap/tidak lengkap setelah aturan tersedia.
- Dashboard dengan data, tanpa data, dan berbagai filter.
- Laporan dengan rentang tanggal dan filter berbeda.
- Notifikasi sukses, gagal, retry, batas retry tercapai, serta kegagalan provider.
- Activity log untuk tindakan penting.
- Personalisasi dashboard tersimpan dan tidak memengaruhi hak akses.
- Logout dan akses ulang setelah sesi berakhir.

Untuk setiap test case, catat: ID, prasyarat, langkah, input, hasil yang diharapkan, hasil aktual, status Pass/Fail, dan catatan.

---

## 12. Panduan Kerja untuk Coding Agent

1. **Inspeksi sebelum mengubah:** baca struktur repository, dokumentasi, konfigurasi, dependency, dan test yang sudah ada.
2. **Hormati stack yang sudah tersedia:** jangan mengganti framework, database, atau pola arsitektur tanpa persetujuan.
3. **Jangan langsung membangun semua fitur dalam satu prompt.** Kerjakan per tahap dan per modul.
4. **Mulai dengan rencana:** rangkum pemahaman, daftar file yang akan dibuat/diubah, model data, risiko, dan pertanyaan yang masih terbuka.
5. **Tunggu klarifikasi pada keputusan berisiko tinggi:** terutama konflik aktor nasabah, status bisnis, skema pembayaran, Evidence Checker, dan provider notifikasi.
6. **Implementasikan secara vertikal:** satu fitur lengkap dari UI sampai penyimpanan, authorization, validasi, dan test sebelum pindah ke fitur berikutnya.
7. **Jaga keamanan:** tidak ada secret di source code; jangan membuat endpoint tanpa otorisasi; jangan membuka dokumen secara publik.
8. **Migrasi harus aman:** jangan menghapus atau mereset data yang ada tanpa persetujuan eksplisit.
9. **Uji setelah perubahan:** jalankan test/linter/build yang relevan dan laporkan perintah serta hasil sebenarnya.
10. **Perbarui dokumentasi:** catat cara menjalankan aplikasi, environment variables, migrasi, seed, test, dan keputusan desain.
11. **Jangan mengklaim selesai tanpa bukti:** bedakan fitur yang sudah dibuat, diuji, belum diuji, dan masih memakai mock.
12. **Integrasi eksternal:** jika kredensial belum tersedia, gunakan adapter/mock yang jelas untuk development; jangan berpura-pura pesan benar-benar terkirim.
13. **Perubahan scope:** jika implementasi membutuhkan keputusan di luar PRD, ajukan opsi singkat beserta dampaknya terlebih dahulu.

### Format laporan setiap tahap

Setelah setiap tahap, laporkan:

- Ringkasan yang dikerjakan.
- File penting yang dibuat/diubah.
- Perubahan database.
- Cara menjalankan atau mencoba fitur.
- Pengujian yang dijalankan dan hasilnya.
- Hal yang belum selesai, asumsi, dan pertanyaan untuk tahap berikutnya.

---

## 13. Pertanyaan yang Harus Dijawab Sebelum Implementasi Final

1. Bagaimana akun Nasabah dibuat: registrasi mandiri, undangan, atau dibuat/diaktifkan Admin?
2. Bagaimana akun Nasabah diverifikasi sebelum dapat mengakses data layanan?
3. Apa saja field wajib untuk data layanan dan data pembayaran?
4. Apa daftar resmi status layanan, status pembayaran, dan status verifikasi dokumen?
5. Apa checklist dan aturan **Evidence Checker**?
6. Jenis/ukuran file apa yang boleh diunggah dan berapa lama dokumen disimpan?
7. Provider WhatsApp dan email apa yang akan digunakan?
8. Peristiwa apa yang mengirim notifikasi, kepada siapa, dan dengan template apa?
9. Berapa maksimal retry dan seperti apa aturan backoff?
10. Apa format laporan yang dibutuhkan, dan apakah laporan harus dijalankan terjadwal?
11. Apa saja komponen yang bisa dipersonalisasi pada dashboard?
12. Apakah operator boleh mencetak/mengekspor laporan dan melihat log aktivitas?
13. Apakah sudah ada stack, repository, desain UI, atau deployment target yang wajib digunakan?

---

## 14. Definisi Selesai Produk (MVP)

MVP dapat dianggap siap untuk evaluasi pengguna ketika:

- Admin, Operator, dan Nasabah dapat login dengan hak akses yang benar.
- Akun Nasabah terhubung ke data layanan yang tepat dan isolasi data antarnasabah telah diuji.
- Data layanan dan catatan pembayaran dapat dikelola dan dicari.
- Nasabah dapat mengunggah dokumen untuk layanan miliknya dan melihat status layanan/verifikasi miliknya.
- Dokumen dapat diverifikasi sesuai aturan yang telah disepakati.
- Dashboard monitoring internal menampilkan ringkasan dan filter yang berfungsi, sedangkan dashboard Nasabah hanya menampilkan datanya sendiri.
- Laporan monitoring dapat dibuat dan diekspor sesuai format final.
- Perubahan penting tercatat di activity log.
- Notifikasi WhatsApp/email dapat diuji dengan provider yang dikonfigurasi, termasuk jalur gagal dan retry/backoff.
- Fitur personalisasi yang disepakati tersedia.
- Test Black Box utama telah dijalankan dan hasilnya didokumentasikan.
- Dokumentasi instalasi, konfigurasi, migrasi, seed, dan pengujian tersedia.

**Catatan akhir:** Prioritaskan fungsi inti dan data yang benar terlebih dahulu. Fitur notifikasi, laporan terjadwal, personalisasi, dan portal nasabah harus mengikuti keputusan ruang lingkup yang dikonfirmasi, bukan asumsi Coding Agent.
