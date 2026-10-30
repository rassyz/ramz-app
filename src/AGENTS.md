# AGENTS.md — Project Instructions

Panduan ini menjadi aturan kerja untuk AI Agent yang mengembangkan proyek Laravel ini. Ikuti aturan berikut bersama dokumentasi proyek dan konvensi yang sudah ada.

## 1. Konteks Teknologi

- Framework: Laravel 13.
- PHP: 8.4.
- Admin panel: Filament 5.
- Ikuti versi aktual seluruh dependency di `composer.lock` dan `package-lock.json` / lockfile yang digunakan.
- Jangan mengasumsikan API atau fitur dari versi paket lain. Periksa dokumentasi yang sesuai dengan versi terpasang sebelum mengimplementasikan perubahan.

## 2. Prioritas dan Konsistensi

- Periksa implementasi, struktur direktori, konvensi, dan komponen yang sudah ada sebelum membuat perubahan.
- Utamakan konsistensi dengan pola proyek yang berjalan. Jangan melakukan refactoring yang tidak diperlukan untuk tugas.
- Jika instruksi bertentangan, utamakan persyaratan eksplisit pengguna dan batasan keamanan; kemudian ikuti dokumen proyek yang relevan serta pola implementasi yang sudah ada. Jelaskan konflik penting sebelum melanjutkan jika konflik menghalangi implementasi yang benar.
- Jangan mengubah dependency, konfigurasi penting, atau arsitektur utama tanpa persetujuan pengguna.
- Jangan menghapus test, data, atau kode yang sudah ada tanpa persetujuan.
- Jangan membuat dokumentasi tambahan kecuali diminta pengguna atau diwajibkan oleh workflow proyek.

## 3. Arsitektur Wajib: Service Repository Pattern

**Gunakan Service Repository Pattern untuk setiap fitur baru dan saat mengubah fitur yang secara material melibatkan business logic atau akses data.** Terapkan secara proporsional; jangan menambah lapisan yang tidak dibutuhkan hanya demi formalitas.

### Tanggung jawab tiap lapisan

- **Controller / Filament Page atau Action:** menangani interaksi framework, menerima input yang sudah divalidasi, memanggil Service, dan menyajikan hasil. Hindari menaruh business logic atau query data kompleks di lapisan ini.
- **Form Request / validasi:** tempatkan validasi HTTP pada Form Request bila sesuai. Untuk alur Filament, gunakan mekanisme validasi Filament yang sesuai.
- **Service:** menjadi tempat business logic, aturan bisnis, orkestrasi proses, dan koordinasi beberapa operasi.
- **Repository:** menjadi tempat akses dan query data melalui Eloquent atau mekanisme persistence yang sesuai. Hindari menaruh aturan bisnis utama di Repository.
- **Model:** merepresentasikan data, relasi, casts, scopes, dan perilaku yang memang menjadi tanggung jawab model.
- **Resource / DTO:** gunakan API Resource, DTO, atau objek hasil hanya bila sesuai dengan kontrak dan kompleksitas fitur. Jangan membuatnya secara otomatis jika tidak diperlukan.

### Aturan implementasi

- Sebelum coding, telusuri pola Repository dan Service yang sudah ada. Ikuti namespace, penamaan, struktur folder, dependency injection, dan gaya implementasi proyek.
- Jika belum ada pola yang mapan, gunakan struktur yang konsisten, misalnya `app/Repositories`, `app/Services`, dan interface hanya jika dibutuhkan oleh desain atau kontrak aplikasi.
- Inject dependency melalui constructor atau mekanisme dependency injection Laravel; hindari membuat dependency secara manual ketika container Laravel dapat menyediakannya.
- Untuk operasi baca/tulis yang merupakan bagian dari satu proses bisnis atomik, gunakan database transaction bila memang diperlukan.
- Pertahankan authorization, validasi, mass-assignment protection, penanganan error, dan keamanan yang sudah berlaku.
- Jangan memindahkan seluruh operasi Eloquent secara membabi buta ke Repository jika hal itu bertentangan dengan pola proyek yang sudah ada; evaluasi konteks dan jaga tanggung jawab tiap lapisan.
- Jangan membuat interface untuk setiap Repository atau Service secara otomatis. Buat interface ketika diperlukan untuk kontrak, substitusi implementasi, pengujian, atau batas arsitektur.
- Hindari overengineering, duplikasi query, pemanggilan database berulang yang tidak perlu, dan abstraksi tanpa manfaat nyata.
- Saat memperbaiki fitur lama, lakukan perubahan bertahap dan pertahankan kompatibilitas perilaku kecuali pengguna meminta perubahan.
- Jika tugas sederhana tidak membutuhkan semua lapisan, tetap jaga pemisahan tanggung jawab yang wajar dan jelaskan alasan bila tidak membuat Repository atau Service tertentu.

