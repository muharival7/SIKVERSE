<?php

use App\Http\Controllers\AbsensiSikController;
use App\Http\Controllers\AnggotaPeriodeSikController;
use App\Http\Controllers\BuktiKetidakhadiranController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\KelompokController;
use App\Http\Controllers\KelulusanSikController;
use App\Http\Controllers\NilaiController;
use App\Http\Controllers\PeriodeSikController;
use App\Http\Controllers\PertemuanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RabuMengajiController;
use App\Http\Controllers\RabuMengajiImportController;
use App\Http\Controllers\RekapAbsensiDosenController;
use App\Http\Controllers\RekapKehadiranSikController;
use App\Http\Controllers\SantriController;
use App\Http\Controllers\SertifikatSikController;
use App\Http\Controllers\UserManagementController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==================================================
// HALAMAN BERANDA PUBLIK SIKVERSE
// ==================================================

Route::get('/', function () {
    return view('beranda');
})->name('beranda');

Route::get('/tentang-sik', fn (): RedirectResponse => redirect()->to(route('beranda').'#tentang'))
    ->name('tentang-sik');
Route::get('/layanan', fn (): RedirectResponse => redirect()->to(route('beranda').'#layanan'))
    ->name('layanan');

// ==================================================
// GANTI PASSWORD
// ==================================================

Route::middleware('auth')->group(function (): void {

    Route::get('/change-password', [ChangePasswordController::class, 'edit'])
        ->name('password.change');

    Route::post('/change-password', [ChangePasswordController::class, 'update'])
        ->name('change-password.update');

    // Mengatasi formulir lama yang mengirim POST /password
    Route::post('/password', [ChangePasswordController::class, 'update'])
        ->name('password.custom-update');
});

// ==================================================
// ROUTE SETELAH LOGIN
// ==================================================

$authenticatedMiddleware = ['auth', 'password.changed'];

if (config('sik.auth.require_email_verification')) {
    array_splice($authenticatedMiddleware, 1, 0, 'verified');
}

