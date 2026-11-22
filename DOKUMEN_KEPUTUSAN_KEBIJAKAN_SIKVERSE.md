# DOKUMEN KEPUTUSAN DAN PERSETUJUAN KEBIJAKAN SIKVERSE

**Tahap:** 2A — Daftar Keputusan dan Persetujuan Kebijakan  
**Berdasarkan:** Laporan Audit Read-Only SIKVERSE tanggal 22 November 2026, Revisi Rencana Tahap 2, dan keputusan yang dinyatakan pengguna  
**Tanggal dokumen:** 22 November 2026  
**Status dokumen:** Catatan keputusan pengguna. **Bukan persetujuan institusi formal.**  
**Status implementasi:** Implementasi teknis berdasarkan keputusan pengguna sedang dilakukan. Persetujuan institusi formal **belum diberikan**.

---

## 0. Batasan Dokumen Ini

Dokumen ini disusun dengan mematuhi batasan berikut:

- Hanya pemeriksaan **read-only** terhadap kode, konfigurasi, dan dokumentasi.
- **Tidak mengubah kode**, database, konfigurasi, atau file apa pun selain dokumen ini.
- **Tidak menjalankan** migration, rollback, seeder, import, atau perintah yang berpotensi menulis data.
- **Tidak menyentuh** `app/Services/SantriSpreadsheet.php`.
- **Tidak menghapus** tabel atau data legacy.
- **Tidak commit**, push, atau menimpa pekerjaan pengguna yang belum di-commit.
- Semua rekomendasi di bawah adalah **saran teknis**, bukan penetapan kebijakan. Kebijakan hanya ditetapkan oleh pihak SIK.
- Setiap keputusan menunggu tanda tangan/konfirmasi pihak SIK agar berstatus persetujuan institusi formal.

> **Pembaruan 22 November 2026:** Batasan read-only di atas berlaku untuk audit G0. Pengguna kemudian menyatakan keputusan 1–20 dan mengizinkan perubahan kode/DB secara langsung. Implementasi teknis kini dibatasi oleh aturan berikut: backup kode dan DB dibuat serta diverifikasi lebih dulu; migration baru saja yang digunakan; migration yang sudah dijalankan, tabel legacy, dan data historis tidak diubah atau dihapus; `app/Services/SantriSpreadsheet.php` tidak disentuh; `.env` dan konfigurasi produksi tidak diubah; pekerjaan kerja sama tim tetap Bootstrap + CSS kustom tanpa refactor Tailwind massal.

### 0.1 Catatan cakupan

20 poin keputusan ini mencakup 20 dari 26 temuan audit. Enam temuan lainnya bersifat teknis dan tidak memerlukan kebijakan baru, sehingga tidak dimasukkan sebagai keputusan kebijakan:

| Temuan audit | Alasan tidak menjadi keputusan kebijakan baru |
|---:|---|
| No. 11 — Navigasi Santri/Ka-Lab | Daftar menu akhir mengikuti Keputusan 7, 8, 10, dan 14. Spesifikasi navigasi per role didokumentasikan pada §3.1 dan tetap **BELUM DIPUTUSKAN**. |
| No. 12 — Notifikasi keberhasilan pengubahan kata sandi | Perbaikan alur notifikasi antarmuka, mengikuti Keputusan 15 (titik akhir standar). |
| No. 13 — NIM `SIK001` | Koreksi data individual memerlukan `[PERSETUJUAN-DATA]`. Kebijakan akses informasi berbasis NIM dibahas pada Keputusan 4. |
| No. 15 — Constraint lintas periode | Turunan langsung dari Keputusan 5 (master periode resmi). |
| No. 20 — N+1 / query berulang | Optimasi teknis; tidak mengubah aturan bisnis. |
| No. 21 — Email hasil impor setelah proses komit | Teknis; bergantung pada Keputusan 19. Pemilihan driver antrean apa pun **tetap memerlukan proses worker** agar pesan diproses. |

### 0.2 Keputusan pengguna — bukan persetujuan institusi

Pengguna telah menyatakan hasil diskusi untuk 20 keputusan. Pernyataan tersebut dicatat di sini sebagai **keputusan pengguna**, bukan `[KEBIJAKAN-SIK]` atau tanda tangan institusi. **Status persetujuan institusi tetap BELUM DIBERIKAN untuk seluruh keputusan**, dan checklist di §7 sengaja dikosongkan. Rincian yang belum ditentukan tetap ditandai demikian.

| No. | Keputusan pengguna | Detail yang masih belum ditentukan |
|---:|---|---|
| 1 | Kelulusan bukan akibat otomatis dari kehadiran; Admin mengambil keputusan kelulusan terpisah. Pengaman diterapkan untuk transaksi baru; data lama dibekukan. | Status hukum `kelulusan_sik.id=2` dan tindakan atas sertifikat lama. |
| 2 | Yang dihitung hanya pertemuan yang benar-benar terlaksana; pertemuan dibatalkan tidak termasuk penyebut. Data historis tidak dikoreksi otomatis. | Koreksi final `pertemuan.id=1` dan mekanisme backdate resmi. |
| 3 | Ambang kehadiran adalah **85% dari pertemuan yang benar-benar terlaksana**. Angka 14/16 dan `87,5%` hanya ilustrasi lama, bukan kebijakan. Rekap adalah data turunan. | Metode pembulatan untuk jumlah hadir minimum. |
| 4 | Akun Santri dan Dosen dibuat oleh Admin; registrasi sendiri dinonaktifkan; NIM hanya boleh berisi digit. | Panjang NIM dan penanganan akun Santri lama tanpa `user_id`. |
| 5 | Master periode tetap dikelola Admin; koreksi periode historis tidak dilakukan tanpa data resmi. | Master periode final (nama, tanggal, status) dan aturan jumlah periode aktif. |
| 6 | Sertifikat hanya diterbitkan setelah Admin memutuskan kelulusan. Data dan berkas lama tidak diubah otomatis. | Penyedia storage produksi serta nasib PDF lama yang hilang. |
| 7 | Tandai selesai kegiatan bersama hanya boleh oleh pemilik kegiatan/kelompok host, Dosen pengganti yang ditunjuk, atau Admin. | Wewenang rinci Leader tidak didefinisikan lebih lanjut. |
| 8 | Dosen dengan status tidak aktif mendapat **akses baca-saja**; tidak boleh menulis atau menyetujui perubahan. | Daftar status resmi selain `Aktif`/`Tidak Aktif`. |
| 9 | `app/Services/SantriSpreadsheet.php` tetap dilindungi dan tidak disentuh. | Kontrak impor final tidak ditetapkan tanpa `[PERSETUJUAN-SANTRI-SPREADSHEET]`. |
| 10 | Registrasi akun publik dinonaktifkan. Admin yang membuat akun Santri/Dosen; pendaftaran peserta tidak dibuka untuk publik. | Alur undangan/verifikasi tambahan bila diperlukan. |
| 11 | Tidak ada detail baru dari pengguna; perilaku kustom saat ini dipertahankan dan kegagalan baseline dicatat, bukan diubah demi lulus. | Kelas notifikasi final dan identitas pengirim. |
| 12 | Duplikat kegiatan lama tidak digabung atau dihapus; konsistensi dijaga untuk data baru. | Penamaan pembeda atau penggabungan data historis. |
| 13 | Tabel legacy dan data historis tetap disimpan; tidak ada penghapusan tabel, migration lama, atau relasi arsip. | Penandaan deprecated lebih lanjut. |
| 14 | Tidak ada rute publik baru untuk Jadwal/Mata Pelajaran; data `mata_pelajaran` tetap dipakai Nilai. | Aktivasi ulang modul Jadwal bila diperlukan. |
| 15 | Tidak ada detail baru dari pengguna; titik akhir yang ada dipertahankan. | Satu titik akhir password standar. |
| 16 | Riwayat absensi tidak dihapus; perubahan status baru dicatat sebagai entri riwayat. Data lama tidak direkonsiliasi otomatis. | Rekonsiliasi `riwayat_absensi.id=1`. |
| 17 | Keanggotaan terbaru ditentukan dari `tanggal_masuk` terbesar; bila kosong, fallback ke `id` terbesar. | Tidak ada. |
| 18 | Standar tampilan tetap **Bootstrap + CSS kustom**; tidak ada refactor Tailwind massal. | Penghapusan dependency Tailwind menyeluruh. |
| 19 | Konfigurasi produksi tidak diterapkan dan `.env` tidak diubah. Untuk lingkungan internal, antrean `sync` digunakan; bila `database`/Redis dipilih, worker wajib berjalan. | Domain, HTTPS, SMTP, storage sertifikat produksi, dan verifikasi email produksi. |
| 20 | Hanya migration baru yang boleh dibuat; dijalankan hanya pada DB isolasi/testing. Migration yang sudah dijalankan, skema destruktif, tabel legacy, dan data historis tidak diubah. | Perapian status migration di DB aktif. |

### 0.3 Bukti backup sebelum implementasi

| Jenis | Lokasi | Ukuran | SHA256 | Verifikasi |
|---|---|---:|---|---|
| Kode | `C:\Users\MUHARI~1\AppData\Local\Temp\SIK_PreImplement_Code_20261122_191541.tar.gz` | 1.728.816 byte | `699AE6845611C63D19F7D6056656FA6CFA330CB766E5E69DB4EA55C5DC5F0DE9` | Hash diverifikasi ulang sebelum edit. |
| Database | `C:\Users\MUHARI~1\AppData\Local\Temp\SIK_PreImplement_DB_20261122_191541.sql` | 44.812 byte | `FF5B1807F11F418FFB995BD2ACB97B8868BD49B684481A8CA252CBFD440410B5` | Hash diverifikasi ulang sebelum edit. |
| File dilindungi | `app/Services/SantriSpreadsheet.php` | 18.765 byte | `5E9FB1807308BD79692BEF4AC0344E462BF070DC2D68EC24D9C32494C24AEFA8` | Hash diverifikasi ulang sebelum edit; harus tetap sama. |

### 0.4 Catatan implementasi sementara — bukan persetujuan

Kode yang sudah dibuat dan diuji pada working copy mencakup: ambang `85%` dari pertemuan `Terlaksana`, akun dibuat Admin dengan registrasi publik dinonaktifkan, Dosen tidak aktif baca-saja, otorisasi penyelesaian kegiatan bersama, urutan keanggotaan terbaru, riwayat perubahan absensi/pertemuan, keputusan kelulusan Admin yang diaudit, dan gate kelayakan ujian berdasarkan kehadiran. Semua ini tetap berstatus **keputusan pengguna yang diimplementasikan sementara**, bukan `[KEBIJAKAN-SIK]` atau persetujuan institusi.

Migration baru bersifat append-only dan hanya dijalankan pada database isolasi/testing. Tidak ada koreksi otomatis terhadap data historis, tabel lama, atau file PDF lama. Detail yang masih BELUM DITENTUKAN pada §0.2 tetap belum ditentukan.

---

## 1. Ringkasan Keputusan

