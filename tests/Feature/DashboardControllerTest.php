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

function createDashboardFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $activePeriod = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Aktif '.$suffix,
        'tahun' => now()->year,
        'tanggal_mulai' => now()->startOfYear()->toDateString(),
        'tanggal_selesai' => now()->endOfYear()->toDateString(),
        'status' => 'Aktif',
    ]);

    $inactivePeriod = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Tidak Aktif '.$suffix,
        'tahun' => now()->year - 1,
        'tanggal_mulai' => now()->subYear()->startOfYear()->toDateString(),
        'tanggal_selesai' => now()->subYear()->endOfYear()->toDateString(),
        'status' => 'Tidak Aktif',
    ]);

    $instrukturUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instruktur = InfrastrukturSik::query()->create([
        'user_id' => $instrukturUser->id,
        'nama' => 'Instruktur Uji '.$suffix,
        'status' => 'Aktif',
    ]);

    $group = Kelompok::query()->create([
        'periode_sik_id' => $activePeriod->id,
        'instruktur_id' => $instruktur->id,
        'nama_kelompok' => 'Kelompok Uji '.$suffix,
        'status' => 'Aktif',
    ]);

    $santri = Santri::query()->create([
        'nomor_induk' => 'SIK-DASH-'.$suffix,
        'nama' => 'Santri Dashboard Uji',
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);

    $membership = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $activePeriod->id,
        'kelompok_id' => $group->id,
        'status' => 'Aktif',
    ]);

    $activity = Kegiatan::query()->create([
        'periode_sik_id' => $activePeriod->id,
        'kelompok_id' => $group->id,
        'nama_kegiatan' => 'Kegiatan Uji '.$suffix,
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);

    return compact('admin', 'activePeriod', 'inactivePeriod', 'instruktur', 'group', 'santri', 'membership', 'activity');
}

function createTerlaksanaMeetings(array $fixture, int $count): array
{
    $meetings = [];

    for ($number = 1; $number <= $count; $number++) {
        $meeting = Pertemuan::query()->create([
            'kegiatan_id' => $fixture['activity']->id,
            'pertemuan_ke' => $number,
            'tanggal' => now()->startOfYear()->addDays($number - 1)->toDateString(),
            'status' => 'Terlaksana',
        ]);

        $meetings[] = $meeting;
    }

    return $meetings;
}

function createAbsensiRecords(array $fixture, Pertemuan $meeting, array $statuses): void
{
    foreach ($statuses as $index => $status) {
        $suffix = fake()->unique()->numerify('######');

        $santri = Santri::query()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Santri])->id,
            'nomor_induk' => 'SIK-DASH-'.$suffix,
            'nama' => 'Santri Absensi '.$suffix,
            'jenis_kelamin' => 'Laki-laki',
            'status' => 'Aktif',
        ]);

        $membership = AnggotaPeriodeSik::query()->create([
            'santri_id' => $santri->id,
            'periode_sik_id' => $fixture['activePeriod']->id,
            'kelompok_id' => $fixture['group']->id,
            'status' => 'Aktif',
        ]);

        AbsensiSik::query()->create([
            'anggota_periode_sik_id' => $membership->id,
            'pertemuan_id' => $meeting->id,
            'waktu_masuk' => '08:00:00',
            'waktu_pulang' => '12:00:00',
            'metode_absensi' => 'Manual',
            'status' => $status,
        ]);

        RekapKehadiranSik::query()->create([
            'anggota_periode_sik_id' => $membership->id,
            'kegiatan_id' => $fixture['activity']->id,
            'total_pertemuan' => 1,
            'jumlah_hadir' => $status === 'Hadir' ? 1 : 0,
            'jumlah_izin' => $status === 'Izin' ? 1 : 0,
            'jumlah_sakit' => $status === 'Sakit' ? 1 : 0,
            'jumlah_alpha' => $status === 'Alpha' ? 1 : 0,
            'jumlah_tidak_lengkap' => $status === 'Tidak Lengkap' ? 1 : 0,
            'persentase_kehadiran' => $status === 'Hadir' ? 100.00 : 0.00,
            'status_kehadiran' => $status === 'Hadir' ? 'Memenuhi' : 'Belum Memenuhi',
        ]);
    }
}

