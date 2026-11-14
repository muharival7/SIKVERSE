<?php

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\KelulusanSik;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\RekapKehadiranSik;
use App\Models\Santri;
use App\Models\User;

function makeDashboardPresentationFixture(int $presentCount = 1, int $meetingCount = 1): array
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Uji',
        'status' => 'Aktif',
    ]);
    $period = PeriodeSik::query()->create([
        'nama_periode' => 'ganjil_'.now()->year,
        'tahun' => now()->year,
        'tanggal_mulai' => now()->startOfYear()->toDateString(),
        'tanggal_selesai' => now()->endOfYear()->toDateString(),
        'status' => 'Aktif',
    ]);
    $group = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Uji',
        'status' => 'Aktif',
    ]);
    $santri = Santri::query()->create([
        'nomor_induk' => 'SIK-UJI-001',
        'nama' => 'Santri Uji',
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $membership = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $period->id,
        'kelompok_id' => $group->id,
        'status' => 'Aktif',
    ]);
    $activity = Kegiatan::query()->create([
        'periode_sik_id' => $period->id,
        'kelompok_id' => $group->id,
        'nama_kegiatan' => 'Kegiatan Uji',
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);

    for ($number = 1; $number <= $meetingCount; $number++) {
        $meeting = Pertemuan::query()->create([
            'kegiatan_id' => $activity->id,
            'pertemuan_ke' => $number,
            'tanggal' => now()->startOfYear()->addDays($number - 1)->toDateString(),
            'status' => 'Terlaksana',
        ]);

        AbsensiSik::query()->create([
            'anggota_periode_sik_id' => $membership->id,
            'pertemuan_id' => $meeting->id,
            'waktu_masuk' => $number === 1 ? '08:17:00' : null,
            'waktu_pulang' => $number === 1 ? '12:03:00' : null,
            'metode_absensi' => 'Manual',
            'status' => $number <= $presentCount ? 'Hadir' : 'Alpha',
        ]);
    }

    RekapKehadiranSik::query()->create([
        'anggota_periode_sik_id' => $membership->id,
        'kegiatan_id' => $activity->id,
        'total_pertemuan' => max($meetingCount, 1),
        'jumlah_hadir' => $presentCount,
        'jumlah_izin' => 0,
        'jumlah_sakit' => 0,
        'jumlah_alpha' => max(0, $meetingCount - $presentCount),
        'jumlah_tidak_lengkap' => 0,
        'persentase_kehadiran' => $meetingCount > 0 ? ($presentCount / $meetingCount) * 100 : 0,
        'status_kehadiran' => 'Belum Memenuhi',
    ]);

    return compact('admin', 'instructorUser', 'period', 'membership', 'santri');
}

test('admin recap shows actual arrival and departure times from attendance records', function () {
    $fixture = makeDashboardPresentationFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rekap-kehadiran-sik.index'))
        ->assertOk()
        ->assertSee('Jam Datang')
        ->assertSee('Jam Pulang')
        ->assertSee('08:17')
        ->assertSee('12:03')
        ->assertDontSee('Tidak Lengkap');
});

test('instructor recap shows separate arrival and departure times from attendance records', function () {
    $fixture = makeDashboardPresentationFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('rekap-kehadiran-sik.index'))
        ->assertOk()
        ->assertSee('Jam Datang')
        ->assertSee('Jam Pulang')
        ->assertSee('08:17')
        ->assertSee('12:03')
        ->assertSee('Total Pertemuan')
        ->assertSee('Hadir')
        ->assertSee('Izin')
        ->assertSee('Sakit')
        ->assertSee('Alpha')
        ->assertSee('Persentase')
        ->assertSee('Status')
        ->assertDontSee('<th class="text-center">Tidak Lengkap</th>', false)
        ->assertDontSee('<th>Bukti Izin</th>', false);
});

test('ka-lab dashboard explains failure using attendance data and configured threshold', function () {
    $fixture = makeDashboardPresentationFixture(presentCount: 11, meetingCount: 16);
    $failure = KelulusanSik::query()->create([
        'anggota_periode_sik_id' => $fixture['membership']->id,
        'status' => 'Tidak Lulus',
        'keterangan' => 'Nilai evaluasi belum memenuhi ketentuan.',
    ]);
    $kaLab = User::factory()->create(['role' => UserRole::KaLabMutu]);

    $this->actingAs($kaLab)
        ->get(route('dashboard.ka_lab_mutu'))
        ->assertOk()
        ->assertSee('Santri dengan status Tidak Lulus')
        ->assertSee('11/16')
        ->assertSee('68.8%')
        ->assertSee('5')
        ->assertSee('14 kali')
        ->assertSee('kurang 3 kali')
        ->assertSee('Nilai evaluasi belum memenuhi ketentuan.');
});

test('santri dashboard renders its attendance analysis in compact chart mode', function () {
    $santriUser = User::factory()->create(['role' => UserRole::Santri]);
    Santri::query()->create([
        'user_id' => $santriUser->id,
        'nomor_induk' => 'SIK-UJI-002',
        'nama' => 'Santri Uji',
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);

    $this->actingAs($santriUser)
        ->get(route('dashboard.santri'))
        ->assertOk()
        ->assertSee('sd-trend-card--compact')
        ->assertSee('data-chart-size="compact"', false);
});