| No. Keputusan | No. Temuan | Topik | Grup | Prioritas | Jenis persetujuan | Status |
|---:|---:|---|---|---|---|---|
| 1 | 1 | Status kelulusan tanpa dasar kehadiran | Akademik & operasional | P1 / CRITICAL | Kode + Data | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 2 | 2 | Status/tanggal pertemuan masa depan | Akademik & operasional | P2 / HIGH | Kode + Data | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 3 | 3 | Perhitungan ulang rekap kehadiran | Akademik & operasional | P2 / HIGH | Kode + Data + Schema | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 4 | 4 | Akun login santri dan akses informasi berbasis NIM | Data & database | P2 / HIGH | Kode + Data | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 5 | 5 | Master data periode resmi SIK | Data & database | P2 / HIGH | Kode + Data | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 6 | 6 | Sertifikat dengan PDF hilang | Akademik & operasional | P2 / HIGH | Kode + Data + Konfigurasi | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 7 | 7 | Hak instruktur Tandai Selesai kegiatan bersama | Hak akses & keamanan | P2 / HIGH | Kode | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 8 | 8 | Daftar status instruktur aktif/tidak aktif | Hak akses & keamanan | P2 / HIGH | Kode + Data | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 9 | 9 | Kontrak resmi Import Santri | Fitur & UI/UX | P2 / HIGH | Kode khusus `SantriSpreadsheet.php` | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 10 | 10 | Registrasi akun publik vs pendaftaran peserta SIK | Hak akses & keamanan | P2 / HIGH | Kode | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 11 | 10 | Standar notifikasi pengaturan ulang kata sandi | Hak akses & keamanan | P2 / HIGH | Kode | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 12 | 14 | Kegiatan nama sama / duplikat | Akademik & operasional | P3 / MEDIUM | Kode + Data + Schema | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 13 | 17 | Penggunaan tabel legacy | Data & database | P3 / MEDIUM | Kode | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 14 | 18 | Aktivasi/arsip modul Jadwal & Mata Pelajaran | Fitur & UI/UX | P3 / MEDIUM | Kode | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 15 | 19 | Titik akhir pengubahan kata sandi standar | Hak akses & keamanan | P3 / MEDIUM | Kode | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 16 | 16 | Ketidaksesuaian riwayat absensi | Akademik & operasional | P3 / MEDIUM | Kode + Data | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 17 | 24 | Definisi keanggotaan periode terbaru | Akademik & operasional | P4 / LOW | Kode | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 18 | 26 | Standar frontend | Fitur & UI/UX | P4 / LOW | Kode + Konfigurasi | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 19 | 22 | Konfigurasi produksi | Infrastruktur & produksi | P3 / MEDIUM | Konfigurasi | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |
| 20 | 23 | Migration tertunda dan no-op | Data & database | P4 / LOW | Schema | DITETAPKAN OLEH PENGGUNA; FORMAL BELUM DIBERIKAN |

**Ringkasan status:** 20 keputusan **DITETAPKAN OLEH PENGGUNA** (lihat §0.2), 0 keputusan **DISETUJUI SECARA FORMAL OLEH INSTITUSI**. Sejumlah detail teknis masih **BELUM DITENTUKAN**.

---

# 2. Daftar Keputusan

> Status terkini setiap keputusan mengikuti §0.2. Pertanyaan, pilihan, dan rekomendasi di bawah merupakan bahan audit G0 dan tidak dihapus; beberapa di antaranya telah dijawab oleh pengguna tetapi belum disetujui secara institusional.

## KELOMPOK 1 — Keputusan Akademik dan Operasional

---

### KEPUTUSAN 1 — Status kelulusan santri tanpa dasar kehadiran memadai

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 1 — Kelulusan dan Sertifikat Tanpa Dasar (CRITICAL) |
| **Kondisi saat ini (bukti audit)** | `kelulusan_sik.id=2`: `status=Lulus`, `tanggal_kelulusan=2026-11-05`, anggota periode dibuat `2026-11-11`, `tanggal_masuk=2026-11-12`, `rekap_n=0`, `absensi_n=0`. Evaluasi service: "Belum memenuhi ambang kehadiran". `sertifikat_sik.id=2` (nomor `12154354454`) bersandar pada kelulusan ini. |
| **Pertanyaan untuk pihak SIK** | Apakah kelulusan tersebut sah karena pengecualian resmi, atau harus dinyatakan tidak memenuhi syarat? Jika tidak sah, apa tindakan atas sertifikat yang sudah terbit? |
| **Rekomendasi teknis (bukan keputusan)** | Tambah guard create/update kelulusan: harus ada evaluasi kehadiran yang memenuhi atau pengecualian tertulis. Guard sertifikat: kelulusan harus sah dan file harus ada. **Data lama tidak diubah otomatis.** Alasan: mencegah sertifikat baru tanpa dasar tanpa merusak arsip. |
| **File / modul / tabel terdampak** | `app/Http/Controllers/KelulusanSikController.php`, `app/Http/Controllers/SertifikatSikController.php`, `app/Models/KelulusanSik.php`, `app/Services/AttendanceRecapService.php`, tabel `kelulusan_sik`, `sertifikat_sik`, `rekap_kehadiran_sik`, `absensi_sik`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` + `[PERSETUJUAN-DATA]` + `[KEBIJAKAN-SIK]` |
| **Prioritas pengerjaan** | **P1 / CRITICAL** — namun tetap menunggu keputusan SIK paling awal (Gate G0). |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Kelulusan dinyatakan tidak sah → evaluasi ulang; sertifikat terkait ditandai bermasalah/diterbitkan ulang setelah dasar kehadiran dipenuhi.
- **B.** Kelulusan dinyatakan sah karena pengecualian resmi → pengecualian dicatat, service diberi mekanisme pengecualian, data lama dipertahankan apa adanya.
- **C.** Kasus ditunda → data lama dibekukan, hanya guard untuk data baru yang diterapkan.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Nilai `status` pada `kelulusan_sik` dan baris `sertifikat_sik` ikut berubah; halaman `sertifikat-saya` dan laporan Ka-Lab mengikuti status baru. | Santri terkait kehilangan akses unduh atau harus menerima sertifikat pengganti. |
| B | Perlu mekanisme penanda pengecualian yang dibaca oleh evaluasi kelulusan; jika menambah kolom, wajib `[PERSETUJUAN-SCHEMA]`. | Sertifikat tetap diakui, tetapi arsip bukti pengecualian harus disediakan. |
| C | Tidak ada perubahan data aktif atau skema; pengaman hanya berlaku untuk transaksi baru sehingga data lama tetap tidak konsisten. | Temuan CRITICAL tetap terbuka pada saat serah terima. |

---

### KEPUTUSAN 2 — Status dan tanggal pertemuan masa depan tetapi berstatus Terlaksana

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 2 — Pertemuan Masa Depan Berstatus `Terlaksana` (HIGH) |
| **Kondisi saat ini (bukti audit)** | `pertemuan.id=1`, tanggal `2026-11-30`, status `Terlaksana`; absensi terkait dibuat `2026-11-08`. `AttendanceAuthorizationService.php:155-159` dan `:216-229` mengatur otorisasi pertemuan, tetapi tidak ada validasi status terhadap tanggal. |
| **Pertanyaan untuk pihak SIK** | Mana yang benar: pertemuan benar-benar sudah terlaksana (tanggal salah input), tanggal sudah benar (status salah), atau ini input terlambat/backdate yang sah? Jika status diubah menjadi belum terlaksana, bagaimana penanganan absensi yang sudah tercatat sebelumnya? |
| **Rekomendasi teknis (bukan keputusan)** | Nilai awal: status `Terlaksana` hanya untuk `tanggal <= hari ini`. Sediakan **jalur pengecualian resmi** untuk input terlambat/backdate: wajib alasan, referensi/bukti bila tersedia, persetujuan pihak berwenang, dan catatan riwayat. Jangan membuat larangan keras yang menghalangi proses bisnis tanpa konfirmasi SIK. Jika status diubah menjadi `Terjadwal` atau `Dibatalkan`, **jangan menghapus absensi yang sudah tercatat**; tandai untuk peninjauan dan pastikan rekap tidak menghitungnya. Alasan: menjaga integritas status sekaligus mempertahankan jejak audit. |
| **File / modul / tabel terdampak** | `app/Http/Controllers/PertemuanController.php`, `app/Http/Controllers/AbsensiSikController.php`, `app/Services/AttendanceAuthorizationService.php`, `app/Services/AttendanceRecapService.php`, `app/Models/Pertemuan.php`, `app/Models/RiwayatAbsensi.php`, tabel `pertemuan`, `absensi_sik`, `riwayat_absensi`, `rekap_kehadiran_sik`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` + `[PERSETUJUAN-DATA]` (+ `[PERSETUJUAN-SCHEMA]` jika perlu kolom alasan/backdate) |
| **Prioritas pengerjaan** | **P2 / HIGH** — Gate G2, setelah otorisasi (Keputusan 7, 8). |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Status yang salah → koreksi menjadi `Terjadwal`; rekap dihitung ulang.
- **B.** Tanggal yang salah → koreksi tanggal ke tanggal pelaksanaan nyata; rekap dihitung ulang.
- **C.** Backdate sah → pertahankan status, catat pengecualian resmi; rekap tetap dihitung ulang sesuai sumber.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | `status` berubah menjadi `Terjadwal`; baris `absensi_sik` tetap tersimpan, tetapi `AttendanceRecapService` berhenti menghitungnya karena pertemuan bukan `Terlaksana`. | Angka kehadiran yang tampil ke santri/instruktur turun; perlu pemberitahuan. |
| B | `tanggal` berubah; urutan pertemuan dan hubungannya dengan `periode_sik` perlu ditinjau ulang. | Jadwal yang tampil ke instruktur berubah. |
| C | Perlu mekanisme pencatatan pengecualian; tanpa itu status future tetap dianggap tidak valid oleh pemeriksa. | Proses input terlambat tetap berjalan, tetapi dengan kontrol yang disepakati. |

**Penanganan absensi pada pertemuan yang kemudian dinyatakan belum terlaksana**

Ketentuan ini merupakan **usulan teknis**, bukan keputusan SIK:

| Aspek | Penjelasan |
|---|---|
| Fakta | `pertemuan.id=1` berstatus `Terlaksana` pada tanggal `2026-11-30`, tetapi absensi sudah dibuat `2026-11-08`. Jika status diubah menjadi `Terjadwal`/`Dibatalkan`, tetap ada baris `absensi_sik` yang terkait. |
| Pertanyaan untuk pihak SIK | Apakah absensi tersebut tetap diarsipkan, dinonaktifkan sementara, atau dipindahkan ke pertemuan pengganti? |
| Rekomendasi teknis | Pertahankan baris `absensi_sik`; jangan DELETE otomatis. Tandai sebagai perlu peninjauan, catat perubahan status di `riwayat_absensi`, dan pastikan perhitungan rekap hanya memakai pertemuan `Terlaksana`. |
| Risiko jika dihapus | Jejak audit hilang, riwayat santri tidak dapat menjelaskan perubahan, dan data sulit dipulihkan. |
| Persetujuan | `[KEBIJAKAN-SIK]` + `[PERSETUJUAN-KODE]` (+ `[PERSETUJUAN-DATA]` jika penandaan menyentuh baris lama). |
| Status | **KEPUTUSAN PENGGUNA; FORMAL BELUM DIBERIKAN** |

---

