# RENCANA IMPLEMENTASI REVISI SIKVERSE

**Tahap:** 2 — Perencanaan
**Status:** Rencana saja. Belum ada kode, migration, atau data yang diubah.
**Prioritas terbaru:** Web-first tanpa ketergantungan fingerprint. Seluruh pekerjaan fingerprint berstatus **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**.
**Dasar:** Laporan Audit Tahap 1 + keputusan aturan sistem pada brief Tahap 2 + perubahan prioritas Tahap 2.
**Tanggal:** 2026-11-17

---

## 1. Ringkasan Keputusan Sistem

| No | Keputusan | Konsekuensi Teknis |
|---|---|---|
| 1 | Kelulusan dihitung **per kegiatan** | `kelulusan_sik` harus punya `kegiatan_id`; evaluasi tidak boleh menghitung lintas kegiatan |
| 2 | Syarat kelulusan **hanya kehadiran** (persentase/jumlah) | `nilai` tidak dipakai di service kelulusan; tidak ada join ke `nilai` |
| 3 | Tabel absensi legacy **diarsipkan, tidak dihapus** | Rename ke `arsip_*` setelah kode berhenti memakainya; data asli dipertahankan |
| 4 | Fingerprint dan absensi kelas adalah **dua jenis pencatatan berbeda**; pekerjaan fingerprint **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** | Untuk sementara absensi santri dicatat manual melalui website ke `absensi_sik`; fingerprint bukan sumber absensi dan tidak mengubah data lama |
| 5 | Data & struktur fingerprint yang sudah ada **dipertahankan tanpa perubahan** | `fingerprints` + `log_penggunaan_fingerprint` tidak dimodifikasi; tidak ada import, SDK/API, koneksi perangkat, atau migration fingerprint baru |
| 6 | Absensi dosen tetap `absensi_dosen` | Tidak membuat tabel baru; hanya menambah alur input/kelola dan audit |
| 7 | **Kelulusan Pembinaan Tahsin ditentukan manual oleh instruktur**, disimpan **terpisah** dari Kelulusan SIK, **satu catatan per santri per periode SIK** | Tabel `kelulusan_tahsin` (N2) dengan `anggota_periode_sik_id` **UNIQUE**; **tanpa** `kegiatan_id` dan tanpa relasi langsung ke kegiatan; admin hanya melihat; sertifikat butuh kedua status `Lulus` |

**Prinsip turunan yang dipakai di seluruh rencana:**

1. `absensi_sik` = **satu-satunya sumber kebenaran absensi santri**.
2. `log_penggunaan_fingerprint` = **bukti/log scan lama**, bukan absensi sah; seluruh pekerjaan terkait berstatus **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**.
3. `absensi_dosen` = pencatatan kehadiran dosen/instruktur, terpisah total dari rekap santri dan fingerprint.
4. `rekap_kehadiran_sik` = **cache agregat** per (`anggota_periode_sik_id`, `kegiatan_id`), selalu bisa dibangun ulang.
5. Tidak ada angka pertemuan hard-code; semua memakai jumlah `pertemuan` aktual per kegiatan.
6. **Tambahan Tahap 2:** nilai ujian SIK adalah dasar evaluasi hasil ujian; **nilai harian tetap bukan syarat kelulusan**. Kehadiran minimal **87,5% dari pertemuan `Terlaksana`** adalah syarat mutlak untuk **mengikuti** ujian, bukan pengganti nilai ujian.
7. Status "kelayakan mengikuti ujian" dan "status mengikuti ujian" bersifat **turunan** (dihitung), bukan kolom baru.
8. **Kelulusan SIK ≠ Kelulusan Pembinaan Tahsin.** Keduanya tersimpan di tabel berbeda, diinput oleh pihak berbeda, dan **harus sama-sama `Lulus`** sebelum sertifikat akhir diterbitkan. Tidak ada menu/halaman baru untuk keduanya.
9. **Granularitas Tahsin sudah final:** satu baris `kelulusan_tahsin` untuk setiap `anggota_periode_sik_id` (santri + periode SIK), **bukan per kegiatan**. Satu santri dapat memiliki status Tahsin berbeda pada periode SIK berbeda. Tidak ada kolom `kegiatan_id` dan tidak ada relasi langsung ke `kegiatan` pada tabel Tahsin.
10. **Satu sumber perhitungan rekap:** seluruh angka rekap (tabel utama, halaman detail, export, dashboard, laporan, dan halaman Instruktur) berasal dari `absensi_sik` → `AttendanceRecapService` → `rekap_kehadiran_sik`. Tidak diperbolehkan menghitung ulang dengan query/CSS/JS lain di tampilan.
11. **Rabu Mengaji bukan tabel absensi baru:** data Rabu Mengaji memakai struktur `kegiatan` + `pertemuan` + `absensi_sik` dengan penanda kegiatan yang jelas. Tidak ada tabel duplikat, dan tidak ada penghitungan ganda dengan fingerprint (lihat §12.3).
12. **Web-first, fingerprint bukan penghambat:** semua fitur website yang tidak bergantung perangkat fingerprint masuk prioritas implementasi dan dapat diuji secara mandiri.
13. **Absensi sementara melalui website:** Instruktur memilih **kegiatan → pertemuan → status `Hadir`/`Izin`/`Sakit`/`Alpha` → simpan**, lalu rekap diperbarui dari `absensi_sik` yang sama. Tidak ada dua sistem pencatatan yang saling bertentangan (lihat §3.3 dan §12.9).
14. **Metode absensi akhir terbuka:** pihak SIK belum menetapkan wajib fingerprint atau metode lain; keputusan diambil setelah konsultasi. Sampai saat itu, seluruh pekerjaan fingerprint berstatus **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**.

> **Fokus pengembangan prioritas saat ini (tanpa fingerprint):**
> 1. Login dan autentikasi; hak akses dan dashboard setiap role.
> 2. Manajemen data santri; kelompok dan periode SIK; instruktur/dosen.
> 3. Absensi santri melalui website dan rekap kehadiran santri.
> 4. Tabel/data Rabu Mengaji.
> 5. Nilai ujian dan grade; Kelulusan SIK per kegiatan.
> 6. Kelulusan Pembinaan Tahsin per santri per periode.
> 7. Sertifikat **satu kali per santri per periode SIK** (bukan per kegiatan).
> 8. Laporan Ka-Lab Mutu.
> 9. Perbaikan seluruh halaman, tombol, route, validasi, dan fitur yang masih bermasalah.
>
> **Metode absensi akhir akan diputuskan setelah konsultasi dengan pihak SIK.**

> **Rekonsiliasi keputusan:** keputusan lama "syarat kelulusan hanya kehadiran, nilai akademik tidak dipakai" tetap berlaku untuk **nilai harian**. Setelah Tambahan Tahap 2, yang dimaksud "nilai ujian" adalah `nilai` dengan `jenis_nilai = 'Ujian'`, yang dipakai untuk menilai hasil ujian SIK. Rincian ada di §11.

---

## 2. Arsitektur Sistem yang Diusulkan

### 2.1 Lapisan

