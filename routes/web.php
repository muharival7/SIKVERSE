
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

        return redirect()->route($user->role->dashboardRoute());

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

        // Manajemen Sertifikat
        Route::resource('sertifikat-sik', SertifikatSikController::class)
            ->except(['show']);
    });

    // ==================================================
    // FITUR INSTRUKTUR SIK
    // ==================================================

    Route::middleware('role:admin,infrastruktur_sik')->group(function (): void {

        Route::resource('absensi-sik', AbsensiSikController::class)
            ->only(['index', 'store', 'update']);

        Route::get('/rekap-kehadiran-sik', [RekapKehadiranSikController::class, 'index'])
            ->name('rekap-kehadiran-sik.index');
        Route::get('/rekap-kehadiran-sik/export', [RekapKehadiranSikController::class, 'export'])
            ->name('rekap-kehadiran-sik.export');
    });

    Route::middleware('role:infrastruktur_sik')->group(function (): void {

        Route::resource('nilai', NilaiController::class)
            ->only(['index', 'store', 'update']);

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