### KEPUTUSAN 3 — Kebijakan perhitungan ulang rekap kehadiran

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 3 — Rekap Kehadiran Tidak Sinkron dan Nilai Awal 16 (HIGH) |
| **Kondisi saat audit G0 (bukti audit; sebelum implementasi)** | `rekap_kehadiran_sik.id=1`: tersimpan 1 pertemuan/1 hadir, aktual 1/1, 100%, `Memenuhi`. `rekap_kehadiran_sik.id=2`: tersimpan `total_pertemuan=16`, aktual `Terlaksana=0`; tersimpan `jumlah_hadir=1`, aktual hadir pada pertemuan `Terlaksana` `=0`; persentase 6,25%, `Belum Memenuhi`. `database/migrations/2026_11_01_075544_create_rekap_kehadiran_sik_table.php:22` menetapkan default `16`. Saat audit, `config/sik.php` menyimpan `meeting_count=16`, `minimum_present=14`, dan `minimum_percentage=87,5`; **setelah implementasi, konfigurasi aktif memakai `attendance.minimum_percentage=85` tanpa `meeting_count`/`minimum_present`**. `docs/RENCANA_IMPLEMENTASI_SIKVERSE.md:1510` masih mencantumkan pertanyaan terbuka lama. `AttendanceRecapService.php` menghitung dari `pertemuan.Terlaksana` dan `absensi_sik.Hadir`. |
| **Pertanyaan untuk pihak SIK** | Apakah `rekap_kehadiran_sik` hanya data turunan yang boleh dihitung ulang dari `absensi_sik` dan pertemuan `Terlaksana`? Apakah hitung ulang boleh mengubah status `Memenuhi` menjadi `Belum Memenuhi`? **Manakah ketentuan minimal kehadiran yang berlaku — persentase tetap atau jumlah hadir minimum? Hal ini wajib dikonfirmasi pihak SIK; penyusun tidak menetapkan angka baru.** Apakah angka 16 tetap dipertahankan sebagai nilai awal/validasi formulir? |
| **Rekomendasi teknis (bukan keputusan)** | Jadikan `AttendanceRecapService` satu-satunya jalur penulisan rekap; jalankan pemeriksaan perbandingan sebelum hitung ulang; hitung ulang hanya setelah `[PERSETUJUAN-DATA]`. **Jangan mengubah atau menetapkan angka ambang maupun nilai awal sebelum pihak SIK mengonfirmasi**; perubahan nilai awal memerlukan `[PERSETUJUAN-SCHEMA]`. Pertemuan yang tidak berstatus `Terlaksana` tidak dihitung, tetapi absensi yang sudah tercatat tidak dihapus otomatis. Alasan: data turunan harus mengikuti sumber, dan angka ambang berdampak langsung pada kelulusan. |
| **File / modul / tabel terdampak** | `database/migrations/2026_11_01_075544_create_rekap_kehadiran_sik_table.php`, `config/sik.php`, `app/Services/AttendanceRecapService.php`, `app/Console/Commands/RecapAttendanceDryRun.php`, `app/Http/Controllers/RekapKehadiranSikController.php`, tabel `rekap_kehadiran_sik`, `pertemuan`, `absensi_sik`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` + `[PERSETUJUAN-DATA]` + `[PERSETUJUAN-SCHEMA]` |
| **Prioritas pengerjaan** | **P2 / HIGH** — Gate G3, setelah pertemuan (Keputusan 2) dan kegiatan duplikat (Keputusan 12). |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Rekap murni data turunan → hitung ulang penuh dari sumber; status lama boleh berubah.
- **B.** Rekap adalah catatan resmi yang tidak boleh ditimpa → hanya tambah pemeriksa/sinkronisasi untuk data baru; data lama dipertahankan.
- **C.** Hitung ulang terbatas hanya pada baris yang menyimpang, setelah peninjauan per baris.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Seluruh kolom `rekap_kehadiran_sik` mengikuti hasil `AttendanceRecapService`; nilai awal `total_pertemuan` perlu ditinjau setelah konfirmasi SIK. | Status kehadiran banyak santri berubah, sehingga evaluasi kelulusan ikut berubah. |
| B | Baris lama tetap tidak sesuai sumber; mekanisme pemeriksa hanya mencegah ketidaksesuaian pada data baru. | Temuan HIGH tetap terbuka pada data aktif. |
| C | Hanya baris yang menyimpang yang diperbarui; perlu daftar selisih dan peninjauan per baris. | Proses lebih lama, tetapi perubahan dapat dibatasi dan diaudit. |

**Contoh perhitungan — hanya ilustrasi, bukan kebijakan**

| Ilustrasi | Perhitungan | Penjelasan |
|---|---|---|
| 14 hadir dari 16 pertemuan | `14 ÷ 16 = 0,875 = 87,5%` | Nilai lama `minimum_present=14`, `meeting_count=16`, dan `minimum_percentage=87,5` saling konsisten secara aritmetik pada data saat ini. |
| Total pertemuan berubah menjadi 18, persentase tetap 87,5% | `0,875 × 18 = 15,75` → pembulatan harus diputuskan SIK | Jika metode persentase tetap dipilih, jumlah hadir minimum dapat berubah. |
| Jumlah hadir minimum tetap 14, total pertemuan 18 | `14 ÷ 18 ≈ 77,78%` | Jika metode jumlah hadir minimum tetap dipilih, persentase hasilnya berubah. |

Catatan: angka 14 dan 16 berasal dari konfigurasi lama yang sedang ditinjau. **14 pertemuan bukan ketentuan resmi**; keputusan akhir tetap berada pada pihak SIK.

---

### KEPUTUSAN 6 — Penanganan sertifikat yang file PDF-nya hilang

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 6 — File Sertifikat Hilang (HIGH) |
| **Kondisi saat ini (bukti audit)** | `sertifikat_sik.id=1` → `sertifikat-sik/WblSFIJmiIron02bThDluH5ObWhF0RbXXr4SLwF8.pdf`, `Storage::exists()=false`. `sertifikat_sik.id=2` → `sertifikat-sik/C9lWSOmm8L13qLrlxMvmykIxBHzrzgntaykmv7Q4.pdf`, `Storage::exists()=false`. `SertifikatSikController.php:169,180` sudah memeriksa berkas saat unduh/pratinjau, sehingga keduanya akan menghasilkan 404. `public/storage` tidak ada, tetapi pengontrol memakai disk `local` + rute bertanda tangan, sehingga akar masalah bukan symlink. |
| **Pertanyaan untuk pihak SIK** | Apakah file lama harus dipulihkan dari backup, diregenerasi dari template resmi, atau sertifikat ditandai tidak valid? Apakah nomor/tanggal lama boleh berubah? |
| **Rekomendasi teknis (bukan keputusan)** | Sebelum create/update sertifikat: validasi kelulusan sah + file berhasil disimpan + `Storage::exists()` → baru aktifkan row. Nomor dibuat setelah file tersedia. Data lama tidak diubah otomatis; tambahkan peringatan admin untuk sertifikat aktif tanpa file. Hak unduh tetap Admin + santri pemilik. Alasan: mencegah sertifikat baru tanpa file tanpa merusak arsip. |
| **File / modul / tabel terdampak** | `app/Http/Controllers/SertifikatSikController.php`, `app/Http/Controllers/KelulusanSikController.php`, `app/Models/SertifikatSik.php`, `config/filesystems.php`, `storage/app/sertifikat-sik`, tabel `sertifikat_sik`, `kelulusan_sik`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` + `[PERSETUJUAN-DATA]` + `[PERSETUJUAN-KONFIG]` (+ `[PERSETUJUAN-SCHEMA]` jika ditambah kolom status) |
| **Prioritas pengerjaan** | **P2 / HIGH** — Gate G5, setelah kelulusan (Keputusan 1). |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Restore file dari backup ke path yang sama; nomor/tanggal tidak diubah.
- **B.** Regenerasi PDF dari template resmi; nomor/tanggal ditetapkan ulang sesuai aturan SIK.
- **C.** Tandai sertifikat sebagai bermasalah/tidak aktif; jangan hapus baris.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | File dikembalikan ke path yang sama; tidak ada perubahan baris `sertifikat_sik`, sehingga `Storage::exists()` menjadi benar. | Bergantung pada ketersediaan berkas di backup. |
| B | Berkas PDF kemungkinan berubah beserta metadata nomor/tanggal pada `sertifikat_sik`. | Santri menerima dokumen pengganti; isi harus diverifikasi sebelum diserahkan. |
| C | Penandaan status baru diperlukan agar `sertifikat-saya` tidak menampilkan tautan 404; laporan kelulusan turut berubah. | Perlu komunikasi resmi kepada santri yang bersangkutan. |

---

### KEPUTUSAN 12 — Penanganan kegiatan dengan nama sama atau duplikat

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 14 — Kegiatan Duplikat (MEDIUM) |
| **Kondisi saat ini (bukti audit)** | `kegiatan.id=1` dan `id=2` sama-sama bernama `teknologi`, periode sama, `kelompok_id=NULL`. `AttendanceRecapService.php:730-788` memiliki penanganan khusus untuk mengompensasi duplikat kegiatan. |
| **Pertanyaan untuk pihak SIK** | Apakah kedua kegiatan benar-benar duplikat yang harus digabung, atau dua aktivitas berbeda yang kebetulan bernama sama? Bolehkah nama kegiatan dibuat unik per periode/kelompok? |
| **Rekomendasi teknis (bukan keputusan)** | Tambah validasi duplikat saat membuat/mengubah kegiatan, termasuk kegiatan bersama (`kelompok_id=NULL`). Penanganan khusus pada service dihapus hanya setelah data bersih. Alasan: duplikat saat ini ditutupi oleh kode khusus sehingga mengganggu hitung ulang rekap. |
| **File / modul / tabel terdampak** | `app/Http/Controllers/KegiatanController.php`, `app/Models/Kegiatan.php`, `app/Services/AttendanceRecapService.php`, tabel `kegiatan`, `pertemuan`, `absensi_sik`, `rekap_kehadiran_sik`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` + `[PERSETUJUAN-DATA]` + `[PERSETUJUAN-SCHEMA]` (jika unique constraint) |
| **Prioritas pengerjaan** | **P3 / MEDIUM** — harus diputuskan **sebelum** Keputusan 3 (hitung ulang rekap). |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Duplikat nyata → gabungkan ke satu kegiatan; alihkan pertemuan/absensi terkait.
- **B.** Disengaja → beri pembeda nama/kode dan tanda bahwa keduanya berbeda.
- **C.** Pertahankan → dokumentasikan pengecualian dan biarkan penanganan khusus tetap ada.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Kunci asing pada `pertemuan`, `absensi_sik`, dan `rekap_kehadiran_sik` harus dialihkan ke satu `kegiatan_id`. | Angka historis dapat berubah setelah hitung ulang. |
| B | Nama atau kode pembeda berubah pada tabel `kegiatan`; penanganan khusus dapat dihapus. | Daftar kegiatan lebih mudah dibedakan oleh instruktur. |
| C | Tidak ada perubahan data; `AttendanceRecapService` tetap memuat logika khusus. | Rekap tetap bergantung pada perlakuan khusus tersebut. |

---

### KEPUTUSAN 16 — Penanganan ketidaksesuaian riwayat absensi dengan status absensi saat ini

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 16 — Riwayat Absensi Tidak Sinkron (MEDIUM) |
| **Kondisi saat ini (bukti audit)** | `riwayat_absensi.id=1` mencatat akhir `Alpha`, sedangkan `absensi_sik.id=1` saat ini `Hadir`. Ketidaksesuaian juga dapat muncul jika status `pertemuan` diubah menjadi `Terjadwal`/`Dibatalkan` sementara `absensi_sik` tetap tersimpan (lihat Keputusan 2). |
| **Pertanyaan untuk pihak SIK** | Apakah ketidaksesuaian ini bug sinkronisasi, atau riwayat memang sengaja tidak lengkap? Apakah riwayat lama boleh direkonsiliasi? Apakah perubahan status pertemuan wajib dicatat sebagai entri riwayat baru? |
| **Rekomendasi teknis (bukan keputusan)** | Gunakan satu fungsi pembantu transaksi yang menulis `absensi_sik` dan `riwayat_absensi` bersamaan. Rekonsiliasi data lama hanya setelah persetujuan; **jangan menghapus riwayat**. Alasan: jejak audit harus tetap menjelaskan setiap perubahan status. |
| **File / modul / tabel terdampak** | `app/Http/Controllers/AbsensiSikController.php`, `app/Http/Controllers/BuktiKetidakhadiranController.php`, `app/Models/RiwayatAbsensi.php`, tabel `riwayat_absensi`, `absensi_sik`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` + `[PERSETUJUAN-DATA]` |
| **Prioritas pengerjaan** | **P3 / MEDIUM** |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Bug → rekonsiliasi urutan riwayat dengan status aktif.
- **B.** Sengaja → pertahankan riwayat, tambahkan catatan pengecualian.
- **C.** Tunda → hanya perbaiki penulisan untuk data baru.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Entri `riwayat_absensi` disesuaikan dengan status aktif `absensi_sik`; halaman riwayat santri menampilkan urutan baru. | Riwayat kehadiran menjadi konsisten dengan status terkini. |
| B | Tidak ada perubahan data; perlu catatan pengecualian yang dapat dibaca pengguna. | Perlu penjelasan tertulis agar laporan tidak disalahartikan. |
| C | Hanya penulisan data baru yang diperbaiki; data lama tetap tidak sinkron. | Risiko salah tafsir laporan historis tetap ada. |