```
┌─────────────────────────────────────────────────────────────────────┐
│ PRESENTASI (Blade)                                                  │
│  - Absensi Santri via Website (kegiatan → pertemuan → status)       │
│  - Rekap Kehadiran Santri (index ringkas + detail lengkap)          │
│  - Absensi Rabu Mengaji (cari santri, filter tanggal, detail)       │
│  - [DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK] Rekap Fingerprint        │
│  - Rekap Absensi Dosen (index + input + detail)                     │
│  - Kelulusan per Kegiatan + Sertifikat per Periode + Laporan Ka-Lab  │
│  - Nilai Instruktur (tanpa blok hijau penanda; label "Kelompok")    │
│  - Status Tahsin per Santri per Periode (admin: read-only)          │
│  - Data Dosen & Instruktur (tanpa tautan rekap di header)           │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────┐
│ HTTP (Controller + Request Validation + Policy/Middleware)          │
│  AbsensiSikController (prioritas) PertemuanController (prioritas)  │
│  [DITUNDA] FingerprintImportController / FingerprintRecapController │
│  RekapKehadiranSikController     RekapAbsensiDosenController         │
│  KelulusanSikController          UserManagementController            │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────┐
│ DOMAIN SERVICE (baru, agar aturan tidak tersebar)                   │
│  AttendanceRecapService      → hitung & simpan rekap per kegiatan   │
│  AttendanceEvaluationService → evaluasi kelulusan per kegiatan      │
│  ExamEligibilityService      → syarat ujian: ≥87,5% Terlaksana      │
│  GradeService                → konversi angka → grade (belum diset) │
│  [DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK] FingerprintImportService  │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
┌───────────────────────────────▼─────────────────────────────────────┐
│ DATA (Eloquent + MySQL)                                             │
│  absensi_sik ─ pertemuan ─ kegiatan                                  │
│  rekap_kehadiran_sik (cache)                                        │
│  kelulusan_sik (+ kegiatan_id) ─ sertifikat_sik                     │
│  kelulusan_tahsin (anggota_periode_sik_id UNIQUE; tanpa kegiatan)   │
│  fingerprints ─ log_penggunaan_fingerprint (existing; tanpa ubahan) │
│  absensi_dosen                                                      │
│  arsip_absensi, arsip_rekap_kehadiran, arsip_jadwal, ... (legacy)   │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.2 Peta tabel → model → controller → route → halaman

| Entitas | Tabel | Model | Controller | Route | Halaman |
|---|---|---|---|---|---|
| Santri | `santris` | `Santri` | `SantriController` | `santris.*` | `santris/*` |
| Periode | `periode_sik` | `PeriodeSik` | `PeriodeSikController` | `periode-sik.*` | `periode-sik/*` |
| Kelompok | `kelompok` | `Kelompok` | `KelompokController` | `kelompok.*`, `instruktur.kelompok.*` | `kelompok/*` |
| Keanggotaan | `anggota_periode_sik` | `AnggotaPeriodeSik` | `AnggotaPeriodeSikController` | `anggota-periode-sik.*` | `anggota-periode-sik/*` |
| Kegiatan | `kegiatan` | `Kegiatan` | `KegiatanController` | `kegiatan.*` | `kegiatan/*` |
| Pertemuan | `pertemuan` | `Pertemuan` | `PertemuanController` | `pertemuan.*` | `pertemuan/*` |
| Absensi kelas (prioritas; manual website) | `absensi_sik` | `AbsensiSik` | `AbsensiSikController` | `absensi-sik.*` | `absensi-sik/index` + form kegiatan → pertemuan → status |
| Absensi Rabu Mengaji | **memakai** `kegiatan` + `pertemuan` + `absensi_sik` (penanda kegiatan; **tanpa tabel baru**) | `Kegiatan`/`Pertemuan`/`AbsensiSik` | `AbsensiSikController` | memakai `absensi-sik.*` | filter/detail pada halaman absensi yang ada |
| Cache rekap | `rekap_kehadiran_sik` | `RekapKehadiranSik` | `RekapKehadiranSikController` | `rekap-kehadiran-sik.*` | `rekap-kehadiran-sik/index` (6 kolom) + detail (baru, lengkap) |
| Kelulusan | `kelulusan_sik` | `KelulusanSik` | `KelulusanSikController` | `kelulusan-sik.*` | `kelulusan-sik/*` |
| Sertifikat | `sertifikat_sik` (direncanakan: **satu per `anggota_periode_sik_id`**, lihat S1) | `SertifikatSik` | `SertifikatSikController` | `sertifikat-sik.*` | `sertifikat-sik/*` |
| Nilai (harian + ujian) | `nilai` | `Nilai` | `NilaiController` | `nilai.*`, `nilai-saya` | `nilai/index`, `nilai/transkrip` |
| Kelayakan ujian | turunan dari `absensi_sik` + `pertemuan` | `ExamEligibilityService` (baru) | `NilaiController`, `KelulusanSikController` | memakai route yang ada | badge di `nilai/index`, `kelulusan-sik/index` |
| Kelulusan Tahsin | **baru** `kelulusan_tahsin` (N2, lihat §11.7) — 1 baris per `anggota_periode_sik_id`, tanpa `kegiatan_id` | `KelulusanTahsin` (baru) | `AnggotaPeriodeSikController` (method baru, tanpa controller baru) | `instruktur.anggota.kelulusan-tahsin.update` (baru, tanpa halaman baru) | inline di `anggota-periode-sik/index` (mode instruktur) + tampilan read-only di `kelulusan-sik/index` |
| Fingerprint registry | `fingerprints` (existing, **tanpa perubahan**) | `Fingerprint` | `SantriController` (registrasi existing) | `santris.*` | `santris/partials/form-fields` — **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** |
| Log scan | `log_penggunaan_fingerprint` (existing, **tanpa perubahan**) | `LogPenggunaanFingerprint` | **`FingerprintRecapController` (baru)** | **`fingerprint-rekap.*` (baru)** | **`fingerprint-rekap/*` (baru)** — **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** |
| Import fingerprint | **TIDAK DIBUAT** `fingerprint_import_batches`, `fingerprint_import_rows` | **TIDAK DIBUAT** | **`FingerprintImportController` (baru)** | **`fingerprint-import.*` (baru)** | **`fingerprint-import/*` (baru)** — **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** |
| Absensi dosen | `absensi_dosen` | `AbsensiDosen` | `RekapAbsensiDosenController` | `rekap-absensi-dosen.*` | `rekap-absensi-dosen/*` |
| Bukti tidak hadir | `bukti_ketidakhadiran` | `BuktiKetidakhadiran` | `BuktiKetidakhadiranController` | `absensi-saya.bukti.*`, `bukti-ketidakhadiran.*` | `santris/absensi-saya`, **`bukti-ketidakhadiran/index` (baru)** |

### 2.3 Route yang perlu ditambah / dirapikan

| Route | Method | Controller@method | Peran |
|---|---|---|---|
| `absensi-sik.index` | GET | `AbsensiSikController@index` | instruktur, admin — **prioritas**, form manual website (§3.3) |
| `absensi-sik.store` | POST | `AbsensiSikController@store` | instruktur, admin — **prioritas**, simpan `Hadir`/`Izin`/`Sakit`/`Alpha` |
| `fingerprint-import.create` | GET | `FingerprintImportController@create` | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** (tidak didaftarkan) |
| `fingerprint-import.preview` | POST | `@preview` | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** (tidak didaftarkan) |
| `fingerprint-import.commit` | POST | `@commit` | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** (tidak didaftarkan) |
| `fingerprint-import.index` | GET | `@index` (riwayat batch) | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** (tidak didaftarkan) |
| `fingerprint-import.show` | GET | `@show` (detail + baris gagal) | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** (tidak didaftarkan) |
| `fingerprint-rekap.index` | GET | `FingerprintRecapController@index` | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** (tidak didaftarkan) |
| `fingerprint-rekap.export` | GET | `@export` | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** (tidak didaftarkan) |
| `rekap-kehadiran-sik.show` | GET | `RekapKehadiranSikController@show` | admin, instruktur |
| `rekap-absensi-dosen.create` | GET | `RekapAbsensiDosenController@create` | admin |
| `rekap-absensi-dosen.edit` | GET | `@edit` | admin |
| `rekap-absensi-dosen.update` | PUT | `@update` | admin |
| `rekap-absensi-dosen.destroy` | DELETE | `@destroy` | admin |
| `bukti-ketidakhadiran.index` | GET | `BuktiKetidakhadiranController@index` | admin, instruktur |
| `bukti-ketidakhadiran.verify` | POST | `@verify` | admin, instruktur |
| `bukti-ketidakhadiran.reject` | POST | `@reject` | admin, instruktur |
| `instruktur.anggota.kelulusan-tahsin.update` | PATCH | `AnggotaPeriodeSikController@updateKelulusanTahsin` | instruktur (hanya anggota kelompoknya) |
| `jadwal.*` | — | `JadwalController` | **putuskan: daftarkan atau hapus** |
| `mata-pelajaran.*` | — | `MataPelajaranController` | **putuskan: daftarkan atau hapus** |

---

## 3. Diagram Alur Sistem

### 3.1 Alur utama: input → rekap → evaluasi kelulusan

```
                        ┌───────────────────────────┐
                        │ Admin membuat Periode     │
                        │ → Kelompok → Kegiatan     │
                        │ → Pertemuan (1..N)        │
                        └─────────────┬─────────────┘
                                      │
              ┌───────────────────────┼────────────────────────┐
              │                       │                        │
              ▼                       ▼                        ▼
   ┌────────────────────┐  ┌─────────────────────┐  ┌──────────────────────┐
   │ INPUT ABSENSI WEB  │  │ [DITUNDA - MENUNGGU │  │ INPUT ABSENSI DOSEN  │
   │ (instruktur)       │  │ KEPUTUSAN PIHAK SIK]│  │ (admin)              │
   │ kegiatan→pertemuan │  │ IMPORT FINGERPRINT  │  │ RekapAbsensiDosen    │
   │ →status→simpan     │  │ TIDAK DIBUAT        │  │                      │
   │ AbsensiSikController│ │                     │  │                      │
   └─────────┬──────────┘  └──────────┬──────────┘  └──────────┬───────────┘
             │                        │ (tidak ada alur)        │
             ▼                        ▼                        ▼
   ┌────────────────────┐  ┌─────────────────────┐  ┌──────────────────────┐
   │ absensi_sik        │  │ fingerprints + log  │  │ absensi_dosen        │
   │ (Hadir/Izin/Sakit/ │  │ fingerprint existing│  │ (Hadir/Izin/Sakit/   │
   │ Alpha)             │  │ TANPA perubahan;    │  │ Alpha)               │
   │ metode: Manual     │  │ BUKAN sumber rekap  │  │ unik: dosen+tanggal  │
   └─────────┬──────────┘  └─────────────────────┘  └──────────┬───────────┘
             │                                                 │
             ▼                                                 ▼
   ┌─────────────────────────────────────────┐   ┌──────────────────────────┐
   │ AttendanceRecapService                  │   │ REKAP ABSENSI DOSEN      │
   │ hitung per (anggota, kegiatan):         │   │ ringkasan + riwayat      │
   │ total pertemuan aktual, hadir, izin,    │   │ (terpisah dari santri)   │
   │ sakit, alpha, tidak lengkap, %          │   └──────────────────────────┘
   └───────────────┬─────────────────────────┘
                   │ updateOrCreate
                   ▼
   ┌─────────────────────────────────────────┐
   │ rekap_kehadiran_sik (cache)             │
   └───────────────┬─────────────────────────┘
                   │
                   ▼
   ┌─────────────────────────────────────────┐
   │ AttendanceEvaluationService             │
   │ evaluasi PER KEGIATAN (bukan gabungan)  │
   │ - total = pertemuan aktual kegiatan     │
   │ - hadir = absensi_sik status Hadir      │
   │ - % + alasan bila belum memenuhi        │
   └───────────────┬─────────────────────────┘
                   ▼
   ┌─────────────────────────────────────────┐
   │ kelulusan_sik (+ kegiatan_id)           │
   │ status Lulus / Tidak Lulus / Belum Lulus│
   └───────────────┬─────────────────────────┘
                   │
        ┌──────────┴──────────┐
        ▼                     ▼
┌──────────────────┐  ┌──────────────────────────┐
│ Sertifikat       │  │ Laporan Ka-Lab Mutu      │
│ (per record      │  │ memakai hasil evaluasi   │
│  kelulusan)      │  │ yang sama (konsisten)    │
└──────────────────┘  └──────────────────────────┘
```

### 3.2 Alur import fingerprint (detail) — **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**

> **Tidak diimplementasikan pada prioritas ini.** Bagian ini hanya arsip rancangan untuk masa depan. Tidak ada import, SDK/API, koneksi perangkat, migration baru, atau asumsi format file yang dikerjakan sekarang. Data dan struktur fingerprint yang sudah ada dipertahankan tanpa perubahan.

```
File hasil ekspor alat fingerprint
(FORMAT BELUM DITETAPKAN — menunggu contoh file asli dari perangkat;
 CSV/XLSX belum diasumsikan, lihat §12.6 dan Lampiran A poin 7)
        │
        ▼
[1] Upload → validasi ekstensi/ukuran sesuai format contoh → simpan sementara (storage/app/private)
        │
        ▼
[2] Parse → normalisasi kolom (pemetaan SEMENTARA, mengikuti contoh file):
        fingerprint_uid | waktu_scan | (opsional) nomor_induk | status alat
        │
        ▼
[3] Pra-validasi per baris:
        - format tanggal/waktu valid?
        - fingerprint_uid tidak kosong?
        - waktu_scan tidak di masa depan?
        │
        ▼
[4] Pencocokan identitas (berurutan):
        a. fingerprints.fingerprint_id == fingerprint_uid          → cocok
        b. santris.nomor_induk == fingerprint_uid / nomor_induk     → cocok
        c. mapping manual dari file (kolom NIK/NIM)                 → cocok
        d. tidak ada / ambigu (>1 santri)                           → GAGAL
        │
        ▼
[5] Deteksi duplikat:
        - kunci alami (fingerprint_id, waktu_scan) sudah ada?       → DUPLIKAT
        - external_scan_id sudah ada?                               → DUPLIKAT
        - file_hash sama dengan batch sebelumnya?                   → peringatan
        │
        ▼
[6] Pratinjau: total / valid / duplikat / gagal + alasan per baris
        │
        ▼ (konfirmasi admin)
[7] Commit dalam transaksi:
        - buat fingerprint_import_batches (status=Selesai/Gagal)
        - simpan SEMUA baris ke fingerprint_import_rows (audit, termasuk gagal)
        - simpan baris VALID ke log_penggunaan_fingerprint
        │
        ▼
[8] Riwayat import: batch, file, jumlah, siapa, kapan, baris gagal + alasan
```

**Batas penting:** pekerjaan import fingerprint berstatus **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**. Tidak ada baris fingerprint yang ditulis ke `absensi_sik`. Data fingerprint existing adalah bukti/log lama dan **tidak diubah**. Metode absensi akhir akan diputuskan setelah konsultasi dengan pihak SIK (lihat §8.4 dan Lampiran A poin 4/16).

> **Catatan pada diagram §3.1:** cabang `IMPORT FINGERPRINT` di atas **tidak aktif**. Alur operasional yang dipakai sekarang adalah `INPUT ABSENSI WEB` → `absensi_sik` → `AttendanceRecapService` → `rekap_kehadiran_sik`.

### 3.3 Alur absensi sementara melalui website (prioritas aktif)

Website harus tetap dapat digunakan **tanpa perangkat fingerprint**. Alur pencatatan oleh Instruktur:

1. Instruktur login dan masuk ke halaman Absensi Santri yang sudah ada (`absensi-sik.index`).
2. Instruktur **memilih kegiatan** (termasuk kegiatan Rabu Mengaji yang sudah ditandai).
3. Instruktur **memilih pertemuan** dari kegiatan tersebut (hanya pertemuan yang relevan/`Terlaksana`).
4. Sistem menampilkan santri anggota kelompok binaan instruktur pada periode kegiatan itu (gate otorisasi tetap di server).
5. Untuk setiap santri, instruktur menginput salah satu status: **`Hadir`**, **`Izin`**, **`Sakit`**, atau **`Alpha`**.
6. Instruktur **menyimpan** perubahan. Controller menulis `updateOrCreate` ke `absensi_sik` dengan kunci `(anggota_periode_sik_id, pertemuan_id)` — satu baris per santri per pertemuan.
7. `AbsensiSikController` memanggil `AttendanceRecapService` untuk **menghitung ulang rekap dari sumber yang sama**, lalu menyimpan hasilnya ke `rekap_kehadiran_sik`.
8. Halaman rekap (tabel utama 6 kolom), halaman detail, dashboard, laporan Ka-Lab, dan evaluasi kelulusan **membaca cache/service yang sama**. Tidak ada query agregat kedua.
9. Bila tersedia, `waktu_masuk`/`waktu_pulang` diisi manual oleh instruktur dan tampil di halaman detail; tidak diwajibkan untuk perhitungan status.

**Aturan anti-konflik:**
- Hanya ada **satu sistem pencatatan absensi santri**, yaitu `absensi_sik` melalui website.
- Tidak ada tabel absensi baru untuk sementara; Rabu Mengaji memakai `kegiatan` + `pertemuan` + `absensi_sik` (§12.3).
- Data fingerprint existing **tidak** menambah, mengurangi, atau mengoreksi angka rekap secara otomatis.
- Fitur ini dapat dikembangkan dan diuji **mandiri**, tanpa menunggu keputusan/perangkat fingerprint.

**Checklist alur sementara:**
- [ ] Instruktur dapat memilih kegiatan → pertemuan → status → simpan tanpa fingerprint.
- [ ] Status yang tersedia hanya `Hadir`/`Izin`/`Sakit`/`Alpha`.
- [ ] Simpan memakai `updateOrCreate` pada `absensi_sik`, bukan inserting duplikat.
- [ ] Rekap langsung diperbarui dari `absensi_sik` oleh `AttendanceRecapService`.
- [ ] Tabel utama dan detail rekap menampilkan angka yang sama.
- [ ] Pengujian dapat dilakukan tanpa perangkat/contoh file fingerprint.

---

## 4. Status Tabel Database

### 4.1 Dipertahankan dan menjadi sumber kebenaran

| Tabel | Alasan |
|---|---|
| `santris` | Identitas santri |
| `users`, `infrastruktur_siks`, `admins`, `ka_lab_mutus` | Akun & profil peran |
| `periode_sik`, `kelompok`, `anggota_periode_sik` | Struktur program |
| `kegiatan`, `pertemuan` | Dimensi kegiatan & pertemuan |
| `absensi_sik` | Sumber kebenaran absensi santri |
| `bukti_ketidakhadiran`, `riwayat_absensi` | Bukti & audit perubahan |
| `absensi_dosen` | Absensi dosen/instruktur |
| `fingerprints` | Registry existing — **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**; dipertahankan tanpa perubahan |
| `log_penggunaan_fingerprint` | Log scan existing — **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**; dipertahankan tanpa perubahan, bukan sumber rekap |
| `nilai`, `mata_pelajaran` | Tabel nilai dipakai ulang untuk nilai **harian** dan nilai **ujian SIK**; nilai harian tetap bukan syarat kelulusan (lihat §11) |
| `rekap_kehadiran_sik` | Cache agregat per kegiatan (dibangun ulang) |
| `kelulusan_sik` | Ditambah `kegiatan_id` |
| `sertifikat_sik` | Direncanakan **satu sertifikat per `anggota_periode_sik_id`** (satu santri per periode SIK), bukan per kegiatan; lihat S1 |

### 4.2 Digunakan kembali dengan penyesuaian

| Tabel | Penyesuaian |
|---|---|
| `kelulusan_sik` | + `kegiatan_id` (nullable untuk data lama), unique `(anggota_periode_sik_id, kegiatan_id)`, unique lama `anggota_periode_sik_id` dilepas |
| `log_penggunaan_fingerprint` | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** — tidak ditambah kolom/index apa pun |
| `fingerprints` | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** — tidak ditambah unique/index apa pun |
| `absensi_dosen` | Opsional + `diperbarui_oleh`, `alasan_perubahan` untuk audit edit |
| `infrastruktur_siks` | Opsional + `peran` (`Dosen` / `Instruktur` / `Dosen & Instruktur`) |
| `nilai` | + `jenis_nilai` (`Harian`/`Ujian`), `kegiatan_sik_id`, `grade`, `tanggal_ujian`; `mata_pelajaran_id` dibuat nullable. Data lama tetap utuh (lihat §11.6 / N1) |

### 4.3 Tabel baru (status yang belum ada; fingerprint ditunda)

| Tabel | Fungsi | Alasan tidak dianggap duplikat |
|---|---|---|
| `fingerprint_import_batches` — **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** | Riwayat file import: nama file, hash, pengunggah, jumlah baris, status | **TIDAK DIBUAT** pada prioritas ini |
| `fingerprint_import_rows` — **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** | Baris mentah + status valid/duplikat/gagal + alasan | **TIDAK DIBUAT** pada prioritas ini |
| `kelulusan_tahsin` | Status Kelulusan Pembinaan Tahsin yang ditetapkan manual oleh instruktur; **satu baris per `anggota_periode_sik_id`** (santri + periode SIK); **tanpa `kegiatan_id`**, tanpa relasi langsung ke kegiatan | Tidak ada entity Tahsin di sistem; tidak boleh digabung ke `kelulusan_sik`, ke `nilai`, atau ke tabel legacy `kelulusan` (lihat §11.7) |
| `sertifikat_sik` (penyesuaian S1) | Penyesuaian agar **satu sertifikat per santri per periode SIK**, bukan per `kelulusan_sik` | Granularitas sertifikat sudah diputuskan; detail di §6/S1 dan §11.9 |

### 4.4 Diarsipkan (rename, **tidak dihapus**)

| Tabel lama | Menjadi | Catatan |
|---|---|---|
| `absensi` | `arsip_absensi` | Absensi legacy berbasis `jadwal` |
| `rekap_kehadiran` | `arsip_rekap_kehadiran` | Rekap legacy per tahun/semester |
| `jadwal` | `arsip_jadwal` | Tidak punya route aktif; dipakai `hasOperationalData` |
| `laporan` | `arsip_laporan` | Tidak ada model/controller |
| `kelulusan` | `arsip_kelulusan` | Legacy, tidak dipakai |
| `sertifikat` | `arsip_sertifikat` | Legacy, tidak dipakai |

**Tidak diarsipkan:** `mata_pelajaran` dan `nilai`, karena `nilai` masih memiliki FK ke `mata_pelajaran` dan modul Nilai masih aktif.

### 4.5 Kesesuaian tabel fingerprint untuk data import — **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**

> Analisis di bawah adalah arsikan pertimbangan teknis. **Tidak ada tindakan yang dijalankan sekarang**: tidak ada perluasan tabel, tidak ada tabel baru, tidak ada unique/index baru, dan data fingerprint existing tidak diubah.

| Kebutuhan import | `fingerprints` | `log_penggunaan_fingerprint` | Kesimpulan |
|---|---|---|---|
| Identitas alat (fingerprint UID) | ✅ `fingerprint_id` unik | via FK | Bisa |
| Waktu scan | — | ✅ `waktu_scan` datetime | Bisa |
| Status hasil scan | — | ✅ `status` string | Bisa |
| Riwayat per file import | ❌ | ❌ | **Perlu tabel batch** |
| Baris gagal tanpa ID fingerprint | ❌ | ❌ (`fingerprint_id` NOT NULL) | **Perlu tabel staging** |
| Deteksi duplikat | — | ❌ tidak ada unique | **Perlu unique + kunci eksternal** |
| Asal data (alat/manual) | ❌ | ❌ | **Perlu kolom `sumber`** |
| Audit pencocokan | ❌ | ❌ | **Perlu `metode_pencocokan` + matched IDs** |

**Keputusan tertunda:** `log_penggunaan_fingerprint` existing **dipertahankan apa adanya**. Rencana lama untuk memperluas tabel + 2 tabel pendukung berstatus **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**. Tidak ada tabel `rekap_fingerprint` baru, dan tidak ada perubahan skema/data fingerprint.

### 4.6 ERD ringkas — hubungan data inti (termasuk status Tahsin)

```
┌───────────────┐        ┌─────────────────────┐        ┌──────────────┐
│  periode_sik  │◄───────│ anggota_periode_sik │───────►│   santris    │
└───────────────┘        └──────────┬──────────┘        └──────────────┘
                                    │ 1:N
                    ┌───────────────┴───────────────┐
                    ▼                               ▼
        ┌───────────────────────┐       ┌──────────────────────────┐
        │  kelulusan_sik        │       │  kelulusan_tahsin        │
        │  + kegiatan_id        │       │  anggota_periode_sik_id  │
        │  (PER KEGIATAN)       │       │  UNIQUE                  │
        │                       │       │  1 baris per santri per  │
        │                       │       │  periode SIK             │
        └───────────┬───────────┘       │  TANPA kegiatan_id       │
                    │                   └────────────┬─────────────┘
                    ▼                                │ ditetapkan oleh
        ┌───────────────────────┐                    │ (instruktur_sik_id)
        │  sertifikat_sik       │◄───── GATE ────────┘
        └───────────────────────┘   keduanya harus "Lulus"

        kegiatan ──► pertemuan ──► absensi_sik ──► rekap_kehadiran_sik
        (dimensi SIK: dipakai kelulusan_sik, BUKAN kelulusan_tahsin)
```

**Catatan ERD:**
- `kelulusan_sik` bernilai **per kegiatan**; `kelulusan_tahsin` bernilai **per santri per periode**. Keduanya hanya bertemu di `anggota_periode_sik`.
- Tidak ada garis/relasi langsung dari `kelulusan_tahsin` ke `kegiatan`, `pertemuan`, atau `absensi_sik`.
- Gate sertifikat final: **semua** `kelulusan_sik` dalam satu periode SIK berstatus `Lulus` **dan** `kelulusan_tahsin` berstatus `Lulus`; hasilnya **satu sertifikat per anggota periode** (lihat §11.9).

---

## 5. Daftar File yang Akan Diperbaiki

### 5.1 Prioritas 0 — pemblokir alur

| File | Perubahan |
|---|---|
| `app/Http/Middleware/EnsurePasswordIsChanged.php` | Kecualikan `change-password.update` dan `password.custom-update` |
| `app/Http/Controllers/BuktiKetidakhadiranController.php` | Tambah `index`, `verify`, `reject` + otorisasi instruktur/kelompok |
| `routes/web.php` | Route verifikasi bukti; detail rekap; form/simpan absensi manual website; CRUD absensi dosen; route jadwal/mapel diputuskan; **tidak mendaftarkan route fingerprint — `DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** |
| `resources/views/bukti-ketidakhadiran/index.blade.php` (baru) | Daftar bukti menunggu + aksi terima/tolak |
| `app/Http/Controllers/DashboardController.php`, `app/Http/Middleware/*` | Audit login/autentikasi, `must_change_password`, dan hak akses per role; dashboard tiap role menampilkan data yang sesuai |
| `resources/views/layouts/navigation.blade.php`, `resources/views/auth/*` | Perbaikan menu/tautan yang rusak dan alur login/ganti password tanpa mengubah data |

### 5.2 Prioritas 1 — aturan inti (rekap & kelulusan)

| File | Perubahan |
|---|---|
| `app/Services/AttendanceRecapService.php` (baru) | Hitung & simpan rekap per kegiatan memakai jumlah pertemuan aktual |
| `app/Services/AttendanceEvaluationService.php` (baru) | Evaluasi kelulusan per kegiatan + alasan |
| `app/Http/Controllers/AbsensiSikController.php` | Pakai service; hapus `syncRekap` lokal; sesuaikan `riwayatSantri` |
| `app/Http/Controllers/PertemuanController.php` | Panggil recalc saat create/update/delete; hapus batas `max:16` (atau jadikan configurable) |
| `app/Http/Controllers/RekapKehadiranSikController.php` | Index agregat + `show` detail + export tanpa jam + laporan per kegiatan |
| `app/Http/Controllers/KelulusanSikController.php` | Validasi per kegiatan, alasan tidak lulus, opsi `Belum Lulus` |
| `app/Models/KelulusanSik.php` | `kegiatan_id`, relasi, scope per kegiatan; scope lama dipertahankan untuk data arsip |
| `app/Models/AnggotaPeriodeSik.php` | Tambah `kelulusanKegiatan()` (hasMany) di samping `kelulusan()` lama |
| `app/Models/Kegiatan.php` | Relasi `kelulusan()` |
| `config/sik.php` | Tambah `minimum_percentage` (default 87.5) dan batas pertemuan fleksibel |
| `resources/views/rekap-kehadiran-sik/index.blade.php` | Tabel utama **hanya 6 kolom** (No, Nama Santri, Nomor Induk, Kelompok, Total Pertemuan, Status) + tombol **Detail**. Jam datang/pulang, tanggal, hadir, izin, sakit, alpha **dihapus dari tabel utama** |
| `resources/views/rekap-kehadiran-sik/show.blade.php` (baru) | Detail lengkap: riwayat tanggal pertemuan, **jam datang/pulang bila tersedia**, status per pertemuan, total hadir/izin/sakit/alpha, total pertemuan, persentase — dari sumber yang sama dengan index |
| `resources/views/kelulusan-sik/*` | Form per kegiatan, tampilkan alasan |
| `resources/views/laporan-sik/index.blade.php` | Hasil evaluasi konsisten per kegiatan |
| `app/Http/Controllers/DashboardController.php` | Hapus hard-code 16, pakai service/agregat |
| `app/Http/Controllers/SertifikatSikController.php` | Gate: semua kegiatan SIK dalam periode `Lulus` + Tahsin `Lulus`; terbitkan **satu sertifikat per santri per periode** (§11.9) |

### 5.3 **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** — fingerprint (tidak masuk prioritas)

> Semua file di bawah **tidak dibuat, tidak diubah, tidak diuji, dan tidak diimplementasikan** pada prioritas ini. Tabel ini adalah catatan kebutuhan lama bila pihak SIK kemudian memutuskan penggunaan fingerprint.

| File | Perubahan (semua **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**) |
|---|---|
| `app/Http/Controllers/FingerprintImportController.php` (baru) | Upload, pratinjau, commit, riwayat, detail gagal |
| `app/Http/Controllers/FingerprintRecapController.php` (baru) | Rekap log scan + filter + export |
| `app/Services/FingerprintImportService.php` (baru) | Parse, normalisasi, pencocokan, dedupe, commit transaksi |
| `app/Models/FingerprintImportBatch.php` (baru) | Model batch |
| `app/Models/FingerprintImportRow.php` (baru) | Model staging |
| `app/Models/Fingerprint.php` | Relasi `importRows`, validasi unik |
| `app/Models/LogPenggunaanFingerprint.php` | Relasi batch, cast, kolom baru |
| `app/Http/Controllers/SantriController.php` | Validasi registrasi fingerprint (1 santri = 1 fingerprint) |
| `resources/views/fingerprint-import/*` (baru) | Form upload, pratinjau, riwayat, detail gagal |
| `resources/views/fingerprint-rekap/*` (baru) | Tabel rekap + filter + export |
| `resources/views/absensi-sik/index.blade.php` | Hapus teks "belum terhubung"; arahkan ke modul fingerprint |

### 5.4 Prioritas 3 — absensi dosen, data dosen, navigasi

| File | Perubahan |
|---|---|
| `app/Http/Controllers/RekapAbsensiDosenController.php` | `create`, `edit`, `update`, `destroy`; filter periode/instruktur konsisten |
| `resources/views/rekap-absensi-dosen/index.blade.php` | Ringkasan + tombol Input; form inline dipindah |
| `resources/views/rekap-absensi-dosen/create.blade.php` (baru) | Halaman input |
| `resources/views/rekap-absensi-dosen/edit.blade.php` (baru) | Halaman edit |
| `resources/views/rekap-absensi-dosen/show.blade.php` | Riwayat + ringkasan |
| `app/Http/Controllers/UserManagementController.php` | Guard `AbsensiDosen`; filter peran; link riwayat benar |
| `app/Models/InfrastrukturSik.php` | Relasi `absensiDosen` dipakai guard; `peran` opsional |
| `resources/views/users/index.blade.php` | Hapus tautan "Rekap Absensi Dosen" di **header**; tombol riwayat berlabel jelas & terfilter; "Buka Kelompok Santri" terfilter |
| `app/Http/Controllers/KelompokController.php` | Dukung filter `instruktur_id` |
| `resources/views/layouts/navigation.blade.php` | Menu Rekap Absensi Dosen & master data; **jangan daftarkan Menu Rekap Fingerprint — `DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** |
| `resources/views/jadwal/instruktur-index.blade.php` | Perbaiki relasi `kelas` atau hapus |
| `app/Http/Controllers/JadwalController.php`, `MataPelajaranController.php` | Daftarkan route atau hapus (keputusan) |

### 5.5 Model legacy (penyesuaian arsip)

| File | Perubahan |
|---|---|
| `app/Models/Absensi.php` | `$table = 'arsip_absensi'` |
| `app/Models/RekapKehadiran.php` | `$table = 'arsip_rekap_kehadiran'` |
| `app/Models/Jadwal.php` | `$table = 'arsip_jadwal'` |
| `app/Models/Kelulusan.php`, `Sertifikat.php` | `$table = 'arsip_*'` |

### 5.6 Prioritas 4 — integrasi nilai ujian (Tambahan Tahap 2)

| File | Perubahan |
|---|---|
| `app/Http/Controllers/NilaiController.php` | Ubah fokus ke nilai ujian: filter kegiatan, validasi kelayakan, simpan `jenis_nilai='Ujian'`, tampilkan grade |
| `app/Services/ExamEligibilityService.php` (baru) | Hitung kelayakan ujian: hadir / pertemuan `Terlaksana` ≥ 87,5% |
| `app/Services/GradeService.php` (baru) | Konversi angka → grade berdasarkan config (rentang menunggu persetujuan) |
| `app/Http/Controllers/KelulusanSikController.php` | Tampilkan nilai ujian, grade, kelayakan, status ikut ujian, status Tahsin |
| `app/Http/Controllers/SertifikatSikController.php` | Gate sertifikat: semua SIK periode ini **dan** Tahsin lulus; **satu sertifikat per santri per periode** |
| `database/migrations/..._add_anggota_periode_to_sertifikat_sik_table.php` (S1, baru) | Tambah `anggota_periode_sik_id` UNIQUE + backfill dari `kelulusan_sik`; tanpa menghapus data lama (§6) |
| `app/Models/Nilai.php` | `jenis_nilai`, `kegiatan_sik_id`, `grade`, `tanggal_ujian`; relasi `kegiatan`, scope `ujian()` |
| `config/sik.php` | `exam.minimum_attendance_percentage` (87,5), `exam.grade_scale`, `exam.minimum_passing_grade` |
| `resources/views/nilai/index.blade.php` | Judul & form nilai ujian; kolom kegiatan, angka, grade, kelayakan |
| `resources/views/nilai/transkrip.blade.php` | Pisahkan tampilan nilai harian vs nilai ujian |
| `resources/views/kelulusan-sik/index.blade.php` | Kolom nilai/grade/kelayakan/status ujian/Tahsin (tanpa halaman baru) |
| `resources/views/kelulusan-sik/edit.blade.php` | Panel informasi ujian + Tahsin + alasan |
| `resources/views/sertifikat-sik/create.blade.php` | Tampilkan gate dua kelulusan |
| `app/Models/KelulusanTahsin.php` (baru) | Model tabel Tahsin; relasi `anggotaPeriodeSik()`, `instruktur()`; **tidak** punya relasi `kegiatan()` |
| `app/Models/AnggotaPeriodeSik.php` | Tambah `kelulusanTahsin()` (**hasOne**, satu per anggota periode) di samping `kelulusan()` |
| `app/Models/InfrastrukturSik.php` | Tambah `kelulusanTahsin()` (hasMany) |
| `app/Http/Controllers/AnggotaPeriodeSikController.php` | Tambah `updateKelulusanTahsin()` dengan otorisasi kelompok instruktur; tetap pakai view yang ada |
| `resources/views/anggota-periode-sik/index.blade.php` | Kolom aksi "Status Tahsin" (pilih Lulus/Tidak Lulus) saat mode instruktur; read-only untuk admin |
| `routes/web.php` | Satu route PATCH untuk update status Tahsin (bukan halaman/menu baru) |

---

## 6. Daftar Migration yang Diperlukan

> Semua migration **belum dibuat** dan **belum dijalankan**. Urutan eksekusi aktif (web-first): **M1 → S1 → N1 → N2 → M6/M7 → M8**. Migration **M2–M5 berstatus `DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** dan tidak masuk urutan eksekusi saat ini. Migrasi absensi manual website **tidak memerlukan tabel baru** karena memakai `absensi_sik` yang sudah ada.

| # | Nama migration (usulan) | Isi | Alasan | Dampak / Risiko |
|---|---|---|---|---|
| M1 | `add_kegiatan_id_to_kelulusan_sik_table` | `+ kegiatan_id` nullable FK `kegiatan` (restrict); drop unique `anggota_periode_sik_id`; unique `(anggota_periode_sik_id, kegiatan_id)` | Kelulusan per kegiatan | Data lama tetap jalan dengan `kegiatan_id = NULL`; perlu backfill manual terpisah |
| M2 | `create_fingerprint_import_batches_table` | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** — tidak dibuat | Riwayat import (rencana lama) | Tidak dijalankan |
| M3 | `create_fingerprint_import_rows_table` | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** — tidak dibuat | Audit baris gagal (rencana lama) | Tidak dijalankan |
| M4 | `add_import_metadata_to_log_penggunaan_fingerprint_table` | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** — tidak ditambah kolom/index | Provenance + dedupe (rencana lama) | Tidak dijalankan |
| M5 | `add_unique_santri_id_to_fingerprints_table` | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** — tidak ditambah unique | Konsistensi relasi (rencana lama) | Tidak dijalankan |
| S1 | `add_anggota_periode_to_sertifikat_sik_table` (**diperlukan**, granularitas sertifikat sudah final) | + `anggota_periode_sik_id` nullable FK, **unique per anggota periode**; backfill dari `kelulusan_sik.anggota_periode_sik_id`; `kelulusan_sik_id` dipertahankan sebagai referensi historis | Sertifikat **satu kali per santri per periode SIK**, bukan per kegiatan | Data lama tidak dihapus; bila ada beberapa sertifikat untuk satu anggota, lakukan dedupe dengan persetujuan sebelum unique aktif |
| M6 | `add_audit_columns_to_absensi_dosen_table` (opsional) | `diperbarui_oleh`, `alasan_perubahan` | Jejak edit/hapus | Aman |
| M7 | `add_peran_to_infrastruktur_siks_table` (opsional) | `peran` default `Dosen` | Pemisahan dosen vs instruktur | Perlu keputusan bisnis |
| M8 | `archive_legacy_attendance_tables` | Rename 6 tabel legacy → `arsip_*` | Keputusan arsip | **Paling berisiko**; lakukan terakhir setelah kode bersih & backup |
| N1 | `extend_nilai_table_for_exam_scores` | + `jenis_nilai` enum default `Harian`, `kegiatan_sik_id` nullable FK, `grade` nullable, `tanggal_ujian` nullable; `mata_pelajaran_id` → nullable; unique `(santri_id, kegiatan_sik_id, jenis_nilai)` | Pakai ulang tabel `nilai`, bedakan ujian vs harian | Data lama tidak berubah; unique lama tetap ada; detail §11.6 |
| N2 | `create_kelulusan_tahsin_table` (**diperlukan**, keputusan sudah final) | `anggota_periode_sik_id` **UNIQUE** FK (1 baris per santri per periode SIK), `instruktur_sik_id` nullable FK, `status` enum nullable (`Lulus`/`Tidak Lulus`), `tanggal_penilaian`, `keterangan`, timestamps; **tanpa `kegiatan_id`** | Menyimpan status Tahsin secara manual oleh instruktur, terpisah dari `kelulusan_sik`, per santri per periode | Tabel baru, tidak mengubah data lama; tidak menambah menu/halaman; detail lengkap + relasi di §11.7 |

**Catatan migrasi:**
- Jangan menambahkan kolom `total_pertemuan` baru; gunakan `rekap_kehadiran_sik.total_pertemuan` yang sudah ada.
- M2–M5 **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK**: tidak ada migration fingerprint baru; jangan membuat tabel `rekap_fingerprint` baru; jangan mengubah struktur `fingerprints`/`log_penggunaan_fingerprint`.
- S1 tidak boleh menghapus baris `sertifikat_sik` lama; backfill `anggota_periode_sik_id` berasal dari relasi `kelulusan_sik`, bukan dari fingerprint.
- M1 tidak boleh menghapus data lama; baris `kegiatan_id = NULL` diperlakukan sebagai data historis.
- M8 tidak boleh memakai `DROP`; hanya `RENAME`.
- N1 tidak boleh mengubah/menghapus baris `nilai` lama; kolom baru bersifat nullable atau punya default `Harian`.
- N2 **tidak bergantung pada M1** karena tidak memiliki `kegiatan_id`; urutan setelah M1 hanya untuk kerapitan supaya gate sertifikat bisa dikerjakan setelah kelulusan SIK siap. `down()` hanya menghapus tabel baru dan tidak menyentuh `kelulusan_sik`, `nilai`, atau data lama.
- N2 **dilarang** menambahkan `kegiatan_id` atau FK ke `kegiatan` — status Tahsin berlaku untuk seluruh kegiatan dalam satu periode SIK.
- N2 **tidak** memakai / mengganti nama tabel legacy `kelulusan` (tetap diarsipkan sebagai `arsip_kelulusan` pada M8).

---

## 7. Rencana Implementasi per Fase

### Fase 0 — Fondasi web & pemblokir (1–2 hari)

**Cakupan:**
1. Audit/perbaikan **login & autentikasi** (termasuk `EnsurePasswordIsChanged` exempt route yang benar).
2. Audit/perbaikan **hak akses dan dashboard setiap role** (Admin, Instruktur, Dosen/Infrastruktur SIK, Ka-Lab Mutu, Santri).
3. Alur verifikasi bukti ketidakhadiran (`index`, `verify`, `reject`).
4. Rapikan route verifikasi + menu bukti.
5. Perbaiki halaman, tombol, route, validasi, dan fitur manajemen **santri, kelompok, periode SIK, instruktur/dosen** yang masih bermasalah.
6. Pastikan semua perbaikan di atas **tidak memakai fingerprint** dan dapat diuji mandiri.

**Kriteria selesai:**
- Akun baru bisa ganti password lalu masuk dashboard sesuai perannya.
- Setiap role melihat dashboard/menu yang sesuai hak aksesnya.
- Bukti `Menunggu` bisa menjadi `Diterima`/`Ditolak` dan status absensi menyesuaikan.
- Halaman/tombol/route/validasi inti tidak lagi error atau mengarah ke fitur fingerprint.

### Fase 1 — Fondasi aturan kehadiran (1–2 hari)

**Cakupan:**
1. `AttendanceRecapService` (hitung pertemuan aktual, tanpa 16 hard-code).
2. Refactor `AbsensiSikController` memakai service; hapus `syncRekap` duplikat.
3. `PertemuanController` memicu recalc saat create/update/delete.
4. `config/sik.php`: `minimum_percentage`, hapus ketergantungan 16.
5. Perbaiki dashboard agar memakai agregat yang sama.

**Kriteria selesai:**
- Menambah/menghapus pertemuan langsung memperbarui `rekap_kehadiran_sik`.
- Tidak ada lagi angka 16 yang memengaruhi perhitungan.

### Fase 2 — Rekap santri (1–2 hari)

**Cakupan:**
1. Index ringkas: **No, Nama Santri, Nomor Induk, Kelompok, Total Pertemuan, Status** + tombol **Detail** (§12.2).
2. Halaman/panel detail: riwayat tanggal pertemuan, jam datang & jam pulang bila tersedia, status Hadir/Izin/Sakit/Alpha per pertemuan, Total Hadir, Total Izin, Total Sakit, Total Alpha, Total Pertemuan, Persentase Kehadiran.
3. Jam datang/pulang, tanggal, hadir, izin, sakit, alpha **dihapus dari tabel utama dan CSV**; **tetap ditampilkan di detail**.
4. Filter kegiatan/kelompok + paginasi. Angka index dan detail berasal dari `AttendanceRecapService`/`rekap_kehadiran_sik` yang sama.

**Kriteria selesai:**
- Tabel utama hanya 6 kolom yang disetujui + tombol Detail; tidak ada kolom rincian di index.
- Satu baris per santri di index; rincian per kegiatan di detail.
- Detail menampilkan jam datang/pulang bila tersedia dan seluruh total + persentase.
- Export sesuai kolom tabel utama.

### Fase 3 — Evaluasi kelulusan per kegiatan (2–3 hari)

**Cakupan:**
1. M1 (`kegiatan_id`).
2. `AttendanceEvaluationService` + alasan.
3. Refactor `KelulusanSikController` & model.
4. Laporan Ka-Lab + dashboard memakai hasil yang sama.
5. Siapkan agregat kegiatan per periode untuk gate sertifikat; penerbitan final **satu sertifikat per santri per periode** menyusul di Fase 7 lewat S1.

**Kriteria selesai:**
- Kehadiran kegiatan A tidak memengaruhi B.
- Total pertemuan mengikuti jumlah aktual.
- Alasan tidak lulus tampil.

### Fase 4 — Fingerprint — **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**

**Status:** tidak dijadwalkan, tidak diimplementasikan, tidak menjadi penghambat fase lain.

**Cakupan yang ditunda:**
1. M2–M5 (semua migration fingerprint) — **tidak dibuat**.
2. `FingerprintImportService` + controller + view — **tidak dibuat**.
3. Rekap fingerprint + export + riwayat import — **tidak dibuat**.
4. Integrasi SDK/API, koneksi perangkat, dan asumsi format ekspor — **tidak dilakukan**.
5. Validasi registrasi fingerprint di Data Santri — **tidak diubah**.

**Kriteria saat ini (bukan kriteria rilis):**
- Tidak ada perubahan pada tabel/data `fingerprints` dan `log_penggunaan_fingerprint`.
- Tidak ada import, SDK/API, koneksi perangkat, atau migration fingerprint baru.
- Metode absensi akhir dicatat sebagai keputusan yang akan diambil setelah konsultasi dengan pihak SIK.

### Fase 5 — Absensi dosen & Data Dosen (2 hari)

**Cakupan:**
1. Halaman input/edit/hapus absensi dosen.
2. Guard `AbsensiDosen` pada hapus dosen.
3. Link riwayat + kelompok terfilter.
4. Navigasi "Rekap Absensi Dosen" tersedia; **tidak** menambah menu fingerprint.
5. Hapus tautan "Rekap Absensi Dosen" di header Data Dosen; tombol riwayat dosen diberi label jelas (§12.5).

**Kriteria selesai:**
- Hapus dosen dengan riwayat → pesan ramah, data tidak hilang.
- Rekap dosen tidak tercampur dengan rekap santri; fingerprint tidak dipakai di keduanya.

### Fase 6 — Arsip legacy & kerapian (1–2 hari)

**Cakupan:**
1. Hentikan seluruh referensi ke model legacy.
2. M8 rename ke `arsip_*`.
3. Putuskan route `jadwal`/`mata-pelajaran`.
4. Lengkapi navigasi admin.

**Kriteria selesai:**
- Tidak ada query aplikasi ke tabel legacy.
- Data arsip tetap bisa dibaca (read-only).

### Fase 7 — Nilai ujian SIK (Tambahan Tahap 2, 2–3 hari)

**Cakupan:**
1. N1 + `ExamEligibilityService` + `GradeService`.
2. Ubah halaman Nilai Instruktur menjadi input nilai ujian (tanpa menu/page baru).
3. N2: tabel `kelulusan_tahsin` + model + relasi + satu route PATCH (tanpa halaman/menu baru), dengan `anggota_periode_sik_id` **UNIQUE** dan **tanpa `kegiatan_id`**.
4. Instruktur memilih status Tahsin `Lulus`/`Tidak Lulus` inline di halaman `anggota-saya` yang sudah ada, **tanpa memilih kegiatan** (status berlaku untuk satu periode SIK).
5. Perkaya `kelulusan-sik/index` dan `edit` dengan nilai, grade, kelayakan, status ujian, dan status Tahsin (read-only, tampil sekali per santri per periode).
6. Gate sertifikat akhir: **semua Kelulusan SIK dalam satu periode lulus** **dan** Kelulusan Tahsin lulus, dengan alasan penolakan yang jelas.
7. S1: sesuaikan `sertifikat_sik` agar **satu sertifikat per santri per periode SIK** (bukan per kegiatan), tanpa menghapus data lama.
8. Nilai Instruktur: hilangkan blok hijau penanda, gunakan label "Kelompok" hanya pada tampilan yang tepat, tambah panel absensi dosen **read-only** (§12.4).
9. Data Dosen: hapus tautan rekap di header, perjelas tombol riwayat dosen (§12.5).

**Kriteria selesai:**
- Santri tidak memenuhi 87,5% tidak bisa diberi nilai ujian.
- Nilai ujian tampil dengan grade yang disetujui.
- Instruktur dapat memilih status Tahsin untuk santri di kelompoknya, tanpa halaman baru.
- Satu santri memiliki tepat satu baris Tahsin per periode SIK; periode berbeda → baris berbeda; tidak ada baris per kegiatan.
- Admin melihat semua status di halaman yang sudah ada (tanpa hak mengubah status Tahsin).
- Sertifikat hanya terbit bila semua SIK periode itu lulus **dan** Tahsin lulus; bila tidak, alasan ditampilkan.
- Satu santri hanya memiliki **satu sertifikat untuk satu periode SIK**, walaupun memiliki beberapa kegiatan lulus.
- Blok hijau Nilai Instruktur hilang; label Kelompok dan panel absensi dosen sesuai §12.4.
- Tautan rekap di header Data Dosen hilang; tombol riwayat dosen jelas dan tidak tertukar dengan santri.
- Data nilai lama dan data legacy tetap utuh.

### Fase 8 — Perbaikan menyeluruh halaman/tombol/route/validasi (berjalan paralel, 1–2 hari)

**Cakupan:**
1. Inventarisasi halaman, tombol, route, dan validasi yang masih error/terputus di seluruh modul web.
2. Perbaiki tanpa menambah halaman/menu baru kecuali sudah diatur di §12.4/§12.5.
3. Uji ulang alur web-first: login → dashboard per role → santri/kelompok/periode → absensi manual → rekap → nilai → kelulusan → sertifikat → laporan Ka-Lab.
4. Pastikan tidak ada perbaikan yang menambah ketergantungan fingerprint.

**Kriteria selesai:**
- Tidak ada tombol/route yang mengarah ke fitur fingerprint yang ditunda.
- Validasi form menghasilkan pesan yang jelas dan tidak menyimpan data ganda.
- Seluruh alur di atas dapat dijalankan tanpa perangkat fingerprint.

---

## 8. Risiko dan Cara Mencegah Kehilangan Data

### 8.1 Risiko utama

| # | Risiko | Dampak | Pencegahan |
|---|---|---|---|
| R1 | Migration unique gagal karena duplikat fingerprint | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** | Tidak ada migration fingerprint yang dijalankan; data existing dipertahankan |
| R2 | Rename tabel legacy memutus FK | Aplikasi error / data tidak terbaca | Lakukan terakhir; backup penuh; uji di salinan DB; rename dalam satu migration |
| R3 | `kelulusan_sik` diubah tanpa backfill | Data lama tidak punya kegiatan | `kegiatan_id` nullable; data lama dibiarkan; backfill terpisah setelah keputusan |
| R4 | Sertifikat ganda per santri per periode | Kebingungan administratif | Aturan sudah final: **satu sertifikat per `anggota_periode_sik_id`**; S1 menambah unique + backfill tanpa menghapus data |
| R5 | Rekap cache stale setelah perubahan aturan | Angka tidak cocok | Artisan command `rekap:rebuild` (baru) + jalankan setelah migration |
| R6 | Import fingerprint dobel | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** | Import tidak dibuat; tidak ada log ganda baru |
| R7 | Hapus dosen memicu FK restrict | Error 500 | Cek `absensi_dosen` di guard; tampilkan opsi nonaktifkan |
| R8 | `nilai` ikut terarsip | Modul nilai rusak | `mata_pelajaran` & `nilai` **tidak** diarsipkan |
| R9 | Commit import setengah jalan | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** | Tidak ada proses import yang dijalankan |
| R10 | File import besar menghabiskan memori | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** | Format/batas file tidak diasumsikan; tidak ada upload diimplementasikan |
| R11 | Perubahan makna `nilai` | Data nilai lama salah terbaca sebagai ujian | `jenis_nilai` default `Harian`; tidak ada `UPDATE` massal; transkrip memisahkan harian vs ujian |
| R12 | Nilai ujian dobel per kegiatan | Grade ganda | Unique `(santri_id, kegiatan_sik_id, jenis_nilai)` + `updateOrCreate` |
| R13 | Grade ditetapkan sebelum disetujui | Salah ketetapan kelulusan | Rentang & ambang hanya diisi di `config/sik.php` setelah persetujuan (Lampiran A poin 8–9) |
| R14 | Sertifikat terbit tanpa Tahsin lulus / tanpa semua SIK lulus | Melanggar aturan gate | Gate di `SertifikatSikController::eligibleGraduations()`: semua SIK periode `Lulus` + Tahsin `Lulus`; satu sertifikat per anggota periode (§11.9) |
| R15 | Instruktur mengubah status Tahsin santri di luar kelompoknya | Penyalahgunaan data | Otorisasi `kelompok.instruktur_id === instruktur.id` di server pada route PATCH (§11.7) |
| R16 | Status Tahsin belum diisi | Sertifikat tertahan (sesuai aturan) | Tampilkan `Belum Ditentukan` (NULL) di admin; gate mensyaratkan tepat `Lulus`, bukan sekadar "bukan Tidak Lulus"; tampilkan alasan penolakan |
| R17 | Status Tahsin keliru dibuat per kegiatan | Duplikat status dalam satu periode, gate sertifikat tidak konsisten | `anggota_periode_sik_id` **UNIQUE**; **tanpa** `kegiatan_id` dan tanpa relasi ke `kegiatan`; `updateOrCreate` berdasarkan anggota periode |
| R18 | Fingerprint dijadikan penghambat web | Pengembangan berhenti menunggu perangkat/keputusan SIK | **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK**; absensi manual website dapat dikembangkan dan diuji mandiri |
| R19 | Dua sistem pencatatan absensi santri | Rekap ganda/bertentangan | Satu sumber `absensi_sik`; Rabu Mengaji memakai `kegiatan`+`pertemuan`+`absensi_sik`; fingerprint tidak menulis ke absensi |

### 8.2 Aturan keselamatan data (wajib dipatuhi saat implementasi nanti)

1. **Backup penuh** `sik_db` sebelum migration apa pun.
2. Tidak ada `DROP TABLE`, `TRUNCATE`, atau `DELETE` massal dalam rencana ini.
3. Rename legacy hanya setelah verifikasi bahwa aplikasi tidak lagi memakainya.
4. Semua migration harus punya `down()` yang aman dan tidak menghapus data.
5. Import berjalan dalam transaksi; baris gagal disimpan, bukan dibuang.
6. Data produksi tidak boleh dipakai untuk uji import sungguhan.
7. Setiap fase harus lulus test sebelum lanjut.
8. Seluruh pekerjaan fingerprint berstatus **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**: tidak ada import/SDK/API/koneksi perangkat/migration baru serta tidak ada perubahan data/struktur fingerprint.
9. Metode absensi akhir akan diputuskan setelah konsultasi dengan pihak SIK; sampai saat itu website memakai absensi manual (§3.3).

### 8.3 Query pra-migration (read-only, dijalankan saat DB tersedia)

> Dua query fingerprint di bawah **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** dan **tidak dijalankan** pada prioritas ini. Query lain tetap dipakai sesuai fase aktif.

```sql
-- Duplikat fingerprint per santri
SELECT santri_id, COUNT(*) FROM fingerprints GROUP BY santri_id HAVING COUNT(*) > 1;

-- Duplikat log scan
SELECT fingerprint_id, waktu_scan, COUNT(*) FROM log_penggunaan_fingerprint
GROUP BY fingerprint_id, waktu_scan HAVING COUNT(*) > 1;

-- Absensi dosen tanpa profil
SELECT ad.id FROM absensi_dosen ad
LEFT JOIN infrastruktur_siks i ON i.id = ad.infrastruktur_sik_id
WHERE i.id IS NULL;

-- Rekap vs absensi
SELECT r.anggota_periode_sik_id, r.kegiatan_id, r.jumlah_hadir,
       COUNT(a.id) AS hitung_ulang
FROM rekap_kehadiran_sik r
LEFT JOIN absensi_sik a ON a.anggota_periode_sik_id = r.anggota_periode_sik_id
LEFT JOIN pertemuan p ON p.id = a.pertemuan_id AND p.kegiatan_id = r.kegiatan_id
GROUP BY r.id;

-- Jumlah baris tabel legacy
SELECT 'absensi', COUNT(*) FROM absensi
UNION ALL SELECT 'rekap_kehadiran', COUNT(*) FROM rekap_kehadiran
UNION ALL SELECT 'jadwal', COUNT(*) FROM jadwal
UNION ALL SELECT 'laporan', COUNT(*) FROM laporan;
```

### 8.4 Aturan pencocokan fingerprint — **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**

> Ini adalah arsikan usulan. Tidak ada pencocokan, import, atau konversi yang dikerjakan sekarang. Metode absensi akhir akan diputuskan setelah konsultasi dengan pihak SIK.

| Langkah | Aturan | Jika gagal |
|---|---|---|
| 1 | `fingerprints.fingerprint_id` == UID alat | lanjut |
| 2 | `santris.nomor_induk` == UID/NIK | lanjut |
| 3 | Kolom NIK/NIM eksplisit di file | lanjut |
| 4 | Lebih dari satu kandidat | **Gagal (ambigu)** |
| 5 | Tidak ada kandidat | **Gagal (tidak dikenal)** |

**Keputusan yang masih terbuka:** apakah scan `Hadir` otomatis membuat `absensi_sik` (metode `Fingerprint`) atau hanya menjadi bukti yang harus diverifikasi instruktur. Rekomendasi: **tahap 1 hanya log**, konversi otomatis ditunda sampai aturan disetujui.

---

## 9. Checklist Pengujian per Fase

### Fase 0
- [ ] Login/autentikasi berhasil untuk setiap role yang diizinkan.
- [ ] Dashboard setiap role menampilkan data sesuai hak akses.
- [ ] Halaman/tombol/route/validasi santri, kelompok, periode, instruktur/dosen tidak error.
- [ ] Tidak ada tombol/menu yang mengarah ke fitur fingerprint yang ditunda.
- [ ] User `must_change_password` dapat POST ganti password dan diarahkan ke dashboard.
- [ ] User `must_change_password` tetap diblokir dari halaman lain.
- [ ] Bukti `Menunggu` → `Diterima` mengubah status absensi.
- [ ] Bukti `Menunggu` → `Ditolak` tidak mengubah status.
- [ ] Instruktur tidak bisa memverifikasi bukti di luar kelompoknya.
- [ ] Santri tidak bisa memverifikasi buktinya sendiri.

### Fase 1
- [ ] Tambah pertemuan → `total_pertemuan` rekap naik.
- [ ] Hapus pertemuan → rekap turun.
- [ ] Pertemuan `Dibatalkan` tidak dihitung.
- [ ] Tidak ada perhitungan yang bergantung pada angka 16.
- [ ] Absensi baru langsung memperbarui rekap.
- [ ] Rekap bisa dibangun ulang dari nol dan hasilnya sama.

### Fase 2
- [ ] Index hanya berisi 6 kolom: No, Nama Santri, Nomor Induk, Kelompok, Total Pertemuan, Status + tombol Detail.
- [ ] Tidak ada kolom Jam Datang, Jam Pulang, Tanggal, Hadir, Izin, Sakit, Alpha di **tabel utama** (dan CSV mengikuti tabel utama).
- [ ] Tombol Detail membuka detail/rincian per santri.
- [ ] Detail menampilkan riwayat tanggal pertemuan, jam datang/pulang bila tersedia, status per pertemuan, Total Hadir, Total Izin, Total Sakit, Total Alpha, Total Pertemuan, dan Persentase.
- [ ] Angka di index dan detail sama (satu sumber: `AttendanceRecapService`/`rekap_kehadiran_sik`).
- [ ] Filter kegiatan/kelompok dan paginasi bekerja.
- [ ] Santri tanpa absensi tampil sebagai `Tidak Lengkap`/0%, bukan error.
- [ ] Export CSV aman dari formula injection.

### Fase 3
- [ ] Hadir 14 di kegiatan A + 0 di kegiatan B → B tidak lulus.
- [ ] Total pertemuan 10 dengan 9 hadir dihitung 90%, bukan 9/16.
- [ ] Alasan tidak lulus tampil dan tersimpan.
- [ ] Nilai harian tidak memengaruhi status SIK (nilai ujian baru masuk di Fase 7 setelah aturan grade disetujui).
- [ ] Laporan Ka-Lab = hasil service (konsisten).
- [ ] Sertifikat tidak bisa terbit untuk kegiatan yang belum memenuhi.
- [ ] Data kelulusan lama (`kegiatan_id = NULL`) tidak rusak.

### Fase 4 — **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**
- [ ] Tidak dibuat: import, pratinjau, commit, riwayat batch, dan rekap fingerprint.
- [ ] Tidak dibuat: SDK/API, koneksi perangkat, dan asumsi format ekspor.
- [ ] Tidak dibuat dan tidak dijalankan: M2–M5 atau migration fingerprint lainnya.
- [ ] Tidak ada perubahan pada data/struktur `fingerprints` dan `log_penggunaan_fingerprint`.
- [ ] Tidak ada route/menu fingerprint yang didaftarkan.
- [ ] Checklist lama di bawah hanya dipakai bila pihak SIK menyetujui dan prioritas dibuka kembali.

<details>
<summary>Arsip checklist lama (tidak aktif)</summary>

- [ ] Upload file valid → pratinjau menampilkan total/valid/duplikat/gagal.
- [ ] Baris dengan UID tidak dikenal → gagal + alasan.
- [ ] Baris ambigu (2 santri) → gagal.
- [ ] Duplikat `(fingerprint_id, waktu_scan)` tidak masuk dua kali.
- [ ] Upload file yang sama → peringatan hash.
- [ ] Commit gagal di tengah → tidak ada baris setengah masuk.
- [ ] Riwayat import menampilkan siapa, kapan, jumlah, dan detail gagal.
- [ ] Import tidak menulis `absensi_sik`.

</details>

### Fase 5
- [ ] Input absensi dosen dari halaman khusus.
- [ ] Edit/hapus absensi dosen berfungsi + jejak audit.
- [ ] Duplikat dosen+tanggal ditolak dengan pesan jelas.
- [ ] Hapus dosen dengan riwayat → pesan ramah, data tetap.
- [ ] Link "Detail riwayat" membuka dosen yang benar.
- [ ] "Buka Kelompok Santri" hanya menampilkan kelompok dosen tersebut.
- [ ] Rekap dosen tidak berbagi query/tabel dengan rekap santri; fingerprint tidak dipakai.
- [ ] Tautan "Rekap Absensi Dosen" tidak tampil di header Data Dosen; tombol riwayat berlabel jelas.

### Fase 6
- [ ] Tidak ada query ke `absensi`, `rekap_kehadiran`, `jadwal`, `laporan` legacy.
- [ ] Tabel `arsip_*` ada dan datanya sama dengan sebelum rename.
- [ ] `nilai` dan `mata_pelajaran` tetap berfungsi.
- [ ] Navigasi admin memuat Kegiatan, Pertemuan, Periode, Kelompok, Absensi Dosen; **tidak** memuat Fingerprint.
- [ ] Tidak ada route/view orphan.

### Fase 7 — Nilai ujian
- [ ] Tidak ada menu atau halaman baru yang dibuat.
- [ ] Halaman Nilai Instruktur hanya menampilkan/input nilai ujian; data harian tetap tersimpan.
- [ ] Santri dengan kehadiran < 87,5% dari pertemuan `Terlaksana` ditolak saat input nilai ujian.
- [ ] Pertemuan `Dibatalkan`/`Terjadwal` tidak dihitung dalam kelayakan.
- [ ] Grade sesuai rentang yang disetujui (belum ditetapkan).
- [ ] Nilai ujian per santri per kegiatan tidak bisa dobel.
- [ ] Admin melihat: nama, kegiatan ujian, nilai angka, grade, kelayakan, status ikut ujian, status SIK.
- [ ] Status Tahsin tampil terpisah dari status SIK.
- [ ] Sertifikat ditolak bila salah satu kelulusan belum lulus.
- [ ] Transkrip santri menampilkan nilai harian dan nilai ujian secara terpisah.
- [ ] Semua baris `nilai` lama masih terbaca.
- [ ] Tabel `kelulusan_tahsin` terpisah dari `kelulusan_sik` (tidak ada kolom `status_tahsin` di `kelulusan_sik`).
- [ ] Instruktur dapat memilih `Lulus`/`Tidak Lulus` untuk santri di kelompoknya pada halaman `anggota-saya`.
- [ ] Instruktur ditolak saat mengubah status Tahsin santri di luar kelompoknya.
- [ ] Satu santri per periode hanya memiliki satu baris status Tahsin (update, bukan duplikat).
- [ ] Tabel `kelulusan_tahsin` **tidak memiliki** kolom `kegiatan_id` dan tidak ada relasi ke `kegiatan`/`pertemuan`/`absensi_sik`.
- [ ] Satu santri dengan dua periode SIK memiliki dua baris Tahsin yang statusnya dapat berbeda.
- [ ] Instruktur tidak memilih/mengaitkan kegiatan saat mengisi status Tahsin; status berlaku untuk seluruh kegiatan dalam periode.
- [ ] Admin melihat status Tahsin di `kelulusan-sik/index` bersifat read-only, tampil sekali per santri per periode.
- [ ] Status Tahsin yang belum diisi tampil sebagai `Belum Ditentukan`, bukan `Lulus`.
- [ ] Sertifikat ditolak bila status Tahsin NULL/`Belum Ditentukan`, dengan alasan "Kelulusan Pembinaan Tahsin belum ditentukan".
- [ ] Sertifikat ditolak bila Tahsin `Tidak Lulus` walaupun SIK `Lulus`, dengan alasan "Kelulusan Pembinaan Tahsin belum lulus".
- [ ] Sertifikat ditolak bila SIK belum `Lulus` walaupun Tahsin `Lulus`, dengan alasan "Kelulusan SIK belum lulus".
- [ ] Sertifikat terbit hanya bila **semua SIK dalam periode** `Lulus` **dan** Tahsin `Lulus`.
- [ ] Satu santri per periode hanya memiliki satu sertifikat (bukan satu per kegiatan).

### Fase 8 — Perbaikan menyeluruh
- [ ] Seluruh tombol/route/validasi yang rusak sudah diinventarisasi dan diperbaiki.
- [ ] Alur web-first lengkap dapat diuji tanpa fingerprint.
- [ ] Tidak ada redirect/tautan ke halaman fingerprint yang ditunda.
- [ ] Tidak ada perubahan data/struktur fingerprint.

---

## 10. Rekomendasi Urutan Implementasi

| Urutan | Fase | Estimasi | Ketergantungan | Risiko |
|---|---|---|---|---|
| 1 | Fase 0 — fondasi web & pemblokir | 1–2 hari | — | Rendah |
| 2 | Fase 1 — fondasi rekap (satu sumber) | 1–2 hari | Fase 0 | Sedang |
| 3 | Fase 2 — rekap santri + perbaikan Nilai Instruktur/Data Dosen | 1–2 hari | Fase 1 | Rendah |
| 4 | Fase 3 — kelulusan per kegiatan + Rabu Mengaji + Ka-Lab | 2–3 hari | Fase 1, 2 | Sedang |
| 5 | Fase 5 — absensi dosen & Data Dosen | 2 hari | bisa paralel | Rendah |
| 6 | Fase 7 — nilai ujian, Tahsin, S1 & sertifikat per periode | 3–4 hari | Fase 1, 3; keputusan grade | Sedang |
| 7 | Fase 8 — perbaikan menyeluruh halaman/tombol/route/validasi | 1–2 hari | bisa paralel | Rendah |
| 8 | Fase 6 — arsip legacy & kerapian | 1–2 hari | semua fase aktif | **Tinggi** (rename tabel) |
| — | Fase 4 — fingerprint | **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | **tidak dijadwalkan** | — |

**Total estimasi (tanpa fingerprint):** 12–19 hari kerja.

**Daftar fitur website yang menjadi prioritas (sesuai keputusan terbaru):**
1. Login dan autentikasi; hak akses dan dashboard setiap role.
2. Manajemen data santri; kelompok dan periode SIK; instruktur/dosen.
3. Absensi santri melalui website (manual, §3.3) dan rekap kehadiran santri.
4. Tabel/data Rabu Mengaji.
5. Nilai ujian dan grade; Kelulusan SIK per kegiatan.
6. Kelulusan Pembinaan Tahsin per santri per periode.
7. Sertifikat **satu kali per santri per periode SIK**.
8. Laporan Ka-Lab Mutu.
9. Perbaikan seluruh halaman, tombol, route, validasi, dan fitur yang masih bermasalah.

**Aturan rilis:**
- Fase 0–2 boleh dirilis lebih dulu sebagai perbaikan operasional; alur absensi manual website (§3.3) dapat dikembangkan dan diuji **mandiri**.
- Granularitas sertifikat **sudah final** (satu per santri per periode), sehingga Fase 3 tidak lagi menunggu keputusan sertifikat.
- Fase 6 dilakukan paling akhir dari fase struktural dan hanya setelah backup + uji di salinan DB.
- Fase 7 hanya menunggu persetujuan rentang grade dan ambang nilai lulus. Keputusan Tahsin — manual oleh instruktur, satu baris per santri per periode SIK (bukan per kegiatan), dan gate sertifikat — **sudah final** (§11.7).
- **Fase 4 berstatus `DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`.** Tidak ada import/SDK/API/koneksi perangkat/migration fingerprint, tidak ada asumsi format ekspor, dan tidak ada perubahan data/struktur fingerprint. Fase ini **tidak boleh** menjadi penghambat rilis fase lain.

---

## 11. TAMBAHAN: INTEGRASI NILAI UJIAN SIKVERSE

Bagian ini merupakan rincian dari permintaan tambahan. **Tidak ada halaman atau menu baru** yang dibuat: seluruh fungsi diimplementasikan dengan memakai ulang halaman Nilai Instruktur, halaman Kelola Status Kelulusan, dan halaman detail yang sudah tersedia.

### 11.1 Prinsip desain

1. Halaman **Nilai Instruktur** (`nilai/index`) diubah fungsinya menjadi **input & daftar nilai ujian SIK**. Tidak dibuat halaman nilai harian baru.
2. Modul yang dipakai adalah modul nilai yang sudah ada: tabel `nilai`, model `Nilai`, `NilaiController`, route `nilai.*` dan `nilai-saya`, serta view `resources/views/nilai/*`.
3. Nilai lama tetap dipertahankan. Kolom baru bersifat aman (nullable / default), bukan mengganti arti data yang sudah ada.
4. Status **kelayakan mengikuti ujian** dan **status mengikuti ujian** adalah nilai **turunan** (dihitung/ditampilkan), bukan kolom status baru.
5. Grade belum ditetapkan secara sepihak; rentang di §11.5 adalah **usulan yang menunggu persetujuan**.

### 11.2 File yang akan digunakan kembali

| Jenis | File / entitas | Kondisi saat ini | Perlakuan |
|---|---|---|---|
| Tabel | `nilai` | `santri_id`, `mata_pelajaran_id`, `infrastruktur_sik_id`, `semester`, `tahun_ajaran`, `nilai`, `keterangan`; unique `(santri_id, mata_pelajaran_id, semester, tahun_ajaran)` | Dipakai ulang, dikembangkan via N1 (§11.6) |
| Model | `app/Models/Nilai.php` | `$fillable` berisi 7 kolom lama; relasi `santri`, `mataPelajaran`, `infrastrukturSik` | Tambah field baru, relasi `kegiatan`, scope `ujian()` |
| Controller | `app/Http/Controllers/NilaiController.php` | `index`, `store`, `update`, `destroy`, `transcript`; bergantung pada `MataPelajaran` aktif & kepemilikan kelompok instruktur | Fokus ke nilai ujian; tetap pakai gate kelompok instruktur |
| Route | `routes/web.php` | `Route::resource('nilai', NilaiController::class)` dan `GET nilai-saya` | **Tidak ditambah route baru**; cukup memakai yang ada |
| View instruktur | `resources/views/nilai/index.blade.php` | Judul "Input dan Pengelolaan Nilai", per santri + form nilai per mapel | Diubah menjadi daftar & form nilai ujian |
| View santri | `resources/views/nilai/transkrip.blade.php` | "Nilai, Transkrip, dan Kelulusan" | Dipisah antara nilai harian dan nilai ujian |
| View admin | `resources/views/kelulusan-sik/index.blade.php`, `edit.blade.php` | Tabel kelulusan per periode + form edit | Diperluas dengan kolom/panel nilai ujian |
| View sertifikat | `resources/views/sertifikat-sik/create.blade.php` | Eligibilitas dari `eligibleGraduations()` | Tambah gate Tahsin lulus |
| Config | `config/sik.php` | Baru punya `attendance.meeting_count = 16`, `minimum_present = 14` | Tambah `exam.*` (§11.5, §11.6) |
| Navigasi | `resources/views/layouts/navigation.blade.php` | Menu Nilai Instruktur sudah ada | **Tidak diubah** (sesuai larangan menambah menu) |

### 11.3 Perubahan fungsi halaman Nilai Instruktur

**Halaman yang dipakai:** `resources/views/nilai/index.blade.php` via route `nilai.index` (milik peran Instruktur SIK).

**Fungsi baru:**
1. **Daftar santri yang mengikuti ujian** — santri pada kelompok aktif binaan instruktur (gate yang sudah ada di `ensureInstructorCanGrade` dipertahankan), difilter menurut **kegiatan/periode ujian** yang dipilih.
2. **Badge kelayakan** di setiap baris: `Memenuhi` bila kehadiran ≥ 87,5% dari pertemuan `Terlaksana` pada kegiatan tersebut; `Tidak Memenuhi` bila di bawahnya.
3. **Badge status mengikuti ujian**: `Sudah Dinilai` bila sudah ada baris `nilai` dengan `jenis_nilai = 'Ujian'` untuk santri + kegiatan itu; `Belum Dinilai` bila belum.
4. **Form input/update nilai ujian** berisi:
   - `nilai` (angka, validasi 0–100),
   - `kegiatan_sik_id` (kegiatan ujian yang dinilai),
   - `tanggal_ujian`,
   - `keterangan` (opsional).
   - Field `jenis_nilai` diisi otomatis `'Ujian'` oleh controller, **bukan** dipilih bebas oleh pengguna.
5. **Penolakan input** bila santri belum memenuhi kelayakan (dicegah di server, bukan hanya disembunyikan di UI). Pesan: "Santri belum memenuhi syarat kehadiran 87,5% untuk mengikuti ujian."
6. **Tampilan angka + grade** setelah tersimpan; grade dihitung melalui `GradeService` (config), disimpan ke kolom `grade` sebagai hasil turunan.
7. **Konteks kegiatan/periode ujian** ditampilkan di header/footer halaman (nama kegiatan + nama periode + jumlah pertemuan `Terlaksana`).

**Yang dihapus/diubah dari fungsi lama:**
- Form nilai per `mata_pelajaran` tidak lagi ditampilkan di halaman ini. Data nilai harian **tetap tersimpan** dan tidak dihapus; hanya dipindah tampilannya ke `nilai/transkrip.blade.php` sebagai riwayat terpisah.
- Ketergantungan pada `MataPelajaran` aktif di `index()`/`store()` diganti dengan ketergantungan pada `Kegiatan` ujian (maka `mata_pelajaran_id` perlu nullable — N1).

### 11.4 Perubahan tampilan Admin

**Halaman yang dipakai:** `resources/views/kelulusan-sik/index.blade.php` (Kelola Status Kelulusan) dan `resources/views/kelulusan-sik/edit.blade.php` (detail yang sudah ada). **Tidak ada halaman admin baru.**

**Kolom yang ditambahkan di `kelulusan-sik/index`:**

| Kolom | Sumber |
|---|---|
| Nama santri | `anggotaPeriodeSik.santri.nama` (sudah ada) |
| Kegiatan ujian | `nilai.kegiatan` (jenis_nilai = Ujian) / `kelulusan_sik.kegiatan` |
| Nilai angka | `nilai.nilai` |
| Grade | `nilai.grade` |
| Status kelayakan mengikuti ujian | `ExamEligibilityService` (turunan) |
| Status mengikuti ujian | ada/tidaknya baris `nilai` Ujian (turunan) |
| Status kelulusan SIK | `kelulusan_sik.status` (sudah ada) |
| Status Kelulusan Tahsin | lihat §11.7 (ditampilkan terpisah) |

**Panel yang ditambahkan di `kelulusan-sik/edit`:**
- Ringkasan ujian: kegiatan, tanggal ujian, nilai angka, grade.
- Ringkasan kehadiran: jumlah pertemuan `Terlaksana`, jumlah hadir, persentase, dan **alasan** bila tidak memenuhi.
- Dua status kelulusan terpisah: **Kelulusan SIK** dan **Kelulusan Pembinaan Tahsin**.
- Indikator gate sertifikat: aktif hanya bila keduanya `Lulus`.

Catatan performa: hindari N+1 dengan `with()`/`withCount()` pada `KelulusanSikController` saat menambah kolom turunan ini.

### 11.5 Hubungan nilai ujian dengan kelulusan

```
absensi_sik (Hadir) + pertemuan (Terlaksana)
        │  dihitung per kegiatan
        ▼
ExamEligibilityService  →  hadir / total Terlaksana >= 87,5%
        │                       │
        │ Memenuhi              │ Tidak Memenuhi
        ▼                       ▼
boleh input nilai ujian     ditolak (tidak berhak ujian)
        │
        ▼
nilai (jenis_nilai = 'Ujian', kegiatan_sik_id, grade)
        │
        ▼
GradeService → grade (usulan: A/B/C/D/E)
        │
        ▼
Evaluasi hasil ujian SIK  ──►  kelulusan_sik (per kegiatan)
        │                              │
        │                              ├── status SIK: Lulus / Tidak Lulus / Belum Lulus
        │                              │
        ▼                              ▼
kelulusan_tahsin (Tahsin)      Sertifikat akhir DITERBITKAN
   (entitas terpisah)          hanya bila SEMUA SIK periode Lulus DAN Tahsin Lulus
   1 baris per anggota         SATU sertifikat per santri per periode SIK
   periode; TANPA kegiatan     (bukan per kegiatan); SIK: per kegiatan, sertifikat: per periode
```

**Aturan yang dipertahankan (sesuai permintaan):**
1. Kehadiran **minimal 87,5% dari pertemuan `Terlaksana`** adalah syarat **mutlak mengikuti ujian**. Pertemuan `Dibatalkan`/`Terjadwal` tidak dihitung.
2. Santri di bawah 87,5% **tidak berhak mengikuti ujian**, sehingga tidak boleh memperoleh nilai ujian.
3. **Nilai ujian** (`jenis_nilai = 'Ujian'`) menjadi dasar evaluasi hasil ujian SIK.
4. **Nilai harian** (`jenis_nilai = 'Harian'`) **tidak menjadi syarat kelulusan**.
5. **Kelulusan SIK** dan **Kelulusan Pembinaan Tahsin** tetap dua status yang terpisah; tidak digabung. Status SIK **per kegiatan**, status Tahsin **satu per santri per periode SIK** (tanpa `kegiatan_id`).
6. **Sertifikat akhir** diterbitkan **satu kali per santri per periode SIK**, hanya bila **semua** `kelulusan_sik` pada periode itu berstatus `Lulus` **dan** `kelulusan_tahsin` berstatus `Lulus`. Bila ada SIK belum lulus atau Tahsin belum/NULL/`Tidak Lulus`, penerbitan ditolak dengan alasan jelas (§11.9).

**Status turunan (tidak perlu kolom baru):**

| Status | Definisi |
|---|---|
| Kelayakan mengikuti ujian | `COUNT(absensi_sik.status = 'Hadir' AND pertemuan.status = 'Terlaksana') / COUNT(pertemuan.status = 'Terlaksana') >= 0.875` untuk santri + kegiatan |
| Mengikuti ujian | `EXISTS(nilai WHERE jenis_nilai = 'Ujian' AND santri_id = ? AND kegiatan_sik_id = ?)` |
| Grade ujian | Hasil `GradeService::convert(nilai)` |

**Usulan rentang grade — BELUM DITETAPKAN, menunggu persetujuan:**

| Grade | Rentang nilai |
|---|---|
| A | 90 – 100 |
| B | 80 – < 90 |
| C | 70 – < 80 |
| D | 60 – < 70 |
| E | < 60 |

Usulan ambang lulus: **minimal C (70)**. Pemeriksaan sistem: **belum ditemukan** aturan konversi angka→grade maupun konversi lulus di kode/config (`grep` tidak menemukan `grade`, `predikat`, atau atarman ujian). Seluruh nilai di atas adalah **usulan**, bukan ketetapan; diimplementasikan di `config/sik.php` setelah disetujui.

### 11.6 Kebutuhan perubahan database — migration N1

Tabel `nilai` **tidak dibuat baru**, cukup dikembangkan. Migration usulan: `extend_nilai_table_for_exam_scores` (N1 di §6).

| Perubahan | Detail | Alasan | Dampak & mitigasi |
|---|---|---|---|
| + `jenis_nilai` | `enum('Harian','Ujian')` **default `'Harian'`** | Membedakan nilai ujian dari data lama tanpa mengubah baris existing | Semua baris lama otomatis terbaca sebagai `Harian`; tidak ada data yang berubah arti |
| + `kegiatan_sik_id` | `foreignId` **nullable**, FK ke `kegiatan`, `restrictOnDelete` | Menandai kegiatan/periode ujian yang dinilai | Nullable agar baris lama tetap valid; data lama tidak di-backfill otomatis |
| + `grade` | `string` **nullable** | Menyimpan hasil konversi | Bisa diisi ulang dari `nilai` bila aturan grade berubah |
| + `tanggal_ujian` | `date` **nullable** | Menampilkan tanggal ujian di admin & transkrip | Tidak wajib pada data lama |
| `mata_pelajaran_id` | diubah menjadi **nullable** | Nilai ujian tidak terikat `mata_pelajaran` (legacy) | Unique lama `(santri_id, mata_pelajaran_id, semester, tahun_ajaran)` dipertahankan; baris dengan `mata_pelajaran_id = NULL` tidak bentrok karena MySQL mengizinkan NULL berulang pada unique |
| + unique `(santri_id, kegiatan_sik_id, jenis_nilai)` | index baru | Mencegah nilai ujian dobel per santri per kegiatan | Pra-cek duplikat belum perlu (data ujian belum ada); `down()` hanya menghapus index & kolom baru, bukan data |

**Jaminan keamanan data:**
- Tidak ada `DROP TABLE`, `TRUNCATE`, atau `UPDATE` massal pada baris lama.
- `down()` membalik perubahan skema tanpa menghapus baris `nilai`.
- `mata_pelajaran` dan `nilai` **tidak** ikut diarsipkan pada M8 (lihat §4.4).
- Migration N1 dijalankan **setelah** M1 (kelulusan per kegiatan) agar `kegiatan_sik_id` konsisten dengan `kelulusan_sik.kegiatan_id`.

### 11.7 Kelulusan Pembinaan Tahsin — keputusan final & rancangan penyimpanan

#### 11.7.1 Keputusan yang berlaku

1. Status Kelulusan Pembinaan Tahsin ditentukan **secara manual oleh instruktur**.
2. Instruktur memilih salah satu: **`Lulus`** atau **`Tidak Lulus`** untuk setiap santri.
3. Status ini **disimpan terpisah** dari Kelulusan SIK.
4. Admin **melihat** status tersebut pada halaman **Kelola Status Kelulusan** (`kelulusan-sik/index`) yang sudah tersedia.
5. **Tidak dibuat halaman atau menu baru.**
6. Sertifikat akhir hanya terbit bila **Kelulusan SIK = `Lulus`** dan **Kelulusan Tahsin = `Lulus`**. Bila salah satu belum `Lulus` atau belum ditentukan, penerbitan ditolak dengan alasan yang jelas.
7. **Granularitas final:** `kelulusan_tahsin` menyimpan **satu catatan untuk setiap `anggota_periode_sik_id`** (santri + periode SIK), **bukan per kegiatan**. Satu santri dapat memiliki status Tahsin berbeda pada periode SIK berbeda. Tidak ada `kegiatan_id` dan tidak ada relasi langsung ke `kegiatan`.

#### 11.7.2 Hasil pemeriksaan ulang: apakah perlu tabel/kolom tambahan?

| Alternatif | Hasil pemeriksaan | Keputusan |
|---|---|---|
| Kolom `status_tahsin` di `kelulusan_sik` | Bertentangan dengan keputusan "disimpan terpisah dari Kelulusan SIK" | **Ditolak** |
| Kolom di tabel `nilai` | `nilai` adalah domain angka/grade ujian; mencampur status kelulusan akan merusak pemisahan modul nilai | **Ditolak** |
| Kolom di `santris` | Hanya satu status global per santri, tanpa siapa yang menetapkan dan kapan; terbawa lintas periode | **Ditolak** |
| Pakai tabel legacy `kelulusan` | Sudah direncanakan jadi `arsip_kelulusan` (M8); hanya `santri_id` unique, tanpa `instruktur_id`, tanpa konteks periode yang eksplisit; tidak bisa dianggap sebagai Tahsin | **Ditolak** |
| Tabel baru `kelulusan_tahsin` | Tidak ada entity Tahsin di codebase (tidak ada tabel/model/route/teks "Tahsin"); keputusan mensyaratkan penyimpanan terpisah + audit penetapan | **DIPILIH** |

**Kesimpulan:** **perlu satu tabel baru** (`kelulusan_tahsin`, migration N2). **Tidak perlu** menambah kolom ke `kelulusan_sik`, `nilai`, `santris`, maupun tabel legacy mana pun. Tidak ada data lama yang diubah atau dihapus. Tabel baru ini **hanya berisi satu baris per `anggota_periode_sik_id`** dan **tidak memiliki `kegiatan_id`**; satu santri dengan beberapa periode SIK memiliki beberapa baris Tahsin yang dapat berbeda statusnya.

#### 11.7.3 Rancangan migration N2 — `create_kelulusan_tahsin_table`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | `bigIncrements` | PK |
| `anggota_periode_sik_id` | `foreignId` **UNIQUE**, FK → `anggota_periode_sik.id`, `restrictOnDelete` | **Satu baris per santri per periode SIK**. Karena `anggota_periode_sik` sudah unik per (`santri_id`, `periode_sik_id`), kunci ini menjamin status Tahsin tidak pernah dibuat per kegiatan |
| `kegiatan_id` | **TIDAK ADA** (dilarang) | Status Tahsin berlaku untuk seluruh kegiatan dalam periode SIK; tidak ada relasi langsung ke `kegiatan` |
| `instruktur_sik_id` | `foreignId` **nullable**, FK → `infrastruktur_siks.id`, **`nullOnDelete()`** | Instruktur yang menetapkan. Nullable + `nullOnDelete` agar menghapus instruktur tidak menghilangkan riwayat status |
| `status` | `enum('Lulus','Tidak Lulus')` **nullable** | `NULL` ditampilkan sebagai **Belum Ditentukan** (belum ada pilihan instruktur). Gate hanya menerima tepat `Lulus` |
| `tanggal_penilaian` | `date` **nullable** | Kapan instruktur menetapkan |
| `keterangan` | `text` **nullable** | Catatan instruktur |
| `timestamps` | — | Audit waktu dibuat/diperbarui |

Index/unique: `unique('anggota_periode_sik_id')` + `index('instruktur_sik_id')`. **Tidak ada index/FK ke `kegiatan`.**

`down()`: `Schema::dropIfExists('kelulusan_tahsin')` — hanya menghapus tabel baru, **tidak** menyentuh tabel lain maupun data lama.

#### 11.7.4 Relasi

```
santris ──────┐
              ├──► anggota_periode_sik ──(1:1)──► kelulusan_tahsin
periode_sik ──┘        │                          ▲
   (1 santri +         │                          │ instruktur_sik_id
    1 periode =        │                          │ (infrastruktur_siks)
    1 anggota)         │
                       ├──► kelulusan_sik (PER KEGIATAN, + kegiatan_id)
                       │            │
                       │            └──► sertifikat_sik
                       └──► (tidak ada cabang ke kegiatan untuk Tahsin)

kelulusan_tahsin: TANPA kegiatan_id, TANPA relasi ke kegiatan/pertemuan/absensi_sik.
DUA ENTITAS KELULUSAN TERPISAH, bertemu hanya di anggota_periode_sik_id.
```

| Model | Relasi yang ditambahkan |
|---|---|
| `KelulusanTahsin` (baru) | `anggotaPeriodeSik(): BelongsTo` (**satu baris per anggota periode**), `instruktur(): BelongsTo`; **tidak ada** `kegiatan()` |
| `AnggotaPeriodeSik` | `kelulusanTahsin(): HasOne` — tepat satu status Tahsin untuk santri pada periode ini; berbeda periode → baris berbeda |
| `InfrastrukturSik` | `kelulusanTahsin(): HasMany` — instruktur dapat menetapkan status untuk banyak anggota |
| `KelulusanSik` | **tidak** punya relasi langsung ke Tahsin; akses lewat `anggotaPeriodeSik.kelulusanTahsin` (status Tahsin yang sama tampil pada setiap kegiatan santri di periode itu) |

#### 11.7.5 Alur input instruktur (tanpa halaman/menu baru)

Halaman yang dipakai: **`instruktur.anggota.index`** → route `/anggota-saya` → view `anggota-periode-sik/index.blade.php` (sudah ada, khusus instruktur, berisi daftar santri di kelompok binaannya).

1. Kolom aksi baru **"Status Tahsin"** menampilkan nilai saat ini (`Lulus` / `Tidak Lulus` / `Belum Ditentukan`).
2. Kontrol pilihan inline: dua opsi **`Lulus`** dan **`Tidak Lulus`** (bukan teks bebas). **Tidak ada pilihan kegiatan** — status berlaku untuk seluruh kegiatan dalam periode SIK yang bersangkutan.
3. Submit ke satu route baru: `PATCH instruktur.anggota.kelulusan-tahsin.update` → `AnggotaPeriodeSikController@updateKelulusanTahsin`.
4. Server memvalidasi:
   - `anggota.id` harus milik kelompok dengan `instruktur_id === instruktur.id` yang login;
   - `status` wajib salah satu dari `Lulus`/`Tidak Lulus`;
   - `tanggal_penilaian` diisi otomatis `today()`;
   - `instruktur_sik_id` diisi otomatis dari user yang login;
   - **tidak menerima/menyimpan `kegiatan_id`** dari request.
5. Simpan dengan `updateOrCreate(['anggota_periode_sik_id' => ...], ...)` → **satu baris per santri per periode SIK**, tidak pernah menduplikat baris untuk kegiatan tertentu.
6. Redirect kembali ke `/anggota-saya` dengan pesan sukses.

Catatan: halaman Nilai Instruktur (`nilai/index`) sengaja **tidak** dipakai untuk input Tahsin agar tetap fokus pada nilai ujian sesuai permintaan sebelumnya. Alternatif bila diinginkan: kontrol yang sama dapat ditaruh inline di `nilai/index`; tetap tanpa halaman baru, tetapi memerlukan keputusan terpisah.

#### 11.7.6 Tampilan Admin & gate sertifikat

- `KelulusanSikController@index()`: `with('anggotaPeriodeSik.kelulusanTahsin')` agar tidak N+1.
- `kelulusan-sik/index.blade.php`: kolom **Status Tahsin** bersifat **read-only** (badge); admin tidak mengubahnya di halaman ini. Karena tabel ini menampilkan kelulusan per kegiatan, badge Tahsin yang sama dapat muncul pada beberapa baris kegiatan dari santri dan periode yang sama — itu **bukan duplikat data**, melainkan satu status Tahsin yang ditampilkan ulang.
- `kelulusan-sik/edit.blade.php`: panel status Tahsin ditampilkan di samping status SIK, tetap terpisah, dan diberi keterangan "berlaku untuk seluruh kegiatan dalam periode ini".
- `SertifikatSikController::eligibleGraduations()`: kumpulkan kandidat per `anggota_periode_sik_id`, bukan per `kelulusan_sik`; tambahkan syarat
  `whereHas('anggotaPeriode.kelulusanTahsin', fn ($q) => $q->where('status', 'Lulus'))`
  **dan** pastikan tidak ada `kelulusan_sik` pada periode itu yang statusnya bukan `Lulus` (detail S1/§11.9).
- **Gate akhir:** **semua** `kelulusan_sik` milik `anggota_periode_sik_id` pada periode bersangkutan berstatus `Lulus` **dan** `kelulusanTahsin?.status === 'Lulus'`. Status Tahsin `NULL` (`Belum Ditentukan`) dan `Tidak Lulus` **keduanya menolak** penerbitan.
- **Granularitas sertifikat:** satu sertifikat untuk satu `anggota_periode_sik_id`; tidak ada sertifikat terpisah per kegiatan (§11.9).
- **Alasan penolakan yang wajib ditampilkan:**
  - Ada kegiatan SIK dalam periode yang belum `Lulus` → "Masih ada Kelulusan SIK yang belum lulus pada periode ini: {nama kegiatan}."
  - Tahsin NULL / belum ada baris → "Kelulusan Pembinaan Tahsin belum ditentukan."
  - Tahsin `Tidak Lulus` → "Kelulusan Pembinaan Tahsin belum lulus."
  - Keduanya terpenuhi → tombol terbit sertifikat aktif untuk **satu sertifikat periode**.

#### 11.7.7 Keputusan final & catatan desain

- **Granularitas — SUDAH DIPUTUSKAN:** `kelulusan_tahsin` menyimpan **satu catatan untuk setiap `anggota_periode_sik_id`** (santri + periode SIK), **bukan per kegiatan**. Satu santri dapat memiliki status berbeda pada periode berbeda. Tidak ada `kegiatan_id`, tidak ada relasi langsung ke `kegiatan`. Rancangan `(anggota_periode_sik_id, kegiatan_id)` yang sebelumnya dipertimbangkan **dibatalkan** dan tidak lagi menjadi keputusan tertunda.
- **Perbedaan dengan SIK:** `kelulusan_sik` tetap **per kegiatan** (M1); `kelulusan_tahsin` **per periode**. Keduanya tidak digabung dan tidak saling menimpa.
- **Riwayat perubahan:** `timestamps` + `tanggal_penilaian` mencatat penetapan terakhir. Jika diperlukan audit penuh (siapa mengubah kapan), tambahkan tabel log terpisah pada fase berikutnya — tidak termasuk rencana ini.
- **Tidak ada keputusan Tahsin yang masih menunggu persetujuan** pada bagian ini.

### 11.8 Checklist pengujian nilai ujian & Kelulusan Tahsin

- [ ] Tidak ada menu, route, atau view baru yang dibuat untuk nilai ujian.
- [ ] Halaman Nilai Instruktur menampilkan daftar santri sesuai kelompok binaan (gate lama tetap berlaku).
- [ ] Instruktur tidak bisa menilai santri di luar kelompoknya.
- [ ] Badge kelayakan = `Memenuhi` bila hadir/Terlaksana ≥ 87,5%; `Tidak Memenuhi` bila < 87,5%.
- [ ] Pertemuan `Dibatalkan` dan `Terjadwal` tidak dihitung dalam kelayakan.
- [ ] Input nilai ujian ditolak di server bila santri tidak memenuhi kelayakan.
- [ ] Nilai di luar rentang 0–100 ditolak validasi.
- [ ] Nilai ujian tersimpan dengan `jenis_nilai = 'Ujian'` dan `kegiatan_sik_id` yang benar.
- [ ] Grade tampil sesuai rentang yang **disetujui** (belum ditetapkan pada tahap perencanaan).
- [ ] Nilai ujian yang sama tidak bisa dientry dua kali untuk santri + kegiatan (unique).
- [ ] Update nilai ujian memperbarui baris yang sama, bukan membuat duplikat.
- [ ] Data nilai lama (`jenis_nilai = 'Harian'`) tetap terbaca dan tidak berubah.
- [ ] Transkrip santri menampilkan nilai harian dan nilai ujian di bagian terpisah.
- [ ] Admin melihat di `kelulusan-sik/index`: nama, kegiatan ujian, angka, grade, kelayakan, status mengikuti ujian, status SIK.
- [ ] Admin melihat di `kelulusan-sik/edit`: alasan bila tidak memenuhi kehadiran, status SIK, status Tahsin terpisah.
- [ ] Sertifikat ditolak bila SIK belum lulus **atau** Tahsin belum lulus.
- [ ] Sertifikat terbit bila keduanya lulus.
- [ ] Tidak ada query N+1 pada halaman admin setelah penambahan kolom turunan.
- [ ] Migration N1 bisa dijalankan dan di-rollback tanpa kehilangan baris nilai lama.
- [ ] Migration N2 membuat `kelulusan_tahsin` tanpa mengubah `kelulusan_sik`, `nilai`, atau tabel legacy.
- [ ] Instruktur memilih `Lulus`/`Tidak Lulus` pada halaman `anggota-saya` tanpa menu/page baru.
- [ ] Instruktur tidak dapat mengubah status Tahsin santri di luar kelompoknya (diuji di server).
- [ ] Update status berulang tidak menimbulkan duplikat baris (`unique anggota_periode_sik_id`).
- [ ] Tabel `kelulusan_tahsin` **tidak memiliki** `kegiatan_id` dan tidak ada relasi ke `kegiatan`/`pertemuan`/`absensi_sik`.
- [ ] Satu santri dengan dua periode SIK menghasilkan dua baris Tahsin yang statusnya dapat berbeda.
- [ ] Instruktur tidak memilih/mengaitkan kegiatan saat mengisi Tahsin; status berlaku untuk seluruh kegiatan dalam periode.
- [ ] Admin melihat status Tahsin di `kelulusan-sik/index` dan `edit` tanpa dapat mengubahnya.
- [ ] Status Tahsin kosong tampil sebagai `Belum Ditentukan`, bukan `Lulus`.
- [ ] Sertifikat ditolak pada kombinasi: ada SIK belum `Lulus` + Tahsin `Tidak Lulus`; SIK `Tidak Lulus` + Tahsin `Lulus`; keduanya belum ditentukan.
- [ ] Sertifikat terbit hanya bila **semua SIK periode** `Lulus` + Tahsin `Lulus`.
- [ ] Satu santri dengan beberapa kegiatan lulus dalam satu periode hanya menerima **satu** sertifikat.
- [ ] Menghapus instruktur yang pernah menetapkan status tidak menghapus baris `kelulusan_tahsin` (`nullOnDelete`).

### 11.9 Sertifikat final — satu kali per santri per periode SIK

**Keputusan final:** satu sertifikat untuk setiap **santri + periode SIK** (`anggota_periode_sik_id`), **bukan** satu per `kelulusan_sik` dan bukan satu per kegiatan.

**Hubungan dengan aturan lama:**
- `kelulusan_sik` tetap dihitung **per kegiatan** (M1). Satu santri dapat memiliki beberapa baris kelulusan dalam satu periode.
- `kelulusan_tahsin` tetap **satu baris per santri per periode** (N2, tanpa `kegiatan_id`).
- Gate sertifikat tidak lagi memakai satu record kelulusan, melainkan **kumpulan seluruh kegiatan SIK pada periode itu** + status Tahsin.

**Syarat terbit:**
1. Semua `kelulusan_sik` yang termasuk dalam `periode_sik_id` tersebut berstatus `Lulus`.
2. `kelulusan_tahsin` untuk `anggota_periode_sik_id` tersebut berstatus `Lulus`.
3. Tidak ada sertifikat lain untuk `anggota_periode_sik_id` yang sama (dicegah oleh unique S1).

**Penyesuaian skema (S1, belum dibuat):**
- Tambah `anggota_periode_sik_id` nullable FK ke `sertifikat_sik`, lalu backfill dari `kelulusan_sik.anggota_periode_sik_id`.
- Tambah unique pada `anggota_periode_sik_id`.
- `kelulusan_sik_id` lama dipertahankan sebagai referensi historis; tidak ada baris sertifikat lama yang dihapus otomatis.
- Bila ternyata ada beberapa sertifikat untuk satu anggota periode, dedupe dilakukan dengan persetujuan dan backup, bukan penghapusan otomatis.

**Alur controller:**
- `SertifikatSikController::eligibleGraduations()` mengembalikan kandidat unik per `anggota_periode_sik_id`.
- Tombol terbit aktif hanya jika seluruh syarat di atas terpenuhi.
- Penyimpanan memakai `updateOrCreate(['anggota_periode_sik_id' => ...], ...)` sehingga menekan tombol dua kali tidak membuat sertifikat kedua.
- Alasan penolakan menampilkan nama kegiatan SIK yang belum lulus atau status Tahsin yang belum memenuhi.

**Checklist:**
- [ ] Satu santri per periode hanya punya satu baris `sertifikat_sik`.
- [ ] Beberapa kegiatan `Lulus` dalam satu periode tidak menghasilkan beberapa sertifikat.
- [ ] Sertifikat tidak terbit bila masih ada kegiatan SIK `Tidak Lulus`/`Belum Lulus` pada periode itu.
- [ ] Sertifikat tidak terbit bila Tahsin NULL/`Tidak Lulus`.
- [ ] Data sertifikat lama tidak dihapus saat S1 dijalankan.

---

## 12. REQUIREMENT TAMBAHAN TAHAP 2 — REKAP, RABU MENGAJI, NILAI INSTRUKTUR, DATA DOSEN, PRIORITAS WEB-FIRST, IMPORT FINGERPRINT

Bagian ini merangkum permintaan awal dan perubahan prioritas terbaru yang harus benar-benar masuk ke rencana. **Tidak ada perubahan kode, migration, atau database yang dilakukan pada tahap dokumentasi ini.** Seluruh pekerjaan fingerprint berstatus **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**.

### 12.1 Keterangan status

| Tanda | Arti |
|---|---|
| ✅ **SUDAH MASUK RENCANA** | Requirement sudah tercantum di bagian lain dokumen dan diperkuat di bagian ini. |
| ⚠️ **PERLU KEPUTUSAN** | Rancangan teknis sudah ada, tetapi perlu konfirmasi pengguna sebelum implementasi. |
| ⏳ **MENUNGGU CONTOH FILE** | Tidak boleh diputuskan dari asumsi; menunggu file ekspor asli perangkat fingerprint. |
| 🚫 **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | Tidak dikerjakan sama sekali pada prioritas ini. Tidak ada import/SDK/API/koneksi perangkat/migration/data fingerprint yang diubah. |

| ID | Requirement | Status | Rincian |
|---|---|---|---|
| REQ-1 | Tabel utama rekap disederhanakan menjadi 6 kolom + tombol Detail | ✅ | §12.2 |
| REQ-2 | Detail menampilkan rincian kehadiran lengkap | ✅ | §12.2 |
| REQ-3 | Satu sumber data/absensi untuk rekap | ✅ | §12.2, §12.7 |
| REQ-4 | Tabel khusus/cara khusus membedakan Absensi Rabu Mengaji | ✅ (struktur) / ⚠️ (penanda) | §12.3 |
| REQ-5 | Cari santri & filter tanggal pelaksanaan SIK pada Rabu Mengaji | ✅ | §12.3 |
| REQ-6 | Tombol Detail per baris Rabu Mengaji | ✅ | §12.3 |
| REQ-7 | Tidak ada penghitungan ganda fingerprint vs absensi manual | ✅ | §12.3, §12.7 |
| REQ-8 | Hilangkan blok berwarna hijau penanda pada Nilai Instruktur | ✅ | §12.4 |
| REQ-9 | Label "Mata Pelajaran" → "Kelompok" pada tampilan yang relevan | ✅ (tampilan) / ⚠️ (makna field) | §12.4 |
| REQ-10 | Hubungkan info absensi dosen ke halaman Instruktur | ✅ | §12.4 |
| REQ-11 | Hapus tautan "Rekap Absensi Dosen" di header Data Dosen | ✅ | §12.5 |
| REQ-12 | Tombol riwayat dosen diberi label jelas & data tidak tertukar | ✅ | §12.5 |
| REQ-13 | Import file ekspor fingerprint (validasi, cocok, dedupe, gagal, ringkasan, log) | 🚫 **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | §12.6 |
| REQ-14 | Format/impor menunggu contoh file asli perangkat | 🚫 **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** (format tetap tidak diasumsikan) | §12.6, Lampiran A poin 7 |
| REQ-15 | Konsistensi seluruh domain absensi & satu sumber rekap | ✅ | §12.7 |
| REQ-16 | Sertifikat **satu kali per santri per periode SIK** (bukan per kegiatan) | ✅ (sudah diputuskan) | §11.9, Lampiran A poin 3/12 |
| REQ-17 | Prioritas web-first tanpa fingerprint + alur absensi sementara manual website | ✅ | §12.9, §3.3, §10 |

### 12.2 REQ-1 s.d. REQ-3 — Halaman Rekap Kehadiran Santri

**Tampilan utama (tabel saat halaman dibuka) — HANYA kolom ini:**

| No | Kolom | Sumber |
|---|---|---|
| 1 | Nomor | nomor urut baris (paginasi) |
| 2 | Nama Santri | `anggotaPeriode.santri.nama` |
| 3 | Nomor Induk | `anggotaPeriode.santri.nomor_induk` |
| 4 | Kelompok | `anggotaPeriode.kelompok` |
| 5 | Total Pertemuan | `rekap_kehadiran_sik.total_pertemuan` (dari jumlah `pertemuan` aktual) |
| 6 | Status | `rekap_kehadiran_sik.status_kehadiran` (turunan) |
| — | **Tombol Detail** | menuju `rekap-kehadiran-sik.show` |

**Dihapus dari tabel utama (dan CSV mengikuti tabel utama):** Jam Datang, Jam Pulang, Tanggal, Hadir, Izin, Sakit, Alpha.

**Halaman/panel Detail — menampilkan rincian lengkap:**

1. Riwayat **tanggal pertemuan** (dan nomor pertemuan) yang menjadi dasar rekap.
2. **Jam datang** (`waktu_masuk`) dan **jam pulang** (`waktu_pulang`) — ditampilkan **bila tersedia**; bila tidak ada, tampilkan `—`.
3. **Status per pertemuan**: `Hadir`, `Izin`, `Sakit`, `Alpha` (serta `Tidak Lengkap` bila ada).
4. **Total Hadir**.
5. **Total Izin**.
6. **Total Sakit**.
7. **Total Alpha**.
8. **Total Pertemuan**.
9. **Persentase Kehadiran**.

> **Perbaikan terhadap teks lama:** dokumen sebelumnya menuliskan "Hapus Jam Datang/Pulang dari index, **detail**, dan CSV". Itu **dikoreksi**: jam datang/pulang dihapus hanya dari **tabel utama dan CSV**, dan **justru wajib tampil di halaman detail** sesuai permintaan terbaru.

**Sumber data tunggal (wajib):**
- Pencatatan mentah: `absensi_sik` (diisi Instruktur/Dosen; kolom `dicatat_oleh`, `waktu_masuk`, `waktu_pulang`, `status`, `metode_absensi`).
- Agregasi: `AttendanceRecapService` → `rekap_kehadiran_sik` (cache) dengan `total_pertemuan` dari jumlah `pertemuan` aktual, **bukan** angka hard-code 16.
- Tabel utama, halaman detail, export, dashboard, dan laporan **memakai service/cache yang sama**. Dilarang membuat query agregat kedua atau menghitung ulang di Blade/JS.

**Checklist REQ-1–REQ-3:**
- [ ] Tabel utama hanya 6 kolom + tombol Detail.
- [ ] Tidak ada jam datang/pulang, tanggal, hadir, izin, sakit, alpha di tabel utama.
- [ ] CSV export mengikuti kolom tabel utama.
- [ ] Detail menampilkan 9 butir rincian di atas, termasuk jam datang/pulang bila tersedia.
- [ ] Angka tabel utama = angka detail (satu sumber).
- [ ] Total pertemuan berasal dari `pertemuan` aktual, bukan konfigurasi 16.

### 12.3 REQ-4 s.d. REQ-7 — Absensi Rabu Mengaji

**Pemeriksaan reuse tabel yang sudah ada (sebelum memutuskan tabel baru):**

| Objek | Kondisi | Keputusan |
|---|---|---|
| `absensi` (legacy) | Akan diarsipkan sebagai `arsip_absensi` (M8) | **Tidak dipakai** |
| `jadwal` (legacy) | Akan diarsipkan sebagai `arsip_jadwal` (M8); hanya daftar hari `Rabu`, bukan entitas "Rabu Mengaji" | **Tidak dipakai** |
| `kegiatan` + `pertemuan` | Sudah punya `nama_kegiatan`, `jenis_kegiatan`, `pertemuan.tanggal`, `status` | **Dipakai ulang** |
| `absensi_sik` | Sudah menyimpan Hadir/Izin/Sakit/Alpha per santri per pertemuan | **Dipakai ulang** |
| `rekap_kehadiran_sik` | Cache agregat per anggota + kegiatan | **Dipakai ulang** |
| `fingerprints` / `log_penggunaan_fingerprint` | Bukti/log scan, bukan absensi sah | **Tidak dijadikan sumber absensi** |

**Kesimpulan reuse:** **tidak perlu tabel absensi baru** untuk Rabu Mengaji. Yang diperlukan hanyalah **penanda yang jelas** agar kegiatan Rabu Mengaji dapat dibedakan dari kegiatan SIK lain. Saat ini `kegiatan.jenis_kegiatan` hanya bernilai `Kelas`/`Aula`, sehingga cara penandaan (nilai enum baru, kolom penanda, atau konvensi nama) **masih memerlukan keputusan** — Lampiran A poin 13.

**Requirement tampilan/data:**
1. Data dapat **dicari berdasarkan nama santri** (dari `anggotaPeriode.santri.nama`).
2. Data dapat **difilter berdasarkan tanggal pelaksanaan SIK** (memakai `pertemuan.tanggal`, dipetakan ke `periode_sik` bila diperlukan).
3. **Setiap baris memiliki tombol Detail**.
4. **Detail** menampilkan informasi kehadiran Rabu Mengaji secara lengkap (tanggal pertemuan, jam datang/pulang bila tersedia, status per santri, total hadir/izin/sakit/alpha, total pertemuan, persentase) — mengikuti format §12.2.
5. Data Rabu Mengaji **harus dapat dibedakan** dari absensi kegiatan SIK lainnya (lewat penanda kegiatan yang disetujui).
6. **Tidak boleh terjadi penghitungan ganda** antara data fingerprint dan absensi manual:
   - log fingerprint **tidak pernah** ditambahkan ke total `absensi_sik` secara otomatis;
   - bila suatu hari konversi disetujui, `unique (anggota_periode_sik_id, pertemuan_id)` pada `absensi_sik` mencegah dua baris untuk santri + pertemuan yang sama;
   - total rekap dihitung sekali oleh `AttendanceRecapService`.

**Checklist REQ-4–REQ-7:**
- [ ] Tidak ada tabel absensi duplikat untuk Rabu Mengaji.
- [ ] Penanda kegiatan Rabu Mengaji sudah diputuskan dan dipakai konsisten.
- [ ] Pencarian nama santri bekerja.
- [ ] Filter tanggal pelaksanaan SIK bekerja.
- [ ] Setiap baris punya tombol Detail; detail menampilkan rincian lengkap.
- [ ] Rabu Mengaji tidak tercampur dengan kegiatan SIK lain di rekap.
- [ ] Total rekap tidak menjumlahkan log fingerprint + absensi manual sekaligus.

### 12.4 REQ-8 s.d. REQ-10 — Halaman Nilai Instruktur

**Kondisi yang diperiksa (read-only):**
- `resources/views/nilai/index.blade.php` menampilkan form per santri dengan dropdown `mata_pelajaran_id` berlabel **"Mata pelajaran"**.
- `app/Http/Controllers/NilaiController.php` mengambil `MataPelajaran` aktif dan memvalidasi `mata_pelajaran_id`; gate kelompok instruktur (`ensureInstructorCanGrade`) sudah ada.
- `nilai.mata_pelajaran_id` adalah FK ke `mata_pelajaran` (migration `2026_10_31_074047_create_nilai_table.php`), **bukan** ke `kelompok`.
- Tidak ada file screenshot yang tersimpan di repositori; kebutuhan "blok hijau" dicatat berdasarkan keterangan pengguna (lihat Lampiran A poin 15 bila perlu konfirmasi).

**Requirement:**
1. **Hilangkan bagian berwarna hijau** yang sebelumnya ditandai pengguna pada halaman Nilai Instruktur (blok penanda/sorotan), tanpa mengubah data nilai.
2. **Ubah label "Mata Pelajaran" menjadi "Kelompok" hanya pada tampilan yang memang menampilkan kelompok** (misalnya konteks daftar santri/kelompok binaan instruktur).
3. **Periksa kesesuaian struktur DB:** karena `nilai.mata_pelajaran_id` masih FK ke `mata_pelajaran` dan dipakai modul Nilai, **kolom database tidak diganti namanya** dan tidak diisi dengan ID kelompok. Mengganti label pada dropdown yang mengikat `mata_pelajaran_id` dengan `kelompok` akan **menyesatkan**; bila pengguna bermaksud mengganti makna field tersebut, itu **keputusan terpisah** (Lampiran A poin 14).
4. **Hubungkan informasi absensi dosen ke halaman Instruktur:**
   - Tambah panel/ringkasan **read-only** kehadiran **dosen/instruktur yang sedang login**, bersumber dari `absensi_dosen` yang difilter oleh `infrastruktur_sik_id` instruktur tersebut.
   - Letakkan pada halaman Instruktur yang sudah ada (dashboard/instruktur), **tanpa route atau menu baru**.
   - Beri label tegas **"Absensi Instruktur/Dosen"** dan tautan ke `rekap-absensi-dosen.show` untuk riwayat.
   - **Dilarang** menggabungkan angka ini dengan rekap kehadiran santri.
5. Fungsi nilai ujian, gate kelayakan 87,5%, dan larangan halaman/menu baru dari §11 tetap berlaku.

**Checklist REQ-8–REQ-10:**
- [ ] Blok hijau penanda tidak tampil lagi di `nilai/index`.
- [ ] Data nilai lama tidak berubah akibat perubahan tampilan.
- [ ] Label "Kelompok" hanya dipakai untuk nilai yang memang berasal dari data kelompok.
- [ ] `mata_pelajaran_id` tetap utuh dan tidak diisi ID kelompok.
- [ ] Panel absensi dosen tampil di halaman Instruktur dengan data instruktur yang login.
- [ ] Panel absensi dosen tidak mengubah/mencampur rekap santri.

### 12.5 REQ-11–REQ-12 — Halaman Data Dosen

**Kondisi yang diperiksa (read-only):**
- `resources/views/users/index.blade.php` memiliki tautan **"Rekap Absensi Dosen"** di header halaman (`route('rekap-absensi-dosen.index')`).
- Di dalam tabel sudah ada tautan per dosen **"Detail riwayat"** ke `rekap-absensi-dosen.show` dengan profil `infrastruktur_sik`.

**Requirement:**
1. **Hapus menu/tautan "Rekap Absensi Dosen" yang berada di bagian atas halaman Data Dosen** sesuai permintaan pengguna. Ini **hanya perubahan tampilan** — **data `absensi_dosen` dan fungsi pencatatan kehadiran dosen tetap dipertahankan** (menu rekap tetap tersedia melalui navigasi admin yang sudah direncanakan).
2. **Perjelas tombol riwayat** per dosen: gunakan label **"Detail Riwayat"** atau **"Lihat Riwayat"** (bukan label samar).
3. Pastikan tautan mengirim **`infrastruktur_sik_id` (profil dosen)**, bukan `users.id` dan bukan `santri.id`.
4. Pastikan halaman riwayat memfilter `absensi_dosen` **hanya** berdasarkan `infrastruktur_sik_id` tersebut, sehingga **data dosen yang dibuka benar dan tidak tertukar dengan data santri**.
5. Pertahankan tautan "Buka Kelompok Santri" yang sudah direncanakan terfilter oleh `instruktur_id`.

**Checklist REQ-11–REQ-12:**
- [ ] Tautan "Rekap Absensi Dosen" tidak lagi tampil di header Data Dosen.
- [ ] Tabel `absensi_dosen` dan alur input/edit/hapus tetap berfungsi.
- [ ] Tombol riwayat berlabel jelas ("Detail Riwayat"/"Lihat Riwayat").
- [ ] Riwayat menampilkan dosen yang benar (sesuai profil), bukan santri.
- [ ] Data absensi dosen tidak tercampur dengan data kehadiran santri.

### 12.6 REQ-13–REQ-14 — Import Data Fingerprint — **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**

> **Keputusan terbaru:** seluruh pekerjaan fingerprint **tidak dibuat dan tidak dijalankan** pada prioritas ini. Pihak SIK belum menetapkan apakah absensi wajib memakai mesin fingerprint atau boleh memakai metode lain; keputusan final diambil setelah konsultasi. Data dan struktur fingerprint yang sudah ada **dipertahankan tanpa perubahan**. Detail lama di bawah tetap dicatat sebagai requirement arsip, bukan pekerjaan aktif.

| No | Requirement lama (tetap tercatat) | Status sekarang | Catatan penundaan |
|---|---|---|---|
| 1 | Import file hasil ekspor perangkat | **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | Tidak dibuat fitur upload/pratinjau/commit |
| 2 | Validasi format dan kolom file | **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | Format tidak diasumsikan; tidak ada validasi yang dibuat |
| 3 | Pencocokan `fingerprint_id` dengan santri terdaftar | **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | Aturan lama di §8.4 hanya arsip |
| 4 | Deteksi data ganda | **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | Tidak ada unique/index baru |
| 5 | Penanganan `fingerprint_id` tidak dikenal | **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | Tidak ada baris gagal baru yang diproses |
| 6 | Penanganan data gagal import | **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | Tidak ada tabel staging baru |
| 7 | Ringkasan berhasil / gagal / duplikat | **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | Tidak ada pratinjau/riwayat batch |
| 8 | Log proses import | **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | Tidak ada log import baru |
| 9 | Hasil impor dapat digunakan sistem rekap santri | **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | Fingerprint tidak menulis/menambah `absensi_sik` |
| 10 | Format CSV/XLSX diputuskan dari contoh asli | **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | Format ekspor **tidak diketahui dan tidak diasumsikan**; contoh file tidak menjadi prasyarat pengembangan web |

**Daftar pekerjaan fingerprint yang ditunda:**
1. Fitur import file ekspor perangkat (upload, pratinjau, commit, riwayat, detail gagal).
2. Integrasi **SDK/API** perangkat fingerprint.
3. Koneksi ke perangkat fingerprint.
4. Migration khusus fingerprint baru (M2–M5 dan turunannya).
5. Penentuan format/ekspor, nama kolom, pemetaan `fingerprint_id`, dedupe, dan konversi ke `absensi_sik`.
6. Rekap fingerprint, export, dan validasi registrasi fingerprint baru.

**Checklist penundaan (yang wajib dipastikan sekarang):**
- [ ] Tidak ada fitur import fingerprint yang dibuat.
- [ ] Tidak ada integrasi SDK/API atau koneksi perangkat.
- [ ] Tidak ada migration fingerprint baru; M2–M5 tidak dijalankan.
- [ ] Tidak ada asumsi format ekspor perangkat.
- [ ] Data dan struktur `fingerprints` / `log_penggunaan_fingerprint` tidak diubah.
- [ ] Tidak ada route/menu fingerprint yang didaftarkan.
- [ ] Metode absensi akhir dicatat sebagai keputusan setelah konsultasi dengan pihak SIK.

### 12.7 REQ-15 — Matriks Konsistensi Data

| Domain | Sumber kebenaran | Dipakai oleh | Pemisahan / pengaman |
|---|---|---|---|
| Absensi santri | `absensi_sik` | Rekap santri, detail, kelulusan, ujian, Rabu Mengaji | Satu baris per santri + pertemuan (`unique`) |
| Absensi Rabu Mengaji | `kegiatan` (berpenanda) + `pertemuan` + `absensi_sik` | Pencarian/filter/detail Rabu Mengaji | Penanda kegiatan; tidak ada tabel absensi baru |
| Absensi dosen | `absensi_dosen` | Rekap dosen, halaman Instruktur, riwayat dosen | FK `infrastruktur_sik_id`; unik dosen + tanggal; tidak pernah dijoin ke rekap santri |
| Data fingerprint | `fingerprints` + `log_penggunaan_fingerprint` (existing, tanpa perubahan) | Tidak dipakai pada prioritas ini — **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`** | Bukan absensi sah; tidak menambah total rekap; tidak ada import batch/rows baru |
| Rekap kehadiran santri | `rekap_kehadiran_sik` (dari `AttendanceRecapService`) | Index, detail, export, dashboard, laporan, Instruktur | Satu sumber; dilarang hitung ulang di UI |
| Halaman Instruktur | `nilai` (ujian) + `absensi_dosen` (panel dosen) | Instruktur | Panel dosen diberi label jelas, tidak dicampur ke rekap santri |
| Halaman Data Dosen | `users` + `infrastruktur_siks` + `absensi_dosen` | Admin | Riwayat memakai `infrastruktur_sik_id`, bukan `santri_id` |

**Aturan keras:**
1. **Jangan mencampur absensi dosen dengan absensi santri** (tabel, query, label, dan angka terpisah).
2. **Jangan membuat dua sumber perhitungan rekap.** Semua angka rekap dari service/cache yang sama.
3. Fingerprint adalah **bukti/log lama** dan seluruh pekerjaannya berstatus **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**; bukan penambah angka hadir dan bukan penghambat website.

### 12.8 Checklist Ringkas Status Requirement

- [ ] ✅ REQ-1–REQ-3: tabel utama 6 kolom + Detail; detail lengkap; satu sumber rekap.
- [ ] ✅ REQ-4–REQ-7: Rabu Mengaji memakai tabel yang ada; cari/filter/detail; penanda ⚠️ diputuskan; tanpa duplikasi fingerprint.
- [ ] ✅ REQ-8–REQ-10: blok hijau hilang; label "Kelompok" hanya pada tampilan yang tepat; absensi dosen terhubung ke halaman Instruktur.
- [ ] ✅ REQ-11–REQ-12: tautan rekap di header Data Dosen dihapus; tombol riwayat jelas & data dosen tidak tertukar.
- [ ] 🚫 REQ-13: **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** — import tidak dibuat.
- [ ] 🚫 REQ-14: **DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK** — format ekspor tidak diasumsikan.
- [ ] ✅ REQ-15: konsistensi domain data dan satu sumber rekap terjaga.
- [ ] ✅ REQ-16: sertifikat satu kali per santri per periode SIK; gate semua SIK + Tahsin lulus.
- [ ] ✅ REQ-17: prioritas web-first dan absensi sementara manual website (§12.9).
- [ ] ⚠️ Keputusan tambahan yang **tidak** terkait fingerprint: penanda Rabu Mengaji, makna label "Kelompok", rentang/ambang grade, peran dosen/instruktur, dan route `jadwal`/`mata-pelajaran`.

### 12.9 REQ-17 — Prioritas web-first & absensi sementara (sesuai keputusan terbaru)

**Keputusan:** website SIKVERSE dikembangkan terlebih dahulu **tanpa ketergantungan fingerprint**. Fingerprint bukan penghambat; fitur web yang tidak memakai perangkat langsung masuk prioritas.

**Fitur website yang diprioritaskan:**
1. Login dan autentikasi.
2. Hak akses dan dashboard setiap role.
3. Manajemen data santri.
4. Manajemen kelompok dan periode SIK.
5. Manajemen instruktur/dosen.
6. Absensi santri melalui website.
7. Rekap kehadiran santri.
8. Tabel/data Rabu Mengaji.
9. Nilai ujian dan grade.
10. Kelulusan SIK per kegiatan.
11. Kelulusan Pembinaan Tahsin per santri per periode.
12. Sertifikat satu kali per santri per periode.
13. Laporan Ka-Lab Mutu.
14. Perbaikan seluruh halaman, tombol, route, validasi, dan fitur yang masih bermasalah.

**Alur absensi sementara lewat website (ringkas):**
`Instruktur login` → `pilih kegiatan` → `pilih pertemuan` → `input Hadir/Izin/Sakit/Alpha per santri` → `simpan ke absensi_sik` → `AttendanceRecapService` → `rekap_kehadiran_sik` → `tabel utama/detail/export/dashboard/laporan`. Rincian lengkap ada di §3.3.

**Ketergantungan yang masih tersisa (bukan fingerprint):**
- Keputusan grade dan ambang nilai lulus untuk nilai ujian (Lampiran A poin 8–9).
- Penanda kegiatan Rabu Mengaji (Lampiran A poin 13).
- Makna label "Kelompok" di Nilai Instruktur (Lampiran A poin 14).
- Peran dosen/instruktur dan route `jadwal`/`mata-pelajaran` (Lampiran A poin 5–6).
- Persetujuan implementasi S1 untuk granularitas sertifikat per periode.
- Ambang/aturan kehadiran final bila belum ditetapkan (Lampiran A poin 1–2).

**Checklist REQ-17:**
- [ ] Seluruh fitur di atas dapat dikembangkan tanpa perangkat fingerprint.
- [ ] Absensi manual website dapat dikembangkan dan diuji mandiri.
- [ ] Tidak ada dua sistem pencatatan absensi santri.
- [ ] Fingerprint tidak menjadi blocker rilis.
- [ ] Semua pekerjaan fingerprint tetap berstatus **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**.

---

## Lampiran A — Keputusan yang Masih Perlu Persetujuan

1. **Ambang kelulusan:** persentase tetap (usulan 87,5%) atau jumlah hadir minimum yang disesuaikan total pertemuan?
2. **Pertemuan dihitung:** hanya `Terlaksana`, atau juga `Terjadwal` yang tanggalnya sudah lewat?
3. **Sertifikat — SUDAH DIPUTUSKAN:** **satu kali per santri per periode SIK**, bukan per kegiatan. Tetap butuh gate: semua `kelulusan_sik` pada periode itu `Lulus` dan `kelulusan_tahsin` `Lulus` (§11.9). Tidak perlu keputusan lanjutan.
4. **Konversi fingerprint — `DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`:** tidak ada konversi otomatis menjadi `absensi_sik`. Metode absensi akhir akan diputuskan setelah konsultasi dengan pihak SIK.
5. **Peran dosen vs instruktur:** satu tabel `infrastruktur_siks` dengan kolom `peran`, atau pemisahan entitas?
6. **Route `jadwal`/`mata-pelajaran`:** diaktifkan atau dihapus?
7. **Format file ekspor fingerprint — `DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`:** format (CSV/XLSX/lainnya), nama kolom, pemisah, encoding, dan ID scan unik **tidak ditentukan dan tidak diasumsikan**. Bila pekerjaan fingerprint dibuka kembali, contoh file asli tetap wajib diterima sebelum pemetaan kolom dibuat (§12.6, REQ-14).
8. **Rentang grade nilai ujian** (usulan A 90–100, B 80–<90, C 70–<80, D 60–<70, E <60 — belum ditetapkan).
9. **Ambang nilai lulus ujian** (usulan minimal C / 70 — belum ditetapkan).
10. **Kegiatan yang ditandai sebagai ujian:** pakai `kegiatan` biasa atau tambah nilai `jenis_kegiatan = 'Ujian'`.
11. **Granularitas status Tahsin — SUDAH DIPUTUSKAN, tidak lagi tertunda:** satu catatan `kelulusan_tahsin` untuk setiap `anggota_periode_sik_id` (santri + periode SIK), **bukan per kegiatan**. Satu santri dapat memiliki status berbeda pada periode berbeda. Tidak ada `kegiatan_id` dan tidak ada relasi langsung ke `kegiatan`. Alternatif `(anggota_periode_sik_id, kegiatan_id)` **dibatalkan** (§11.7).
12. **Sertifikat akhir (sudah diputuskan):** **satu per santri per periode SIK**, bukan satu per record kelulusan/kegiatan. Gate: semua Kelulusan SIK pada periode itu **dan** Kelulusan Tahsin sama-sama `Lulus` (§11.9). Tidak perlu keputusan lanjutan.
13. **Penanda Absensi Rabu Mengaji (BARU):** bagaimana cara membedakan kegiatan Rabu Mengaji dari kegiatan SIK lain — tambah nilai `jenis_kegiatan = 'Rabu Mengaji'`, kolom penanda khusus, atau konvensi `nama_kegiatan`? (§12.3). **Tidak ada tabel absensi baru** dalam rancangan mana pun.
14. **Makna label "Kelompok" di Nilai Instruktur (BARU):** apakah perubahan label hanya pada tampilan, atau pengguna bermaksud mengganti field `nilai.mata_pelajaran_id` menjadi data kelompok? Karena kolom tersebut masih FK ke `mata_pelajaran` dan dipakai modul lain, **perubahan makna/skema tidak dilakukan tanpa keputusan ini** (§12.4).
15. **Konfirmasi blok hijau Nilai Instruktur (BARU):** blok berwarna hijau yang ditandai pengguna tidak memiliki screenshot di repositori; konfirmasi bagian mana yang dimaksud bila diperlukan sebelum implementasi (§12.4).
16. **Metode absensi akhir — `DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`:** pihak SIK belum memutuskan wajib fingerprint atau metode lain. Untuk sementara website memakai absensi manual (§3.3). Keputusan final diambil setelah konsultasi dengan pihak SIK.

## Lampiran B — Yang Tidak Dilakukan pada Tahap Ini

- Tidak ada file kode yang diedit.
- Tidak ada migration dibuat/dijalankan.
- Tidak ada perubahan struktur/data database.
- Tidak ada import data sungguhan.
- Tidak ada refactor otomatis.
- Tidak ada fitur import fingerprint, SDK/API, atau koneksi perangkat fingerprint yang dibuat.
- Tidak ada migration fingerprint baru (M2–M5 tidak dibuat/dijalankan).
- Tidak ada perubahan pada data/struktur `fingerprints` dan `log_penggunaan_fingerprint`.
- Tidak ada asumsi format ekspor perangkat fingerprint.
- Tidak ada penghapusan tabel atau data fingerprint.
- Seluruh pekerjaan fingerprint berstatus **`DITUNDA - MENUNGGU KEPUTUSAN PIHAK SIK`**.
