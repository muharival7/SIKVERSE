<?php

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\RekapKehadiranSik;
use App\Models\Santri;
use App\Models\User;
use App\Services\AttendanceRecapService;

function createRecapServiceFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instrukturUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instruktur = InfrastrukturSik::query()->create([
        'user_id' => $instrukturUser->id,
        'nama' => 'Instruktur Rekap '.$suffix,
        'status' => 'Aktif',
    ]);
    $periode = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Rekap '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompok = Kelompok::query()->create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instruktur->id,
        'nama_kelompok' => 'Kelompok Rekap '.$suffix,
        'status' => 'Aktif',
    ]);
    $santri = Santri::query()->create([
        'nomor_induk' => 'RKS-'.$suffix,
        'nama' => 'Santri Rekap '.$suffix,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $anggota = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'status' => 'Aktif',
    ]);
    $kegiatan = Kegiatan::query()->create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'nama_kegiatan' => 'Kegiatan Rekap '.$suffix,
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);

    return compact('admin', 'instrukturUser', 'instruktur', 'periode', 'kelompok', 'santri', 'anggota', 'kegiatan');
}

function createRecapMeeting(array $fixture, int $number, string $status): Pertemuan
{
    return Pertemuan::query()->create([
        'kegiatan_id' => $fixture['kegiatan']->id,
        'pertemuan_ke' => $number,
        'tanggal' => '2026-06-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
        'status' => $status,
        'kelompok_host_id' => $fixture['kelompok']->id,
    ]);
}

function createRecapAbsensi(array $fixture, Pertemuan $meeting, string $status): void
{
    AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $meeting->id,
        'metode_absensi' => 'Manual',
        'status' => $status,
    ]);
}

test('ambang 85 persen dibandingkan dengan integer tanpa pembagian nol', function () {
    $service = app(AttendanceRecapService::class);

    expect($service->meetsThreshold(17, 20))->toBeTrue()
        ->and($service->meetsThreshold(16, 20))->toBeFalse()
        ->and($service->meetsThreshold(7, 8))->toBeTrue()
        ->and($service->meetsThreshold(0, 0))->toBeFalse()
        ->and($service->minimumPercentage())->toBe(85.0);
});

test('rekap hanya menghitung pertemuan Terlaksana', function () {
    $fixture = createRecapServiceFixture();

    foreach (['Terlaksana', 'Terlaksana'] as $index => $status) {
        $meeting = createRecapMeeting($fixture, $index + 1, $status);
        createRecapAbsensi($fixture, $meeting, 'Hadir');
    }

    $scheduled = createRecapMeeting($fixture, 3, 'Terjadwal');
    createRecapAbsensi($fixture, $scheduled, 'Alpha');
    $cancelled = createRecapMeeting($fixture, 4, 'Dibatalkan');
    createRecapAbsensi($fixture, $cancelled, 'Alpha');

    $metric = app(AttendanceRecapService::class)
        ->calculate((int) $fixture['anggota']->id, (int) $fixture['kegiatan']->id);

    expect($metric['total_pertemuan'])->toBe(2)
        ->and($metric['jumlah_hadir'])->toBe(2)
        ->and($metric['jumlah_alpha'])->toBe(0)
        ->and($metric['status'])->toBe(AttendanceRecapService::STATUS_MET);
});

test('tanpa pertemuan Terlaksana rekap memakai status khusus tanpa persentase', function () {
    $fixture = createRecapServiceFixture();

    $scheduled = createRecapMeeting($fixture, 1, 'Terjadwal');
    createRecapAbsensi($fixture, $scheduled, 'Hadir');
    $cancelled = createRecapMeeting($fixture, 2, 'Dibatalkan');
    createRecapAbsensi($fixture, $cancelled, 'Hadir');

    $metric = app(AttendanceRecapService::class)
        ->calculate((int) $fixture['anggota']->id, (int) $fixture['kegiatan']->id);

    expect($metric['total_pertemuan'])->toBe(0)
        ->and($metric['persentase'])->toBeNull()
        ->and($metric['status'])->toBe(AttendanceRecapService::STATUS_NO_MEETINGS)
        ->and($metric['memenuhi'])->toBeFalse();
});

test('perubahan status pertemuan memicu sinkronisasi rekap', function () {
    $fixture = createRecapServiceFixture();
    $meeting = createRecapMeeting($fixture, 1, 'Terjadwal');
    createRecapAbsensi($fixture, $meeting, 'Hadir');

    app(AttendanceRecapService::class)->syncForPertemuan($meeting);
    $sebelum = RekapKehadiranSik::query()->firstOrFail();
    expect($sebelum->status_kehadiran)->toBe(AttendanceRecapService::STATUS_NO_MEETINGS);

    $this->actingAs($fixture['admin'])
        ->put(route('pertemuan.update', $meeting), [
            'kegiatan_id' => $fixture['kegiatan']->id,
            'pertemuan_ke' => 1,
            'tanggal' => '2026-06-01',
            'status' => 'Terlaksana',
            'kelompok_host_id' => $fixture['kelompok']->id,
        ])
        ->assertRedirect(route('pertemuan.index'));

    $sesudah = RekapKehadiranSik::query()->firstOrFail();
    expect($sesudah->total_pertemuan)->toBe(1)
        ->and($sesudah->jumlah_hadir)->toBe(1)
        ->and($sesudah->status_kehadiran)->toBe(AttendanceRecapService::STATUS_MET);
});

test('dry run bersifat read-only dan merangkum baris terdampak', function () {
    $fixture = createRecapServiceFixture();

    RekapKehadiranSik::query()->create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'kegiatan_id' => $fixture['kegiatan']->id,
        'total_pertemuan' => 16,
        'jumlah_hadir' => 14,
        'jumlah_izin' => 0,
        'jumlah_sakit' => 0,
        'jumlah_alpha' => 0,
        'jumlah_tidak_lengkap' => 0,
        'persentase_kehadiran' => 87.5,
        'status_kehadiran' => 'Memenuhi',
    ]);

    $this->artisan('sik:recap-dry-run')->assertSuccessful();

    $rekap = RekapKehadiranSik::query()->firstOrFail();
    expect((int) $rekap->total_pertemuan)->toBe(16)
        ->and((string) $rekap->status_kehadiran)->toBe('Memenuhi');
});