test('admin dashboard menampilkan nol dan status kosong ketika tidak ada rekap kehadiran', function () {
    $fixture = createDashboardFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('dashboard.admin'))
        ->assertOk()
        ->assertSee('—')
        ->assertSee('0')
        ->assertSee('Belum Ada Pertemuan Terlaksana', false);
});

test('admin dashboard menampilkan statistik kehadiran yang konsisten dengan status Izin, Sakit, dan Alpha', function () {
    $fixture = createDashboardFixture();
    $meetings = createTerlaksanaMeetings($fixture, 1);

    createAbsensiRecords($fixture, $meetings[0], ['Hadir', 'Hadir', 'Izin', 'Sakit', 'Alpha']);

    $this->actingAs($fixture['admin'])
        ->get(route('dashboard.admin'))
        ->assertOk()
        ->assertSee('2')
        ->assertSee('1')
        ->assertSee('1')
        ->assertSee('1')
        ->assertSee('1');
});

test('admin dashboard tidak menampilkan kartu rekap absensi dosen dan rabu mengaji', function () {
    $fixture = createDashboardFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('dashboard.admin'))
        ->assertOk()
        ->assertDontSee('Kehadiran Dosen')
        ->assertDontSee('Kehadiran Kegiatan Mengaji')
        ->assertDontSee('Buka Rekap Absensi Dosen')
        ->assertDontSee('Buka Rabu Mengaji');
});

test('menu sidebar rekap absensi dosen dan rabu mengaji tetap tersedia untuk admin', function () {
    $fixture = createDashboardFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('dashboard.admin'))
        ->assertOk()
        ->assertSee('Rekap Absensi Dosen')
        ->assertSee('Rabu Mengaji')
        ->assertSee(route('rekap-absensi-dosen.index'))
        ->assertSee(route('rabu-mengaji.index'));
});

test('admin dashboard hanya menghitung pertemuan dengan status Terlaksana', function () {
    $fixture = createDashboardFixture();

    $terjadwalMeeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 1,
        'tanggal' => now()->startOfYear()->toDateString(),
        'status' => 'Terjadwal',
    ]);

    $dibatalkanMeeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => now()->startOfYear()->addDay()->toDateString(),
        'status' => 'Dibatalkan',
    ]);

    AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $fixture['membership']->id,
        'pertemuan_id' => $terjadwalMeeting->id,
        'waktu_masuk' => '08:00:00',
        'waktu_pulang' => '12:00:00',
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
    ]);

    AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $fixture['membership']->id,
        'pertemuan_id' => $dibatalkanMeeting->id,
        'waktu_masuk' => '08:00:00',
        'waktu_pulang' => '12:00:00',
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('dashboard.admin'))
        ->assertOk()
        ->assertSee('0')
        ->assertSee('—')
        ->assertDontSee('Hadir</span><strong>1')
        ->assertDontSee('Izin</span><strong>1')
        ->assertDontSee('Sakit</span><strong>1')
        ->assertDontSee('Alpha</span><strong>1');
});

test('admin dashboard mengikuti filter periode SIK aktif', function () {
    $fixture = createDashboardFixture();
    $activeMeetings = createTerlaksanaMeetings($fixture, 1);

    $inactiveActivity = Kegiatan::query()->create([
        'periode_sik_id' => $fixture['inactivePeriod']->id,
        'kelompok_id' => null,
        'nama_kegiatan' => 'Kegiatan Periode Tidak Aktif',
        'jenis_kegiatan' => 'Aula',
        'status' => 'Aktif',
    ]);

    $inactiveMeeting = Pertemuan::query()->create([
        'kegiatan_id' => $inactiveActivity->id,
        'pertemuan_ke' => 1,
        'tanggal' => now()->subYear()->startOfYear()->toDateString(),
        'status' => 'Terlaksana',
    ]);

    createAbsensiRecords($fixture, $activeMeetings[0], ['Hadir']);
    createAbsensiRecords($fixture, $inactiveMeeting, ['Hadir']);

    $this->actingAs($fixture['admin'])
        ->get(route('dashboard.admin'))
        ->assertOk()
        ->assertSee('1')
        ->assertSee($fixture['activePeriod']->nama_periode);
});