## 4. Alur Kerja untuk Setiap Fitur

### Sebelum implementasi

1. Baca instruksi proyek, dokumentasi terkait, dan skill yang relevan.
2. Periksa struktur direktori dan implementasi fitur yang serupa.
3. Periksa skema database dan relasi yang relevan sebelum mengubah migration, model, atau query.
4. Jika tersedia, gunakan Laravel Boost untuk mencari dokumentasi sesuai versi paket yang terpasang. Untuk perubahan kode, cari dokumentasi relevan terlebih dahulu.
5. Identifikasi kebutuhan fitur, dependensi, risiko, dan kriteria penerimaan.
6. Rencanakan file yang perlu dibuat atau diubah. Jangan mulai mengubah kode jika pengguna meminta tahap planning saja.

### Saat implementasi

1. Ikuti rencana yang disetujui dan batasi perubahan pada ruang lingkup tugas.
2. Gunakan perintah Artisan yang sesuai untuk membuat file Laravel atau Filament, dengan opsi yang tepat dan `--no-interaction` jika didukung.
3. Terapkan Service Repository Pattern sesuai aturan pada bagian 3.
4. Ikuti konvensi penamaan, struktur direktori, dan gaya kode yang sudah digunakan.
5. Jangan mengubah dependency atau melakukan refactoring luas tanpa persetujuan.

### Setelah implementasi

1. Jalankan test yang relevan; utamakan test feature untuk perilaku aplikasi dan gunakan factory yang tersedia.
2. Jika file PHP diubah, jalankan formatter proyek, misalnya `vendor/bin/pint --dirty --format agent` jika tersedia dan sesuai.
3. Verifikasi hasil dengan perintah atau tool yang sesuai. Jangan mengklaim test, formatter, atau pemeriksaan berhasil jika belum dijalankan.
4. Laporkan ringkasan perubahan, file utama yang dibuat/diubah, hasil test/formatting, serta kendala atau verifikasi yang belum dilakukan.
5. Jangan membuat skrip verifikasi atau menjalankan Tinker untuk hal yang sudah dibuktikan oleh test yang sesuai.

## 5. Struktur Aplikasi dan Laravel

- Jangan membuat direktori tingkat atas atau mengubah struktur utama tanpa alasan dan persetujuan.
- Gunakan pola Laravel yang idiomatis dan API yang sesuai dengan versi aktual.
- Gunakan named route dan helper `route()` saat membangun URL internal bila sesuai.
- Untuk API, ikuti konvensi API yang sudah ada. Gunakan API Resource dan versioning bila cocok; jangan memperkenalkan pola baru yang tidak konsisten.
- Periksa migration, model, factory, seeder, dan relasi yang relevan sebelum mengubah struktur data.
- Gunakan transaksi database untuk menjaga konsistensi bila suatu operasi bisnis membutuhkan atomicity.
- Jangan pernah menganggap file dapat diakses publik secara default. Atur visibility secara eksplisit sesuai kebutuhan.
- Hindari perubahan schema yang merusak data tanpa rencana migrasi yang aman.

## 6. PHP dan Kualitas Kode

- Gunakan kurung kurawal pada struktur kontrol, termasuk untuk isi satu baris.
- Gunakan constructor property promotion jika membuat constructor yang sesuai.
- Berikan type hint parameter dan return type yang eksplisit pada method jika memungkinkan.
- Gunakan nama variabel dan method yang deskriptif.
- Utamakan PHPDoc untuk menjelaskan tipe kompleks, termasuk array shape bila relevan.
- Hindari komentar yang hanya mengulang kode; gunakan komentar untuk menjelaskan alasan atau logika yang benar-benar kompleks.
- Ikuti konfigurasi formatter, linter, dan static analysis yang sudah ada di proyek.