---

### KEPUTUSAN 17 — Definisi data keanggotaan periode yang dianggap paling terbaru

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 24 — Relasi Model Duplikat/Kurang (LOW) |
| **Kondisi saat ini (bukti audit)** | `app/Models/KelulusanSik.php:30-45` memiliki dua relasi ke `AnggotaPeriodeSik` (`anggotaPeriode()` dan relasi alias `anggotaPeriodeSik()`). Relasi "terbaru" pada model terkait mengurutkan berdasarkan `id`, bukan `tanggal_masuk`. |
| **Pertanyaan untuk pihak SIK** | Apakah "keanggotaan terbaru" ditentukan oleh `tanggal_masuk`, oleh `id` (urutan entri terakhir), atau oleh periode dengan tanggal mulai terakhir? |
| **Rekomendasi teknis (bukan keputusan)** | Pertahankan relasi alias dengan penanda usang bila masih dipakai; tentukan satu definisi "terbaru" sesuai keputusan SIK. Alasan: kartu santri dan filter periode bergantung pada definisi ini. |
| **File / modul / tabel terdampak** | `app/Models/KelulusanSik.php`, `app/Models/InfrastrukturSik.php`, `app/Models/Santri.php`, tabel `anggota_periode_sik`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` |
| **Prioritas pengerjaan** | **P4 / LOW** |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Berdasarkan `tanggal_masuk` (waktu keanggotaan nyata).
- **B.** Berdasarkan `id` (urutan entri terakhir).
- **C.** Berdasarkan periode aktif/terakhir menurut tanggal periode.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Perlu fallback jika `tanggal_masuk` kosong. | Paling sesuai makna "terbaru". |
| B | Tidak berubah dari perilaku saat ini. | Bisa salah jika input backdate. |
| C | Bergantung pada Keputusan 5. | Perlu sinkron dengan master periode. |

---

## KELOMPOK 2 — Keputusan Hak Akses dan Keamanan

---

### KEPUTUSAN 7 — Hak instruktur dalam menandai kegiatan bersama sebagai selesai

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 7 — Tandai Selesai Kegiatan Bersama Terlalu Longgar (HIGH) |
| **Kondisi saat ini (bukti audit)** | `AttendanceAuthorizationService.php:155-159` mengembalikan kendali lebih awal untuk `isSharedActivity()` sebelum pemeriksaan pemilik/pengganti di `:179-182`. Sebaliknya, Ubah Tanggal di `:216-229` membatasi kegiatan bersama hanya kepada `instruktur_pengganti_id`. |
| **Pertanyaan untuk pihak SIK** | Siapa yang boleh menandai kegiatan bersama (misalnya Rabu Mengaji) sebagai selesai: pemilik kegiatan, instruktur pengganti, keduanya, atau semua instruktur aktif? |
| **Rekomendasi teknis (bukan keputusan)** | Pisahkan mode otorisasi `ubahTanggal` dan `tandaiSelesai`. Untuk `tandaiSelesai`, kegiatan bersama mengikuti aturan penanggung jawab/pengganti yang disepakati. Alasan: aturan Ubah Tanggal dan Tandai Selesai saat ini berbeda tanpa dasar kebijakan. |
| **File / modul / tabel terdampak** | `app/Services/AttendanceAuthorizationService.php`, `app/Http/Controllers/AbsensiSikController.php`, `app/Models/Pertemuan.php`, `app/Models/Kegiatan.php`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` |
| **Prioritas pengerjaan** | **P2 / HIGH** — Gate G1, **sebelum** koreksi data pertemuan. |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Hanya `instruktur_pengganti_id` (disamakan dengan Ubah Tanggal).
- **B.** Pemilik kegiatan/kelompok host **atau** instruktur pengganti.
- **C.** Semua instruktur aktif (mempertahankan kondisi saat ini).
- **D.** Admin dan instruktur pengganti saja.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Pemeriksaan `instruktur_pengganti_id` dipakai untuk Tandai Selesai; pemilik kegiatan dapat menerima `403`. | Aturan menjadi konsisten dengan Ubah Tanggal, tetapi alur pemilik berubah. |
| B | `AttendanceAuthorizationService` harus memeriksa `kelompok_host_id`/pemilik dan `instruktur_pengganti_id`. | Memberi jalur bagi pemilik maupun pengganti. |
| C | Tidak ada perubahan kode; cabang `isSharedActivity()` tetap langsung kembali. | Temuan HIGH tetap terbuka dan pihak tanpa hubungan penanggung jawab dapat menyelesaikan kegiatan. |
| D | Admin dilewatkan dari pemeriksaan instruktur; pengganti tetap diverifikasi. | Ketergantungan pada admin meningkat. |

---

### KEPUTUSAN 8 — Daftar status instruktur yang dianggap aktif dan tidak aktif

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 8 — Status Instruktur Tidak Konsisten (HIGH) |
| **Kondisi saat ini (bukti audit)** | `LoginRequest.php:75-80` hanya menolak `'Tidak Aktif'` untuk instruktur/Ka-Lab; `EnsureUserHasRole.php:32-43` melakukan hal sama; service absensi mensyaratkan tepat `'Aktif'`; Nilai dan Rekap tidak memeriksa status sama sekali. Pengujian memakai nilai `'Nonaktif'`. Kolom status bertipe teks tanpa tipe enum pada database. |
| **Pertanyaan untuk pihak SIK** | Apa daftar resmi status instruktur? Status mana yang dianggap aktif dan boleh login/menulis Nilai/Rekap/Absensi? Apakah status legacy seperti `'Nonaktif'` harus dinormalisasi? |
| **Rekomendasi teknis (bukan keputusan)** | Tentukan satu daftar status resmi dan satu fungsi pembantu `isActive()` yang dipakai login, middleware, Nilai, Rekap, dan service absensi. Status di luar daftar resmi ditolak. Normalisasi data hanya dengan `[PERSETUJUAN-DATA]`. Alasan: menghilangkan perbedaan tafsir antar lapisan. |
| **File / modul / tabel terdampak** | `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Middleware/EnsureUserHasRole.php`, `app/Services/AttendanceAuthorizationService.php`, `app/Http/Controllers/NilaiController.php`, `app/Http/Controllers/RekapKehadiranSikController.php`, `app/Models/InfrastrukturSik.php`, tabel `infrastruktur_siks`, `users`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` + `[PERSETUJUAN-DATA]` jika normalisasi |
| **Prioritas pengerjaan** | **P2 / HIGH** — Gate G1, sebelum koreksi data pertemuan. |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Aktif = tepat `'Aktif'`; lainnya ditolak (login + semua fitur).
- **B.** Aktif = `'Aktif'` dan status legacy tertentu yang disebutkan SIK; sisanya ditolak.
- **C.** Pertahankan perilaku saat ini (login tolak `'Tidak Aktif'` saja), dengan dokumentasi risiko.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Akun dengan status di luar `'Aktif'` ditolak oleh login, middleware, Nilai, Rekap, dan Absensi. | Perlu verifikasi nilai status pada data aktif sebelum penerapan. |
| B | Perlu daftar status yang diizinkan dan satu fungsi pembantu yang dipakai bersama. | Data lama tetap dapat masuk selama statusnya tercantum dalam daftar. |
| C | Tidak ada perubahan kode; tiap lapisan terus memakai perbandingan string sendiri. | Temuan HIGH tetap terbuka; akun dengan status tidak aktif dapat menulis nilai. |

---

### KEPUTUSAN 10 — Kebijakan registrasi akun publik dan pendaftaran peserta SIK

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 10 — Pengujian Autentikasi vs Implementasi (HIGH) |
| **Kondisi saat ini (bukti audit)** | `routes/auth.php:14-32` hanya memuat login, lupa kata sandi, dan pengaturan ulang kata sandi; tidak ada rute `register`. `tests/Feature/Auth/RegistrationTest.php` dan `RoleBasedAccessTest.php` mengharapkan `/register`. Pendaftaran peserta SIK merupakan alur lain: `santris`, `anggota_periode_sik`, `kelompok`, dan `kegiatan` dikelola melalui `SantriController` dan modul data induk, bukan melalui rute autentikasi. |
| **Pertanyaan untuk pihak SIK** | **Registrasi akun (pembuatan `users` untuk login):** apakah calon santri boleh mendaftar sendiri, atau akun selalu dibuat admin/import? Jika dibolehkan, peran apa yang dapat didaftarkan sendiri? **Pendaftaran peserta kegiatan SIK (pembuatan `santris` + `anggota_periode_sik`):** apakah juga dibuka untuk publik, atau tetap dilakukan admin/instruktur? Kedua hal ini harus dijawab terpisah. |
| **Rekomendasi teknis (bukan keputusan)** | Perlakukan kedua alur sebagai dua keputusan berbeda. Untuk registrasi akun: jika tidak diinginkan, selaraskan pengujian dengan kebijakan dan jangan menambah rute; jika diinginkan, daftarkan rute dengan validasi peran ketat dan tanpa penetapan peran sensitif secara massal. Untuk pendaftaran peserta SIK: jangan mengaitkan otomatis dengan akun publik sebelum kebijakan akun santri (Keputusan 4) dan master periode (Keputusan 5) ditetapkan. Alasan: pengujian tidak boleh menentukan kebijakan produk, dan akun login tidak sama dengan keanggotaan kegiatan. |
| **File / modul / tabel terdampak** | Registrasi akun: `routes/auth.php`, `app/Http/Controllers/Auth/RegisteredUserController.php`, `app/Models/User.php`, `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/Auth/RoleBasedAccessTest.php`. Pendaftaran peserta SIK: `app/Http/Controllers/SantriController.php`, `app/Http/Controllers/PeriodeSikController.php`, tabel `santris`, `users`, `anggota_periode_sik`, `kelompok`, `kegiatan`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` + `[KEBIJAKAN-SIK]`; jika pendaftaran peserta mengubah data aktif, juga `[PERSETUJUAN-DATA]` |
| **Prioritas pengerjaan** | **P2 / HIGH** |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

Registrasi akun:

- **A.** Registrasi akun publik dimatikan → akun dibuat admin/import.
- **B.** Registrasi akun publik diaktifkan hanya untuk peran santri, dengan verifikasi email.
- **C.** Registrasi akun publik diaktifkan untuk santri dan instruktur dengan persetujuan admin.

Pendaftaran peserta kegiatan SIK:

- **D.** Tetap dikelola admin/instruktur; tidak ada formulir publik.
- **E.** Dibuka untuk publik, tetapi hanya menghasilkan calon peserta yang diverifikasi admin sebelum menjadi `anggota_periode_sik`.
- **F.** Dibuka penuh untuk publik dan langsung menjadi anggota periode (perlu pertimbangan kebijakan akun dan validasi data).

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Tidak ada rute baru; pengujian autentikasi lama diselaraskan dengan kebijakan. | Admin/import tetap menjadi satu-satunya pembuat akun. |
| B | Rute `register` dan validasi peran santri ditambahkan; `users` mendapat entri baru. | Santri dapat membuat akun sendiri, tetapi belum otomatis menjadi peserta kegiatan. |
| C | Perlu alur persetujuan admin sebelum peran instruktur aktif. | Beban admin berkurang, kontrol tetap ada. |
| D | Tidak ada perubahan pada `santris`/`anggota_periode_sik`. | Pendaftaran peserta tetap melalui admin/instruktur. |
| E | Perlu status “calon peserta” dan alur verifikasi sebelum entri `anggota_periode_sik` dibuat. | Admin tetap memegang kontrol atas keanggotaan. |
| F | Entri `santris` dan `anggota_periode_sik` dapat dibuat langsung oleh publik. | Risiko data ganda dan akses akun tanpa verifikasi meningkat. |

---

