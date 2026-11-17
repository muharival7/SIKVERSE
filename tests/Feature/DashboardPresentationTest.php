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

test('admin recap uses the compact table without arrival or departure times', function () {
    $fixture = makeDashboardPresentationFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rekap-kehadiran-sik.index'))
        ->assertOk()
        ->assertSee('Nama Santri')
        ->assertSee('Nomor Induk')
        ->assertSee('Kelompok')
        ->assertSee('Total Pertemuan')
        ->assertSee('Status')
        ->assertSee('Aksi')
        ->assertSee('Detail')
        ->assertDontSee('Jam Datang')
        ->assertDontSee('Jam Pulang')
        ->assertDontSee('08:17')
        ->assertDontSee('12:03')
        ->assertDontSee('Tidak Lengkap');
});

test('instructor recap uses the same compact table and exposes the detail link', function () {
    $fixture = makeDashboardPresentationFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('rekap-kehadiran-sik.index'))
        ->assertOk()
        ->assertSee('Nama Santri')
        ->assertSee('Nomor Induk')
        ->assertSee('Kelompok')
        ->assertSee('Total Pertemuan')
        ->assertSee('Status')
        ->assertSee('Aksi')
        ->assertSee('Detail')
        ->assertDontSee('Jam Datang')
        ->assertDontSee('Jam Pulang')
        ->assertDontSee('08:17')
        ->assertDontSee('12:03')
        ->assertDontSee('<th class="text-center">Hadir</th>', false)
        ->assertDontSee('<th class="text-center">Izin</th>', false)
        ->assertDontSee('<th class="text-center">Sakit</th>', false)
        ->assertDontSee('<th class="text-center">Alpha</th>', false)
        ->assertDontSee('<th class="text-center">Persentase</th>', false)
        ->assertDontSee('<th class="text-center">Tidak Lengkap</th>', false)
        ->assertDontSee('<th>Bukti Izin</th>', false);
});

test('ka-lab dashboard omits the failure section while keeping graduation summaries and charts', function () {
    $fixture = makeDashboardPresentationFixture(presentCount: 11, meetingCount: 16);
    KelulusanSik::query()->create([
        'anggota_periode_sik_id' => $fixture['membership']->id,
        'status' => 'Tidak Lulus',
        'keterangan' => 'Nilai evaluasi belum memenuhi ketentuan.',
    ]);
    $kaLab = User::factory()->create(['role' => UserRole::KaLabMutu]);

    $this->actingAs($kaLab)
        ->get(route('dashboard.ka_lab_mutu'))
        ->assertOk()
        ->assertDontSee('Santri dengan status Tidak Lulus')
        ->assertDontSee('RINCIAN EVALUASI')
        ->assertDontSee('Belum ada santri berstatus Tidak Lulus.')
        ->assertDontSee('Nilai evaluasi belum memenuhi ketentuan.')
        ->assertSee('Total Lulusan Tahun Ini')
        ->assertSee('Evaluasi Mutu Kelulusan')
        ->assertSee('Jumlah Lulusan per Tahun')
        ->assertSee('Perbandingan Jumlah Kelulusan')
        ->assertSee('data-graduation-chart="line"', false)
        ->assertSee('data-graduation-chart="comparison"', false);
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
        ->assertSee('sd-santri-dashboard', false)
        ->assertSee('sd-trend-card--compact')
        ->assertSee('data-chart-size="compact"', false);
});