## 7. Laravel Boost

- Jika Laravel Boost terpasang dan tool MCP-nya tersedia, utamakan tool Boost yang relevan daripada alternatif manual.
- Gunakan tool schema database untuk memeriksa struktur tabel sebelum menulis migration atau model jika tersedia.
- Gunakan tool query database hanya untuk query baca-saja saat memeriksa data.
- Gunakan tool URL proyek untuk memastikan scheme, host, dan port yang benar sebelum membagikan URL jika tersedia.
- Gunakan tool browser logs untuk memeriksa error terbaru jika tersedia.
- Gunakan `search-docs` sebelum perubahan kode bila tool tersedia; gunakan beberapa query topik yang relevan dan batasi paket sesuai kebutuhan.
- Jika tool Boost tidak tersedia, gunakan dokumentasi resmi dan perintah lokal yang sesuai. Jangan mengklaim telah menggunakan tool yang tidak tersedia.

## 8. Filament 5

- Ikuti konvensi Filament yang sudah digunakan proyek dan dokumentasi resmi yang sesuai dengan versi terpasang.
- Gunakan perintah Artisan khusus Filament jika tersedia, periksa opsi terlebih dahulu, dan gunakan `--no-interaction` bila didukung.
- Utamakan API dan namespace yang benar-benar berlaku di Filament 5; jangan menyalin contoh dari versi berbeda tanpa verifikasi.
- Gunakan static `make()` untuk inisialisasi komponen jika itu konvensi API yang relevan.
- Pisahkan business logic dari Resource, Page, Widget, dan Action. Delegasikan proses bisnis ke Service, serta akses data kompleks ke Repository sesuai kebutuhan.
- Untuk operasi tabel, form, action, dan pengujian Livewire/Filament, ikuti API versi yang terpasang dan pola pengujian yang sudah ada.
- Jangan mengasumsikan perilaku visibility, layout, namespace, tipe properti, atau API action tanpa verifikasi.

## 9. Testing dan Verifikasi

- Proyek menggunakan test suite yang benar-benar terpasang. Periksa konfigurasi dan pola test sebelum membuat test baru.
- Gunakan `php artisan make:test` dengan opsi yang sesuai jika membuat test.
- Gunakan factory dan state yang tersedia ketika menyiapkan data pengujian.
- Jangan menghapus atau melemahkan test untuk membuat implementasi terlihat berhasil tanpa persetujuan.
- Jalankan test terfokus terlebih dahulu, lalu test yang lebih luas bila relevan dan memungkinkan.
- Bila test tidak dapat dijalankan, jelaskan alasannya dan sebutkan pemeriksaan yang masih diperlukan.
- Jika PHP diubah, jalankan Pint sesuai konfigurasi proyek jika tersedia.

## 10. Dependency, Keamanan, dan Perubahan Berisiko

- Minta persetujuan sebelum menambah, menghapus, atau memperbarui dependency.
- Minta persetujuan sebelum menjalankan tindakan yang berisiko menghapus data, mereset database, atau mengubah lingkungan bersama.
- Jangan menampilkan secret, token, kredensial, atau data sensitif dalam output, log, dokumentasi, atau commit.
- Jangan menonaktifkan authorization, validasi, proteksi CSRF, atau kontrol keamanan untuk sekadar membuat test lolos.
- Jangan menjalankan perintah destruktif tanpa memahami dampaknya dan memperoleh persetujuan bila diperlukan.

## 11. Dokumentasi dan Pelaporan

- Baca dokumentasi proyek yang relevan sebelum membuat keputusan arsitektur.
- Perbarui dokumentasi yang sudah ada bila perubahan memang membuatnya tidak akurat dan ruang lingkup tugas mengizinkan.
- Jangan membuat file dokumentasi baru tanpa permintaan, kecuali diwajibkan oleh workflow proyek.
- Berikan laporan akhir yang ringkas dan faktual:
    - perubahan yang dilakukan;
    - file penting yang dibuat atau diubah;
    - test dan formatter yang dijalankan beserta hasilnya;
    - keterbatasan, risiko, atau tindak lanjut yang masih diperlukan.