### KEPUTUSAN 11 — Standar notifikasi pengaturan ulang kata sandi

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 10 — Pengujian Autentikasi vs Implementasi (HIGH) |
| **Kondisi saat ini (bukti audit)** | Aplikasi memakai `CustomResetPassword`; `tests/Feature/Auth/PasswordResetTest.php` mengharapkan `Illuminate\Auth\Notifications\ResetPassword` (3 pengujian gagal). `routes/auth.php:21-31` memuat alur lupa/pengaturan ulang kata sandi. |
| **Pertanyaan untuk pihak SIK** | Apakah notifikasi pengaturan ulang kata sandi tetap memakai kelas kustom (`CustomResetPassword`), atau harus kembali ke notifikasi standar Laravel? Bahasa dan identitas pengirim apa yang digunakan? |
| **Rekomendasi teknis (bukan keputusan)** | Tentukan satu kelas notifikasi resmi, lalu selaraskan pengujian. Alasan: pengujian saat ini menguji asumsi lama, bukan perilaku produk. |
| **File / modul / tabel terdampak** | `app/Notifications/CustomResetPassword.php`, `app/Models/User.php`, `config/mail.php`, `tests/Feature/Auth/PasswordResetTest.php`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` |
| **Prioritas pengerjaan** | **P2 / HIGH** |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Pertahankan `CustomResetPassword` → pengujian disesuaikan.
- **B.** Kembali ke notifikasi standar Laravel → notifikasi kustom dihapus/dinonaktifkan.
- **C.** Keduanya dipertahankan untuk alur berbeda (perlu penjelasan alur).

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Pengujian disesuaikan dengan `CustomResetPassword`; isi dan identitas pesan tidak berubah. | Alur notifikasi tetap seperti kondisi saat ini. |
| B | Kelas notifikasi dan template pesan diganti; pengujian mengikuti kelas standar. | Perlu verifikasi pengiriman pada konfigurasi produksi. |
| C | Dua jalur notifikasi harus dipelihara bersamaan. | Risiko pesan berbeda antar alur lebih tinggi. |

---

### KEPUTUSAN 15 — Penentuan titik akhir pengubahan kata sandi yang menjadi standar

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 19 — Titik Akhir Kata Sandi Duplikat (MEDIUM) |
| **Kondisi saat ini (bukti audit)** | `routes/web.php:46-54` memuat `GET /change-password`, `POST /change-password`, dan `POST /password` ke `ChangePasswordController`; `routes/auth.php:55` memuat `PUT /password` ke `Auth\PasswordController`. `ChangePasswordController.php:20-26` memakai `Password::min(8)`, sedangkan jalur Breeze memakai aturan lain. Pengalihan pada `ChangePasswordController.php:35-37` ke `dashboard`, lalu `routes/web.php:70-82` mengalihkan lagi ke dasbor per peran, sehingga notifikasi keberhasilan berisiko hilang. |
| **Pertanyaan untuk pihak SIK** | Titik akhir mana yang menjadi standar? Apakah titik akhir lama tetap dialihkan atau langsung dinonaktifkan? Berapa aturan panjang minimal kata sandi yang berlaku? |
| **Rekomendasi teknis (bukan keputusan)** | Tetapkan satu titik akhir utama; titik akhir lama dialihkan sementara; samakan aturan kata sandi dan notifikasi; arahkan langsung ke dasbor sesuai peran. Alasan: menghindari perilaku berbeda dan pengguna terkunci oleh middleware `password.changed`. |
| **File / modul / tabel terdampak** | `routes/web.php`, `routes/auth.php`, `app/Http/Controllers/ChangePasswordController.php`, `app/Http/Controllers/Auth/PasswordController.php`, `app/Http/Middleware/EnsurePasswordIsChanged.php`, `resources/views/auth/change-password.blade.php`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` |
| **Prioritas pengerjaan** | **P3 / MEDIUM** |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Standar = `POST /change-password` (alur aplikasi SIKVERSE).
- **B.** Standar = `PUT /password` (alur Breeze).
- **C.** Keduanya dipertahankan dengan perilaku identik (pengalihan + validasi sama).

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | `PUT /password` pada `routes/auth.php` dinonaktifkan atau dialihkan ke `POST /change-password`. | Formulir lama perlu disesuaikan agar pengguna tidak melihat halaman gagal. |
| B | Formulir pada halaman `auth.change-password` dialihkan ke `PUT /password`. | Daftar pengecualian middleware `password.changed` harus diverifikasi ulang. |
| C | Kedua jalur tetap aktif dengan validasi dan respons yang disamakan. | Risiko inkonsistensi pesan tetap ada jika salah satu jalur berubah. |

---

## KELOMPOK 3 — Keputusan Data dan Database

---

### KEPUTUSAN 4 — Kebijakan akun login santri dan akses informasi berbasis NIM

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 4 — Santri Tanpa Akun User (HIGH) |
| **Kondisi saat ini (bukti audit)** | `santris.id=3` (MUHARIVAL, NIM `2417020017`) dan `santris.id=7` (Wahyudi) memiliki `user_id=NULL`. `santris.id=3` memiliki anggota periode, kelulusan, dan sertifikat. Rute `nilai-saya`, `absensi-saya`, dan `sertifikat-saya` pada `routes/web.php:266-282` berada di dalam grup berautentikasi dan mengambil data dari `santris.user_id`; NIM saat ini bukan mekanisme autentikasi. |
| **Pertanyaan untuk pihak SIK** | Apakah setiap santri aktif wajib memiliki akun login? Jika iya, bagaimana penanganan dua santri tersebut — dibuatkan akun baru, dihubungkan ke akun yang sudah ada, atau ditandakan sebagai santri tanpa akun? Terpisah dari itu: **apakah santri tanpa akun boleh melihat nilai/absensi/sertifikat hanya dengan memasukkan NIM**, atau akses informasi selalu harus melalui akun dan verifikasi identitas? |
| **Rekomendasi teknis (bukan keputusan)** | Tentukan dulu apakah NIM dianggap sebagai identitas pengganti atau hanya kode identifikasi. Jika tanpa akun harus tetap mendapat akses, mekanisme tersebut merupakan fitur baru yang memerlukan rute publik, pembatasan data, dan verifikasi identitas tambahan; **jangan menganggap NIM saja sebagai bukti identitas**. Jangan membuat kata sandi/default kredensial tanpa prosedur resmi. Alasan: kredensial dan akses data pribadi harus dapat diaudit. |
| **File / modul / tabel terdampak** | `app/Http/Controllers/SantriController.php`, `app/Http/Controllers/Auth/RegisteredUserController.php`, `app/Http/Controllers/UserManagementController.php`, `app/Http/Controllers/NilaiController.php`, `app/Http/Controllers/AbsensiSikController.php`, `app/Http/Controllers/SertifikatSikController.php`, `routes/web.php`, `app/Models/Santri.php`, tabel `santris`, `users`, `anggota_periode_sik`, `nilai`, `absensi_sik`, `sertifikat_sik`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` + `[PERSETUJUAN-DATA]` + `[KEBIJAKAN-SIK]` |
| **Prioritas pengerjaan** | **P2 / HIGH** |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Wajib akun → buatkan `users` untuk santri id=3 dan id=7 dengan kredensial resmi.
- **B.** Wajib akun → hubungkan ke akun yang sudah ada setelah verifikasi identitas.
- **C.** Tidak wajib akun → santri tetap tercatat tanpa login; informasi dikeluarkan oleh admin.
- **D.** Tidak wajib akun → sediakan akses terbaca hanya dengan NIM **plus verifikasi identitas tambahan** yang ditetapkan SIK.
- **E.** Kombinasi → akun untuk santri aktif, dengan jalur terbatas berbasis NIM hanya bagi data lama yang belum memiliki akun.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Baris `users` baru dan `santris.user_id` diisi; rute `nilai-saya`/`sertifikat-saya` mulai dapat diakses. | Santri dapat login, tetapi kredensial harus diserahkan melalui prosedur resmi. |
| B | Penautan `santris.user_id` ke akun yang sudah ada; perlu pemeriksaan duplikat identitas. | Risiko salah menghubungkan identitas jika verifikasi longgar. |
| C | Tidak ada penambahan akun; seluruh rute “-saya” tetap tidak terjangkau bagi santri tersebut. | Santri bergantung pada admin untuk melihat nilai dan sertifikat. |
| D | Perlu rute publik, validasi NIM, pembatasan kolom yang ditampilkan, dan verifikasi identitas; tanpa itu berisiko membocorkan data pribadi. | Santri tanpa akun tetap bisa melihat informasi terbatas sesuai kebijakan. |
| E | Perlu aturan masa transisi dan penanda santri yang memakai jalur NIM. | Akses lama tetap berfungsi sambil akun dibuat bertahap. |

---

### KEPUTUSAN 5 — Data periode SIK yang menjadi acuan resmi

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 5 — Periode Tidak Logis (HIGH) |
| **Kondisi saat ini (bukti audit)** | `periode_sik.id=2` (`ganjil_2026`): 2026-11-01 s.d. 2026-11-01, status `Aktif`. `id=3`: nama "SIK 2027" tetapi tanggal 2026-11-11 s.d. 2026-11-23, status `Persiapan`. `id=4`: 2026-11-24 s.d. 2026-11-30, status `Selesai` (masa depan). Pertemuan dan anggota berada di luar rentang periode aktif. |
| **Pertanyaan untuk pihak SIK** | Apa master periode resmi (nama, tanggal mulai/selesai, status) yang harus dipakai? Bolehkah lebih dari satu periode berstatus `Aktif`? |
| **Rekomendasi teknis (bukan keputusan)** | Validasi: tanggal selesai > mulai, status `Selesai` tidak untuk masa depan, nama sesuai tahun, aturan jumlah periode aktif. Koreksi data hanya setelah master disetujui. Alasan: filter kelompok/anggota dan laporan tahunan bergantung pada ini. |
| **File / modul / tabel terdampak** | `app/Http/Controllers/PeriodeSikController.php`, `app/Models/PeriodeSik.php`, `resources/views/periode_sik/create.blade.php`, `resources/views/periode_sik/edit.blade.php`, tabel `periode_sik`, `kelompok`, `anggota_periode_sik`, `kegiatan`, `pertemuan`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` + `[PERSETUJUAN-DATA]` |
| **Prioritas pengerjaan** | **P2 / HIGH** — harus diputuskan sebelum Keputusan 2 dan 3. |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Periode id=2 diperbaiki rentangnya dan tetap `Aktif`; id=3/4 disesuaikan.
- **B.** Periode id=3 dijadikan aktif; id=2 dinonaktifkan; id=4 disesuaikan.
- **C.** Seluruh master periode disusun ulang sesuai data resmi SIK yang akan diberikan.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Filter periode aktif berubah. | Kelompok/anggota tetap di periode lama. |
| B | Banyak relasi perlu ditinjau. | Data bisa tampil di periode berbeda. |
| C | Paling bersih tetapi paling luas. | Perlu migrasi data terencana. |

---