Route::middleware($authenticatedMiddleware)->group(function (): void {

    // Dashboard utama berdasarkan role
    Route::get('/dashboard', function (Request $request): RedirectResponse {

        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $role = $user->resolvedRole();

        abort_if($role === null, 403, 'Peran pengguna tidak dikenali. Hubungi administrator.');

        return redirect()->route($role->dashboardRoute());

    })->name('dashboard');

    // ==================================================
    // DASHBOARD BERDASARKAN ROLE
    // ==================================================

    Route::prefix('dashboard')->name('dashboard.')->group(function (): void {

        Route::get('/admin', DashboardController::class)
            ->middleware('role:admin')
            ->name('admin');

        Route::get('/santri', DashboardController::class)
            ->middleware('role:santri')
            ->name('santri');

        Route::get('/infrastruktur-sik', DashboardController::class)
            ->middleware('role:infrastruktur_sik')
            ->name('infrastruktur_sik');

        Route::get('/ka-lab-mutu', DashboardController::class)
            ->middleware('role:ka_lab_mutu')
            ->name('ka_lab_mutu');
    });

    Route::get('/sertifikat-sik/{sertifikatSik}/download', [
        SertifikatSikController::class,
        'download',
    ])->middleware('role:admin,santri')->name('sertifikat-sik.download');

    Route::get('/bukti-ketidakhadiran/{buktiKetidakhadiran}/download', [
        BuktiKetidakhadiranController::class,
        'download',
    ])->middleware('role:admin,santri,infrastruktur_sik')->name('bukti-ketidakhadiran.download');

    // ==================================================
    // FITUR KHUSUS ADMIN
    // ==================================================

    Route::middleware('role:admin')->group(function (): void {

        Route::get('/rekap-absensi-dosen', [RekapAbsensiDosenController::class, 'index'])
            ->name('rekap-absensi-dosen.index');
        Route::get('/rekap-absensi-dosen/export', [RekapAbsensiDosenController::class, 'export'])
            ->name('rekap-absensi-dosen.export');
        Route::get('/rekap-absensi-dosen/{infrastrukturSik}', [RekapAbsensiDosenController::class, 'show'])
            ->whereNumber('infrastrukturSik')
            ->name('rekap-absensi-dosen.show');
        Route::post('/rekap-absensi-dosen', [RekapAbsensiDosenController::class, 'store'])
            ->name('rekap-absensi-dosen.store');

        Route::resource('users', UserManagementController::class)->except(['show']);

        Route::get('/santris/import/template', [SantriController::class, 'downloadImportTemplate'])
            ->name('santris.import.template');
        Route::get('/santris/import/credentials/{token}', [SantriController::class, 'downloadImportCredentials'])
            ->whereUuid('token')
            ->name('santris.import.credentials');
        Route::get('/santris/import', [SantriController::class, 'importForm'])
            ->name('santris.import');
        Route::post('/santris/import/preview', [SantriController::class, 'previewImport'])
            ->name('santris.import.preview');
        Route::post('/santris/import/recheck', [SantriController::class, 'recheckImport'])
            ->name('santris.import.recheck');
        Route::post('/santris/import/commit', [SantriController::class, 'commitImport'])
            ->name('santris.import.commit');

        // Data Santri
        Route::resource('santris', SantriController::class);

        // Manajemen Periode SIK
        Route::resource('periode-sik', PeriodeSikController::class)
            ->except(['show']);

        // Manajemen Kelompok
        Route::resource('kelompok', KelompokController::class)
            ->except(['show']);

        // Manajemen Anggota Periode
        Route::resource('anggota-periode-sik', AnggotaPeriodeSikController::class)
            ->except(['show']);

        // Kegiatan tetap menjadi data induk untuk pertemuan dan absensi SIK.
        Route::resource('kegiatan', KegiatanController::class)
            ->except(['show']);

        // Manajemen Pertemuan
        Route::resource('pertemuan', PertemuanController::class)
            ->except(['show']);

        // Manajemen Kelulusan
        Route::resource('kelulusan-sik', KelulusanSikController::class)
            ->except(['show']);

        // Preview Sertifikat
        Route::get('/sertifikat-sik/{sertifikatSik}/preview', [
            SertifikatSikController::class,
            'preview',
        ])->name('sertifikat-sik.preview');

        // Manajemen Sertifikat: hanya penerbitan yang dibuka. Rute edit,
        // update, dan destroy sengaja didaftarkan secara eksplisit agar
        // permintaan ditolak controller dengan pesan sertifikat beku (403),
        // bukan sekadar tidak ditemukan (404).
        Route::resource('sertifikat-sik', SertifikatSikController::class)
            ->only(['index', 'create', 'store']);

        Route::get('/sertifikat-sik/{sertifikatSik}/edit', [
            SertifikatSikController::class,
            'edit',
        ])->name('sertifikat-sik.edit');

        Route::match(['put', 'patch'], '/sertifikat-sik/{sertifikatSik}', [
            SertifikatSikController::class,
            'update',
        ])->name('sertifikat-sik.update');

        Route::delete('/sertifikat-sik/{sertifikatSik}', [
            SertifikatSikController::class,
            'destroy',
        ])->name('sertifikat-sik.destroy');

    });

    // ==================================================
    // FITUR INSTRUKTUR SIK
    // ==================================================

    Route::middleware('role:admin,infrastruktur_sik')->group(function (): void {

        Route::resource('absensi-sik', AbsensiSikController::class)
            ->only(['index', 'store', 'update']);

        Route::post('/absensi-sik/pertemuan/{pertemuan}/ubah-tanggal', [AbsensiSikController::class, 'ubahTanggalPertemuan'])
            ->whereNumber('pertemuan')
            ->name('absensi-sik.pertemuan.ubah-tanggal');

        Route::post('/absensi-sik/pertemuan/{pertemuan}/selesai', [AbsensiSikController::class, 'tandaiPertemuanTerlaksana'])
            ->whereNumber('pertemuan')
            ->name('absensi-sik.pertemuan.selesai');

        Route::get('/rekap-kehadiran-sik', [RekapKehadiranSikController::class, 'index'])
            ->name('rekap-kehadiran-sik.index');
        Route::get('/rekap-kehadiran-sik/export', [RekapKehadiranSikController::class, 'export'])
            ->name('rekap-kehadiran-sik.export');
        Route::get('/rekap-kehadiran-sik/detail', [RekapKehadiranSikController::class, 'detail'])
            ->name('rekap-kehadiran-sik.detail');
        Route::get('/rekap-kehadiran-sik/{rekapKehadiranSik}', [RekapKehadiranSikController::class, 'show'])
            ->whereNumber('rekapKehadiranSik')
            ->name('rekap-kehadiran-sik.show');
    });

    Route::middleware(['auth', 'password.changed', 'role:admin'])->group(function (): void {
        Route::get('/rabu-mengaji', [RabuMengajiController::class, 'index'])
            ->name('rabu-mengaji.index');
        Route::get('/rabu-mengaji/{absensiSik}', [RabuMengajiController::class, 'show'])
            ->whereNumber('absensiSik')
            ->name('rabu-mengaji.show');
        Route::get('/rabu-mengaji/{pertemuan}/input', [RabuMengajiController::class, 'edit'])
            ->whereNumber('pertemuan')
            ->name('rabu-mengaji.input');
        Route::post('/rabu-mengaji', [RabuMengajiController::class, 'store'])
            ->name('rabu-mengaji.store');
        Route::get('/rabu-mengaji/import', [RabuMengajiImportController::class, 'importForm'])
            ->name('rabu-mengaji.import');
        Route::post('/rabu-mengaji/import/preview', [RabuMengajiImportController::class, 'previewImport'])
            ->name('rabu-mengaji.import.preview');
        Route::post('/rabu-mengaji/import/recheck', [RabuMengajiImportController::class, 'recheckImport'])
            ->name('rabu-mengaji.import.recheck');
        Route::post('/rabu-mengaji/import/commit', [RabuMengajiImportController::class, 'commitImport'])
            ->name('rabu-mengaji.import.commit');
        Route::get('/rabu-mengaji/import/template', [RabuMengajiImportController::class, 'downloadTemplate'])
            ->name('rabu-mengaji.import.template');
    });

    Route::middleware('role:admin,infrastruktur_sik')->group(function (): void {
        Route::get('/nilai', [NilaiController::class, 'index'])
            ->name('nilai.index');
    });

    Route::middleware('role:infrastruktur_sik')->group(function (): void {

        Route::post('/nilai', [NilaiController::class, 'store'])
            ->name('nilai.store');
        Route::match(['put', 'patch'], '/nilai/{nilai}', [NilaiController::class, 'update'])
            ->whereNumber('nilai')
            ->name('nilai.update');

        Route::get('/kelompok-saya', [KelompokController::class, 'indexInstruktur'])
            ->name('instruktur.kelompok.index');

        Route::get('/anggota-saya', [AnggotaPeriodeSikController::class, 'indexInstruktur'])
            ->name('instruktur.anggota.index');

    });

    // ==================================================
    // FITUR SANTRI
    // ==================================================

    Route::middleware('role:santri')->group(function (): void {

        Route::get('/nilai-saya', [NilaiController::class, 'transcript'])
            ->name('nilai-saya');

        // Riwayat Absensi Santri
        Route::get('/absensi-saya', [
            AbsensiSikController::class,
            'riwayatSantri',
        ])->name('absensi-saya');

        Route::post('/absensi-saya/{absensiSik}/bukti', [BuktiKetidakhadiranController::class, 'store'])
            ->name('absensi-saya.bukti.store');

        // Sertifikat Santri
        Route::get('/sertifikat-saya', [
            SertifikatSikController::class,
            'sertifikatSantri',
        ])->name('sertifikat-saya');
    });

    // ==================================================
    // FITUR KA-LAB MUTU
    // ==================================================

    Route::middleware('role:ka_lab_mutu')->group(function (): void {

        // Melihat laporan kelulusan dan kehadiran
        Route::get('/laporan-sik', [
            RekapKehadiranSikController::class,
            'laporan',
        ])->name('laporan-sik');

        Route::get('/laporan-sik/{tahun}', [
            RekapKehadiranSikController::class,
            'laporanTahun',
        ])->whereNumber('tahun')->name('laporan-sik.tahun');
    });

    // ==================================================
    // PENGATURAN PROFIL
    // ==================================================

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

// ==================================================
// AUTHENTICATION
// ==================================================

require __DIR__.'/auth.php';