### KEPUTUSAN 13 — Kebijakan penggunaan tabel legacy

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 17 — Dual Schema Legacy (MEDIUM) |
| **Kondisi saat ini (bukti audit)** | Tabel `absensi`, `rekap_kehadiran`, `kelulusan`, `sertifikat`, `laporan`, `fingerprints`, `log_penggunaan_fingerprint`, `admins` masih ada; sebagian besar kosong. `app/Models/Santri.php` masih memiliki relasi ke tabel legacy tersebut. |
| **Pertanyaan untuk pihak SIK** | Apakah tabel legacy diarsipkan (read-only), tetap kompatibel, atau dihapus di masa depan? Apakah kode aktif boleh terus memakai relasi legacy? |
| **Rekomendasi teknis (bukan keputusan)** | Tandai relasi legacy sebagai deprecated, alihkan pemakaian aktif ke model SIK. **Tidak menghapus tabel/file migration.** Alasan: penghapusan dapat merusak view lama dan arsip. |
| **File / modul / tabel terdampak** | `app/Models/Santri.php`, model legacy terkait, dokumentasi arsitektur, tabel legacy yang disebutkan. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]`; **dilarang** menghapus tabel tanpa perintah eksplisit terpisah. |
| **Prioritas pengerjaan** | **P3 / MEDIUM** |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Arsipkan: tabel tetap ada, tidak dipakai kode aktif.
- **B.** Kompatibel: relasi legacy dipertahankan untuk view lama.
- **C.** Hapus di masa depan: **hanya rencana**, tidak dieksekusi tanpa persetujuan eksplisit.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Perlu penandaan deprecated. | Arsip tetap aman. |
| B | Tidak ada perubahan perilaku. | Risiko salah baca tetap ada. |
| C | Perlu perencanaan terpisah. | Tidak boleh dilakukan dalam Tahap 2. |

---

### KEPUTUSAN 20 — Kebijakan penanganan migration tertunda dan migration no-op

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 23 — Migration Drift (LOW) |
| **Kondisi saat ini (bukti audit)** | `database/migrations/2026_11_10_175108_restore_user_id_on_santris_table.php` masih pending meski memiliki guard `hasColumn`. Terdapat `2026_10_25_162829_add_role_to_users_table.php` dan `2026_10_28_140230_add_role_column_to_users_table.php` yang salah satu berpotensi no-op/duplikat. |
| **Pertanyaan untuk pihak SIK** | Apakah status migration boleh dirapikan dengan migration baru atau penyesuaian metadata? Apakah migration lama boleh diubah? Apakah pengujian hanya di DB isolasi? |
| **Rekomendasi teknis (bukan keputusan)** | Rapikan di lingkungan terisolasi; **jangan jalankan ke DB aktif** tanpa persetujuan eksplisit. Alasan: perubahan state migration bisa menyesatkan deployment. |
| **File / modul / tabel terdampak** | Dua/empat file migration di atas, tabel `migrations`, `santris`, `users`. |
| **Jenis persetujuan** | `[PERSETUJUAN-SCHEMA]` |
| **Prioritas pengerjaan** | **P4 / LOW** |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Tambah migration penanda/no-op baru untuk menyelesaikan status pending.
- **B.** Ubah file migration lama (dengan risiko pelacakan berubah).
- **C.** Biarkan apa adanya dan dokumentasikan.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | State migration bersih di DB baru. | Perlu koordinasi deploy. |
| B | Riwayat migrasi berubah. | Berisiko bagi DB yang sudah terpasang. |
| C | Drift tetap ada. | Risiko kecil, terdokumentasi. |

---

## KELOMPOK 4 — Keputusan Fitur dan UI/UX

---

### KEPUTUSAN 9 — Kontrak resmi Import Santri

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 9 — Kontrak Import Santri (HIGH) |
| **Kondisi saat ini (bukti audit)** | `app/Services/SantriSpreadsheet.php:82-85` mewajibkan `nomor_induk`, `nama`, `jenis_kelamin`, `kelompok`; `:95-115` membangun nilai tanpa key `sheet`; parser hanya membaca worksheet pertama (`:256-298` menurut audit). Template menyertakan `Jenis Kelamin`. `tests/Feature/SantriImportTest.php` mengharapkan gender opsional, sheet bernama `Data Santri`, dan key `sheet`. |
| **Pertanyaan untuk pihak SIK** | Apakah `jenis_kelamin` wajib atau opsional? Apakah parser membaca sheet pertama atau sheet bernama `Data Santri`? Apakah template final menyertakan gender? Apakah hasil parser wajib mengandung key `sheet`? |
| **Rekomendasi teknis (bukan keputusan)** | Samakan parser, template, pengontrol, dan pengujian sesuai kontrak akhir. **Jangan mengubah `SantriSpreadsheet.php` tanpa `[PERSETUJUAN-SANTRI-SPREADSHEET]` eksplisit**, dan jangan mengubah pengujian hanya agar seluruh pengujian lulus. Alasan: kontrak harus berasal dari kebijakan, bukan dari pengujian. |
| **File / modul / tabel terdampak** | `app/Services/SantriSpreadsheet.php` **(dilarang disentuh sebelum persetujuan)**, `app/Http/Controllers/SantriController.php`, `tests/Feature/SantriImportTest.php`, tampilan pratinjau/impor. |
| **Jenis persetujuan** | `[PERSETUJUAN-SANTRI-SPREADSHEET]` + `[KEBIJAKAN-SIK]` |
| **Prioritas pengerjaan** | **P2 / HIGH** |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Ikuti implementasi saat ini: gender wajib, sheet pertama, template dengan gender, tanpa key `sheet` → pengujian diselaraskan.
- **B.** Ikuti ekspektasi pengujian: gender opsional, sheet `Data Santri`, template 7 kolom, key `sheet` wajib → parser dan template diubah.
- **C.** Kontrak hibrida yang ditetapkan SIK secara tertulis.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Pengujian berubah; impor tetap seperti sekarang. | Berkas lama tetap diterima. |
| B | Parser dan template berubah (butuh persetujuan khusus). | Berkas baru sesuai spesifikasi pengujian. |
| C | Bergantung pada detail keputusan. | Perlu dokumentasi template final. |

---

### KEPUTUSAN 14 — Keputusan aktivasi atau pengarsipan modul Jadwal dan Mata Pelajaran

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 18 — Rute Jadwal/Mata Pelajaran Tidak Terdaftar (MEDIUM) |
| **Kondisi saat ini (bukti audit)** | Tampilan `resources/views/jadwal/*` dan `resources/views/mata-pelajaran/*` memanggil `route('jadwal.*')` / `route('mata-pelajaran.*')`, tetapi `route:list` tidak memuat rute tersebut. Tabel `mata_pelajaran` tetap dipakai fitur Nilai. |
| **Pertanyaan untuk pihak SIK** | Apakah modul Jadwal dan Mata Pelajaran harus diaktifkan kembali, atau diarsipkan? Jika diaktifkan, peran apa yang boleh mengelolanya? |
| **Rekomendasi teknis (bukan keputusan)** | Jika diaktifkan: daftarkan rute dengan middleware `role:admin` dan uji operasi tambah/baca/ubah/hapus. Jika diarsipkan: hapus/sembunyikan tampilan, **jangan hapus tabel `mata_pelajaran`**. Alasan: mencegah `RouteNotFoundException` sekaligus menjaga data Nilai. |
| **File / modul / tabel terdampak** | `routes/web.php`, `resources/views/jadwal/*`, `resources/views/mata-pelajaran/*`, `app/Http/Controllers/JadwalController.php`, `app/Http/Controllers/MataPelajaranController.php`, tabel `mata_pelajaran`. |
| **Jenis persetujuan** | `[PERSETUJUAN-KODE]` |
| **Prioritas pengerjaan** | **P3 / MEDIUM** |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Aktifkan dua modul untuk admin.
- **B.** Arsipkan view; data `mata_pelajaran` tetap dipakai Nilai.
- **C.** Aktifkan hanya Mata Pelajaran; Jadwal diarsipkan.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Rute + pengujian baru. | Admin bisa mengelola data induk. |
| B | Tidak ada rute baru. | Fitur tidak tersedia. |
| C | Sebagian modul aktif. | Perlu penjelasan ke pengguna. |

---

### KEPUTUSAN 18 — Pemilihan standar frontend

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 26 — Stack Tailwind/Bootstrap/CSS (LOW) |
| **Kondisi saat ini (bukti audit)** | `package.json:10-18` memuat `@tailwindcss/vite ^4.0.0` dan `tailwindcss ^3.1.0` sekaligus; `vite.config.js:4-10` hanya mendaftarkan plugin Laravel (tidak ada plugin Tailwind); CSS masih direktif v3; Bootstrap CDN dan 8 file CSS kustom hidup berdampingan. |
| **Pertanyaan untuk pihak SIK** | Apakah standar akhir Bootstrap + CSS kustom, atau Tailwind v4? Apakah refactor massal diizinkan menjelang serah terima? |
| **Rekomendasi teknis (bukan keputusan)** | Pilih satu sistem. Jika Tailwind v4 → daftarkan plugin dan migrasi direktif. Jika Bootstrap + kustom → hapus dependency Tailwind yang tidak dipakai. **Jangan refactor massal tanpa persetujuan** karena risiko regresi visual tinggi. Alasan: stack ganda menyebabkan benturan gaya dan build tidak konsisten. |
| **File / modul / tabel terdampak** | `package.json`, `vite.config.js`, `resources/css/app.css`, `resources/views/layouts/app.blade.php`, `public/Css/*.css`. |
| **Jenis persetujuan** | `[KEBIJAKAN-SIK]` + `[PERSETUJUAN-KONFIG]` (build/deploy) |
| **Prioritas pengerjaan** | **P4 / LOW** — lakukan setelah logika stabil. |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Bootstrap + CSS kustom sebagai standar; Tailwind dihapus.
- **B.** Tailwind v4 sebagai standar; plugin dan direktif dimigrasi.
- **C.** Pertahankan stack ganda, dokumentasikan aturan pemakaian.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Build lebih sederhana. | Tampilan halaman Tailwind perlu ditinjau. |
| B | Perlu `npm run build` + QA menyeluruh. | Risiko regresi visual tinggi. |
| C | Tidak ada perubahan. | Risiko benturan gaya tetap ada. |

---

## KELOMPOK 5 — Keputusan Infrastruktur dan Produksi

---

### KEPUTUSAN 19 — Informasi konfigurasi produksi: domain, HTTPS, email, queue, dan storage

| Field | Isi |
|---|---|
| **No. temuan audit** | No. 22 — Konfigurasi Produksi Belum Siap (MEDIUM) |
| **Kondisi saat ini (bukti audit)** | `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8000`, `APP_NAME=Laravel`, `session.secure=null`, sistem berkas bawaan `local`, email `smtp`. Rute `POST /_boost/browser-logs` tanpa autentikasi perlu dipastikan tidak aktif di produksi. `config/sik.php:6-9` mengatur verifikasi email otomatis aktif kecuali `APP_ENV=local`. |
| **Pertanyaan untuk pihak SIK** | Apa domain final dan skema HTTPS? Apakah cookie sesi wajib aman? Server email/SMTP apa yang dipakai? Driver antrean apa yang tersedia dan apakah proses worker diizinkan berjalan di hosting? Jika lingkungan internal dipilih, apakah antrean sinkron diterima atau tetap memakai worker? Di mana lokasi penyimpanan sertifikat produksi? Apakah verifikasi email wajib di produksi? |
| **Rekomendasi teknis (bukan keputusan)** | Siapkan nilai final tanpa mengubah `.env` sekarang: `APP_ENV=production`, `APP_DEBUG=false`, `APP_NAME=SIKVERSE`, URL HTTPS, `SESSION_SECURE_COOKIE=true`, antrean dan email sesuai hosting, storage permanen. **Driver `database` hanya menyimpan pekerjaan di tabel; proses worker tetap diperlukan untuk memproses antrean.** Jika driver `sync` dipilih, pekerjaan berjalan langsung dalam proses yang sama dan dapat memperlambat respons bila pengiriman email lambat. Alasan: `debug=true` membocorkan detail galat; HTTPS diperlukan sebelum cookie aman aktif. |
| **File / modul / tabel terdampak** | `.env` **(tidak diubah sekarang)**, `.env.example`, `config/app.php`, `config/session.php`, `config/mail.php`, `config/queue.php`, `config/filesystems.php`, `config/sik.php`, dokumentasi deployment. |
| **Jenis persetujuan** | `[PERSETUJUAN-KONFIG]` |
| **Prioritas pengerjaan** | **P3 / MEDIUM** — setelah logika dan data stabil (Gate G6). |
| **Status keputusan pengguna** | **DITETAPKAN OLEH PENGGUNA** (lihat §0.2) |
| **Status persetujuan institusi** | **BELUM DIBERIKAN** |

**Pilihan keputusan yang memungkinkan**

- **A.** Domain HTTPS + verifikasi email aktif + antrean `database` + worker.
- **B.** Domain HTTPS + verifikasi email aktif + antrean Redis + worker khusus.
- **C.** Domain HTTP internal + verifikasi email dinonaktifkan + antrean `sync` (pekerjaan dijalankan dalam proses yang sama). Jika pihak SIK tetap memilih `database`/Redis pada lingkungan internal, **worker wajib dijalankan**. Opsi ini hanya untuk lingkungan tertutup dan perlu justifikasi tertulis.

**Dampak teknis dan operasional**

| Pilihan | Dampak teknis | Dampak operasional |
|---|---|---|
| A | Pekerjaan disimpan di tabel `jobs`, tetapi tetap memerlukan proses worker yang berjalan untuk memprosesnya. | Email verifikasi diproses secara asinkron; worker harus dipantau agar antrean tidak menumpuk. |
| B | Perlu penyediaan Redis dan proses worker. | Notifikasi lebih andal untuk volume besar, tetapi ada komponen tambahan yang dirawat. |
| C | Dengan `sync`, tidak ada worker dan tidak ada antrean tersimpan; pekerjaan mengikuti permintaan yang sedang berjalan, sehingga respons dapat melambat atau ikut gagal bila pengiriman email gagal. Jika `database`/Redis dipilih, worker tetap diperlukan. | Tidak ada komponen worker yang dirawat, tetapi cocok hanya untuk volume kecil di lingkungan tertutup; risiko keamanan lebih tinggi dan memerlukan persetujuan tertulis. |

---

# 3. Rekomendasi Teknis dan Spesifikasi Implementasi Lintas Keputusan

Rekomendasi berikut bersifat teknis dan **tidak menetapkan kebijakan**:

1. **Sumber kebenaran tunggal**
   - Absensi: `absensi_sik`.
   - Rekap: `AttendanceRecapService` + pertemuan `Terlaksana`; hitung ulang tidak menghapus absensi yang sudah tercatat.
   - Ambang: sebelum implementasi `config/sik.php` memuat `meeting_count=16`, `minimum_present=14`, dan `minimum_percentage=87,5`; konfigurasi aktif sekarang adalah `attendance.minimum_percentage=85`. `docs/RENCANA_IMPLEMENTASI_SIKVERSE.md` masih memuat pertanyaan terbuka lama. **Ketentuan minimal kehadiran menurut pengguna: 85% dari pertemuan terlaksana**; nilai lama 16/14/87,5 bukan acuan kebijakan, dan detail pembulatan belum ditentukan.
   - Sertifikat: kelulusan sah + file fisik ada.

2. **Urutan penerapan pengaman kode sebelum koreksi data** — mencegah data yang sudah dikoreksi kembali rusak.

3. **Pemeriksaan perbandingan (dry-run) sebelum perubahan data** — semua koreksi rekap/kelulusan/sertifikat didahului perbandingan read-only dan laporan selisih.

4. **Data lama dibekukan kecuali disetujui** — tidak ada UPDATE/DELETE pada `kelulusan_sik`, `sertifikat_sik`, `absensi_sik`, `nilai`, atau `periode_sik` tanpa `[PERSETUJUAN-DATA]`.

5. **Tidak ada penghapusan tabel legacy** — Keputusan 13 hanya mengatur pemakaian, bukan penghapusan.

6. **File sensitif** — `app/Services/SantriSpreadsheet.php` tetap pada hash `5E9FB1807308BD79692BEF4AC0344E462BF070DC2D68EC24D9C32494C24AEFA8` sampai Keputusan 9 disetujui.

### 3.1 Spesifikasi navigasi dan daftar menu per peran (BELUM DIPUTUSKAN)

Daftar berikut adalah **inventarisasi berdasarkan kode**. Implementasi teknis mengikuti keputusan pengguna di §0.2 (khususnya no. 7, 8, 10, 14, dan 17), tetapi daftar menu final **belum disetujui secara institusional**.

| Peran | Bukti kondisi saat ini | Daftar menu/rute yang tersedia | Status |
|---|---|---|---|
| Admin | `resources/views/layouts/navigation.blade.php:12-24` memakai tata letak sidebar; `routes/web.php:121-186,192-242` memuat rute admin. | Menu utama: Dashboard, Data Santri, Kelola Status Kelulusan, Data Dosen, Rekap Absensi Dosen, Rekap Absensi, Rabu Mengaji. Rute tambahan yang belum menjadi menu sidebar: `periode-sik`, `kelompok`, `anggota-periode-sik`, `kegiatan`, `pertemuan`, `sertifikat-sik`, impor santri, dan profil. | **BELUM DIPUTUSKAN** — mengikuti Keputusan 7, 8, 10, dan 14. |
| Instruktur SIK | `resources/views/layouts/navigation.blade.php:25-37` memakai sidebar; `routes/web.php:192-257` memuat rute instruktur. | Dashboard, Mahasiswa (`instruktur.anggota.index`), Absen (`absensi-sik.index`), Nilai (`nilai.index`), Rekap Absen (`rekap-kehadiran-sik.index`), dan Kelompok Saya (`instruktur.kelompok.index`) pada menu “Menu lainnya”. Aksi Tandai Selesai/Ubah Tanggal bukan menu dan masih menunggu Keputusan 7. | **BELUM DIPUTUSKAN** — mengikuti Keputusan 7 dan 8. |
| Santri | `resources/views/layouts/navigation.blade.php:105-117` hanya menampilkan bilah atas; `routes/web.php:264-283` memuat rute khusus santri. | Dashboard Santri, Nilai Saya (`nilai-saya`), Absensi Saya (`absensi-saya`), Sertifikat Saya (`sertifikat-saya`), dan profil. Belum ada sidebar. Akses tanpa akun berbasis NIM masih menjadi pertanyaan Keputusan 4. | **BELUM DIPUTUSKAN** — mengikuti Keputusan 4 dan 10. |
| Ka-Lab Mutu | `resources/views/layouts/navigation.blade.php:105-117` hanya menampilkan bilah atas; `routes/web.php:289-300` memuat rute laporan. | Dashboard Ka-Lab Mutu, Laporan SIK (`laporan-sik`), dan Laporan per tahun (`laporan-sik.tahun`). `app/Enums/UserRole.php:51-55` mencantumkan modul rencana (Rekap kehadiran, Laporan evaluasi, Perbandingan laporan tahunan), tetapi keberadaannya sebagai menu belum diverifikasi. | **BELUM DIPUTUSKAN** untuk menu akhir; modul rencana **BELUM DIVERIFIKASI**. |

Perubahan daftar menu memerlukan `[PERSETUJUAN-KODE]` setelah kebijakan terkait diputuskan.

### 3.2 Ketentuan minimal kehadiran (KEPUTUSAN PENGGUNA)

| Aspek | Sumber | Posisi dokumen ini |
|---|---|---|
| Ambang yang berlaku | Keputusan pengguna §0.2 no. 3 | **85% dari pertemuan yang benar-benar terlaksana.** Pertemuan dibatalkan tidak dihitung sebagai penyebut. |
| Konfigurasi lama | `config/sik.php`: `meeting_count=16`, `minimum_present=14`, `minimum_percentage=87,5` | Bukan lagi acuan kebijakan. Nilai yang dipakai aplikasi adalah `85`; angka 14/16 hanya ilustrasi historis. |
| Narasi proyek | `docs/RENCANA_IMPLEMENTASI_SIKVERSE.md:1510` | Pertanyaan lama dianggap terjawab oleh pengguna untuk ambang persentase; detail pembulatan tetap **BELUM DITENTUKAN**. |
| Hubungan dengan data lama | `database/migrations/2026_11_01_075544_create_rekap_kehadiran_sik_table.php:22` | Nilai awal lama `total_pertemuan=16` tidak diubah. Migration yang sudah dijalankan tidak disentuh. |
| Persetujuan institusi | §7 | **BELUM DIBERIKAN**; angka 85% adalah keputusan pengguna, bukan `[KEBIJAKAN-SIK]` final. |

**Contoh ilustrasi historis:** `14 ÷ 16 = 87,5%` hanya menjelaskan asal angka lama dan bukan ambang yang dipakai. Ambang aktif adalah `85%` dari jumlah pertemuan `Terlaksana`. Detail pembulatan untuk jumlah hadir minimum belum ditentukan, sehingga perbandingan boleh memakai aritmetika integer yang ekuivalen dengan `85%` tanpa menetapkan aturan pembulatan baru.

### 3.3 Pemeriksaan yang belum dapat diverifikasi (BELUM DIVERIFIKASI)

| Pemeriksaan | Status | Alasan |
|---|---|---|
| Tampilan responsif pada lebar 1366/1024/760/420 px | **BELUM DIVERIFIKASI** | Tidak dijalankan pada browser nyata; hanya pemeriksaan kode. |
| Alur browser untuk setiap peran (Admin, Santri, Instruktur SIK, Ka-Lab Mutu) | **BELUM DIVERIFIKASI** | Tidak dijalankan secara interaktif. |
| Pengiriman email pada lingkungan produksi | **BELUM DIVERIFIKASI** | Konfigurasi SMTP produksi belum tersedia. |
| Ketersediaan proses worker antrean di hosting | **BELUM DIVERIFIKASI** | Lingkungan produksi tidak diperiksa. |
| Hosting produksi, backup/restore, dan status `APP_DEBUG` produksi | **BELUM DIVERIFIKASI** | Pemeriksaan tidak dilakukan terhadap lingkungan produksi. |
| Validitas berkas PDF sertifikat fisik | **BELUM DIVERIFIKASI** | Berkas tidak ditemukan pada storage saat audit. |

---

# 4. Daftar Persetujuan yang Dibutuhkan

| Jenis persetujuan | Keputusan yang memerlukan | Keterangan |
|---|---|---|
| `[KEBIJAKAN-SIK]` | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18 | Penetapan aturan bisnis oleh pihak SIK. Penyusun tidak menetapkan. |
| `[PERSETUJUAN-KODE]` | 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18 | Perubahan source code setelah kebijakan ditetapkan. |
| `[PERSETUJUAN-SANTRI-SPREADSHEET]` | 9 | Persetujuan eksplisit terpisah untuk menyentuh file yang dilarang. |
| `[PERSETUJUAN-DATA]` | 1, 2, 3, 4, 5, 6, 12, 16 | Perubahan baris data aktif. Termasuk kelulusan, sertifikat, rekap, absensi, akun santri, dan periode. Tidak termasuk perubahan nilai. |
| `[PERSETUJUAN-SCHEMA]` | 2 (opsional), 3, 12, 20 | Perubahan migration/schema. Tidak dijalankan ke DB aktif tanpa persetujuan. |
| `[PERSETUJUAN-KONFIG]` | 6, 18, 19 | Nilai konfigurasi produksi, path storage, dan build/deploy. |

**Catatan:** Seluruh persetujuan di atas **belum diberikan**.

---

# 5. Urutan Keputusan Berdasarkan Ketergantungan

Pembagian tahap berikut membedakan keputusan yang **wajib disetujui sebelum implementasi inti**, keputusan yang **hanya diperlukan sebelum modul terkait dikerjakan**, dan keputusan yang **dapat ditunda sampai tahap akhir**. G1–G6 juga merupakan urutan pengerjaan; sebuah nomor keputusan dapat muncul kembali pada G2–G5 hanya sebagai rujukan urutan implementasi, bukan penambahan keputusan.

```text
G0 — Keputusan wajib sebelum implementasi inti
     1, 2, 3, 4, 5, 6, 7, 8, 12
     Alasan: menetapkan kebijakan akun santri/NIM, periode, kegiatan, pertemuan,
     ambang/rekap, otorisasi, kelulusan, dan sertifikat. Keputusan 4 termasuk karena
     menentukan penyediaan akun serta akses nilai/absensi/sertifikat santri.
                         │
                         ▼
G1 — Modul pendukung; menunggu keputusan modulnya sendiri, bukan seluruh G0
     9 (impor), 10 (registrasi/pendaftaran), 11 (notifikasi kata sandi),
     15 (titik akhir kata sandi), 16 (riwayat absensi)
     Catatan: implementasi otorisasi memakai Keputusan 7 dan 8, tetapi baru boleh
     dilakukan setelah keduanya disetujui di G0.
                         │
                         ▼
G2 — Sumber kebenaran data (implementasi dari G0)
     Keputusan 5 (periode) → Keputusan 12 (kegiatan) → Keputusan 2 (pertemuan)
                         │
                         ▼
G3 — Rekap (implementasi dari G0)
     Keputusan 3 — hanya setelah G2 selesai dan sumber data disepakati
                         │
                         ▼
G4 — Kelulusan (implementasi dari G0)
     Keputusan 1 — hanya setelah kebijakan rekap di G3 ditetapkan
                         │
                         ▼
G5 — Sertifikat (implementasi dari G0)
     Keputusan 6 — hanya setelah kelulusan di G4 diputuskan
                         │
                         ▼
G6 — Tahap akhir / modul non-inti
     13, 14, 17, 18, 19, 20
     Boleh menunggu keputusan dan persetujuan terkait saja; tidak wajib menunggu G2–G5.
```

**Rekap cakupan 20 keputusan:** G0 = 1, 2, 3, 4, 5, 6, 7, 8, 12; G1 = 9, 10, 11, 15, 16; G6 = 13, 14, 17, 18, 19, 20. Keputusan 5, 12, 2, 3, 1, dan 6 muncul kembali pada G2–G5 hanya sebagai rujukan urutan implementasi.

**Aturan ketergantungan penting:**

- Keputusan 4 wajib di G0 karena menentukan apakah santri tanpa akun diberi akun atau mendapat akses terbatas berbasis NIM. Hal ini mengubah rute `nilai-saya`, `absensi-saya`, dan `sertifikat-saya` serta pekerjaan membuat/menautkan akun. Pelaksanaan teknisnya baru dilakukan di G1 setelah keputusan disetujui.
- Perbaikan hak akses tidak boleh dilakukan sebelum Keputusan 7 dan 8 disetujui di G0.
- Koreksi sumber data berurutan: periode (5) → kegiatan (12) → pertemuan (2). Rekap (3) baru dihitung setelah G2 selesai dan sumber data disepakati.
- Evaluasi/koreksi kelulusan (1) baru dilakukan setelah kebijakan rekap (3) ditetapkan di G3.
- Penanganan sertifikat (6) mengikuti keputusan kelulusan (1) di G4.
- Pekerjaan G1 dan G6 tidak dipaksa menunggu seluruh G0 maupun G2–G5; cukup keputusan dan persetujuan yang melekat pada modulnya, kecuali pekerjaan otorisasi yang tetap bergantung pada Keputusan 7 dan 8.
- Keputusan 2 boleh memakai pengecualian backdate, tetapi hanya jika pihak SIK menetapkan mekanismenya.

---

# 6. Daftar Pekerjaan yang Masih Memerlukan Detail atau Persetujuan

Pekerjaan di bawah **tidak lagi seluruhnya ditunda**: keputusan pengguna di §0.2 menjadi dasar implementasi teknis. Yang tetap dibatasi adalah perubahan data historis, `.env`/konfigurasi produksi, migration yang sudah dijalankan, file dilindungi, dan tindakan destruktif. Pembagian tahap tetap dipakai sebagai urutan kerja: **G1** = pendukung; **G2–G5** = periode → kegiatan → pertemuan → rekap → kelulusan → sertifikat; **G6** = tahap akhir. Detail yang masih kosong pada §0.2 tidak boleh diisi dengan asumsi.

| No. | Pekerjaan yang ditunda | Tahap | Syarat untuk mulai |
|---:|---|---|---|
| 1 | Perubahan kode otorisasi Tandai Selesai | G1 (Keputusan 7 dari G0) | Keputusan 7 + `[PERSETUJUAN-KODE]` |
| 2 | Perubahan helper/status instruktur | G1 (Keputusan 8 dari G0) | Keputusan 8 + `[PERSETUJUAN-KODE]` |
| 3 | Validasi pertemuan masa depan / backdate | G2 | Keputusan 2 + `[PERSETUJUAN-KODE]` |
| 4 | Koreksi data `pertemuan.id=1` | G2 | Keputusan 2 + `[PERSETUJUAN-DATA]` |
| 5 | Hitung ulang `rekap_kehadiran_sik` | G3 | Keputusan 3 + `[PERSETUJUAN-DATA]` |
| 6 | Perubahan default `total_pertemuan` | G3 | Keputusan 3 + `[PERSETUJUAN-SCHEMA]` |
| 7 | Evaluasi/koreksi `kelulusan_sik.id=2` | G4 | Keputusan 1 + `[PERSETUJUAN-DATA]` |
| 8 | Restore/regenerasi/invalidasi sertifikat | G5 | Keputusan 6 + `[PERSETUJUAN-DATA]` + `[PERSETUJUAN-KONFIG]` |
| 9 | Menyentuh `app/Services/SantriSpreadsheet.php` | G1 | Keputusan 9 + `[PERSETUJUAN-SANTRI-SPREADSHEET]` |
| 10 | Menambah/mengubah rute `/register` atau rute pendaftaran peserta SIK | G1 | Keputusan 10 + `[PERSETUJUAN-KODE]` |
| 11 | Mengganti kelas notifikasi pengaturan ulang kata sandi | G1 | Keputusan 11 + `[PERSETUJUAN-KODE]` |
| 12 | Penggabungan/penamaan ulang kegiatan duplikat | G2 | Keputusan 12 + `[PERSETUJUAN-DATA]` |
| 13 | Menghapus/mengubah relasi atau tabel legacy | G6 | Keputusan 13; **penghapusan tabel tetap dilarang** |
| 14 | Mengaktifkan/mengarsipkan rute Jadwal & Mata Pelajaran | G6 | Keputusan 14 + `[PERSETUJUAN-KODE]` |
| 15 | Menyatukan titik akhir pengubahan kata sandi | G1 | Keputusan 15 + `[PERSETUJUAN-KODE]` |
| 16 | Rekonsiliasi `riwayat_absensi` | G1 | Keputusan 16 + `[PERSETUJUAN-DATA]` |
| 17 | Mengubah definisi "keanggotaan terbaru" | G6 | Keputusan 17 + `[PERSETUJUAN-KODE]` |
| 18 | Migrasi/refactor frontend | G6 | Keputusan 18 + `[PERSETUJUAN-KONFIG]` |
| 19 | Mengubah `.env` atau konfigurasi produksi | G6 | Keputusan 19 + `[PERSETUJUAN-KONFIG]` |
| 20 | Menjalankan/mengubah migration apa pun | G6 | Keputusan 20 + `[PERSETUJUAN-SCHEMA]` |
| 21 | Membuat/menautkan akun untuk santri tanpa akun | G1 (Keputusan 4 dari G0) | Keputusan 4 + `[PERSETUJUAN-DATA]` |
| 22 | Koreksi master periode | G2 | Keputusan 5 + `[PERSETUJUAN-DATA]` |

**Larangan yang tetap berlaku:** tidak ada perubahan pada migration yang sudah dijalankan, tidak ada DROP/DELETE tabel legacy atau data historis, tidak ada perubahan `.env`/konfigurasi produksi, tidak ada penyentuhan `app/Services/SantriSpreadsheet.php` tanpa persetujuan khusus, dan tidak ada commit/push tanpa instruksi terpisah. Pekerjaan DB hanya pada DB isolasi/testing dengan backup terverifikasi.

---

# 7. Checklist Persetujuan untuk Pihak SIK

> Petunjuk: centang (`[x]`) dan isi kolom keputusan setelah pihak SIK menetapkan. Penyusun tidak mengisi bagian ini.

| No. | Keputusan | Pilihan (A/B/C/D/E/F) / Isi keputusan | Disetujui oleh | Tanggal | Centang |
|---:|---|---|---|---|:---:|
| 1 | Status kelulusan tanpa dasar kehadiran |  |  |  | [ ] |
| 2 | Status/tanggal pertemuan masa depan |  |  |  | [ ] |
| 3 | Kebijakan hitung ulang rekap |  |  |  | [ ] |
| 4 | Akun login santri dan akses informasi berbasis NIM |  |  |  | [ ] |
| 5 | Master periode resmi SIK |  |  |  | [ ] |
| 6 | Sertifikat PDF hilang |  |  |  | [ ] |
| 7 | Hak Tandai Selesai kegiatan bersama |  |  |  | [ ] |
| 8 | Status instruktur aktif/tidak aktif |  |  |  | [ ] |
| 9 | Kontrak Import Santri |  |  |  | [ ] |
| 10 | Registrasi akun publik vs pendaftaran peserta SIK |  |  |  | [ ] |
| 11 | Notifikasi pengaturan ulang kata sandi |  |  |  | [ ] |
| 12 | Kegiatan duplikat |  |  |  | [ ] |
| 13 | Tabel legacy |  |  |  | [ ] |
| 14 | Modul Jadwal & Mata Pelajaran |  |  |  | [ ] |
| 15 | Titik akhir pengubahan kata sandi standar |  |  |  | [ ] |
| 16 | Riwayat absensi vs status absensi |  |  |  | [ ] |
| 17 | Definisi keanggotaan terbaru |  |  |  | [ ] |
| 18 | Standar frontend |  |  |  | [ ] |
| 19 | Domain, HTTPS, email, queue, storage |  |  |  | [ ] |
| 20 | Migration pending & no-op |  |  |  | [ ] |

### Persetujuan tambahan yang harus ditandatangani terpisah

| Persetujuan | Keputusan terkait | Ditandatangani | Tanggal | Centang |
|---|---|---|---|:---:|
| `[PERSETUJUAN-SANTRI-SPREADSHEET]` | 9 |  |  | [ ] |
| `[PERSETUJUAN-DATA]` untuk `kelulusan_sik` / `sertifikat_sik` | 1, 6 |  |  | [ ] |
| `[PERSETUJUAN-DATA]` untuk `absensi_sik` / `riwayat_absensi` | 2, 16 |  |  | [ ] |
| `[PERSETUJUAN-DATA]` untuk `rekap_kehadiran_sik` | 3 |  |  | [ ] |
| `[PERSETUJUAN-DATA]` untuk `santris` / `users` | 4 |  |  | [ ] |
| `[PERSETUJUAN-DATA]` untuk `periode_sik` | 5 |  |  | [ ] |
| `[PERSETUJUAN-DATA]` untuk `kegiatan` | 12 |  |  | [ ] |
| `[PERSETUJUAN-SCHEMA]` | 2, 3, 12, 20 |  |  | [ ] |
| `[PERSETUJUAN-KONFIG]` | 6, 18, 19 |  |  | [ ] |

> Tidak ada baris `[PERSETUJUAN-DATA]` untuk `nilai` karena dokumen ini tidak merencanakan koreksi data nilai.

---

## 8. Penutup

Dokumen ini menyajikan **20 keputusan kebijakan** dari audit G0, hasil diskusi pengguna yang tercatat di §0.2, dan checklist persetujuan institusi yang masih kosong di §7. Setiap keputusan memuat kondisi berbasis bukti audit, pertanyaan, pilihan, rekomendasi teknis, dampak, file/tabel terdampak, jenis persetujuan, prioritas, serta status pengguna dan status persetujuan institusi yang dibedakan.

**Keputusan pengguna telah dicatat. Persetujuan institusi formal belum diberikan.** Implementasi teknis dilakukan hanya sejajar dengan keputusan pengguna, dengan backup terverifikasi, migration baru saja, tanpa perubahan destruktif, tanpa `.env`, dan tanpa menyentuh `app/Services/SantriSpreadsheet.php`.

Checklist §7 sengaja tidak diisi. Apabila pihak SIK kemudian menetapkan keputusan berbeda dari §0.2, kode dan dokumentasi harus diselaraskan kembali dengan keputusan institusi tersebut.
