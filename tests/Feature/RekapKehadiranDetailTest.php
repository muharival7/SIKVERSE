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

function makeRekapDetailFixture(): array
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Detail',
        'status' => 'Aktif',
    ]);
    $period = PeriodeSik::query()->create([
        'nama_periode' => 'ganjil_detail',
        'tahun' => now()->year,
        'tanggal_mulai' => now()->startOfYear()->toDateString(),
        'tanggal_selesai' => now()->endOfYear()->toDateString(),
        'status' => 'Aktif',
    ]);
    $group = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Detail',
        'status' => 'Aktif',
    ]);
    $santri = Santri::query()->create([
        'nomor_induk' => 'SIK-DETAIL-001',
        'nama' => 'Santri Detail',
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
        'nama_kegiatan' => 'Kegiatan Detail',
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);
    $statuses = array_merge(
        array_fill(0, 14, 'Hadir'),
        ['Izin', 'Sakit']
    );

    foreach ($statuses as $index => $status) {
        $meeting = Pertemuan::query()->create([
            'kegiatan_id' => $activity->id,
            'pertemuan_ke' => $index + 1,
            'tanggal' => sprintf('2026-11-%02d', $index + 1),
            'status' => 'Terlaksana',
        ]);
        AbsensiSik::query()->create([
            'anggota_periode_sik_id' => $membership->id,
            'pertemuan_id' => $meeting->id,
            'waktu_masuk' => '08:17:00',
            'waktu_pulang' => '12:03:00',
            'metode_absensi' => 'Manual',
            'status' => $status,
        ]);
    }
    $rekap = RekapKehadiranSik::query()->create([
        'anggota_periode_sik_id' => $membership->id,
        'kegiatan_id' => $activity->id,
        'total_pertemuan' => 16,
        'jumlah_hadir' => 14,
        'jumlah_izin' => 1,
        'jumlah_sakit' => 1,
        'jumlah_alpha' => 0,
        'jumlah_tidak_lengkap' => 0,
        'persentase_kehadiran' => 87.5,
        'status_kehadiran' => 'Memenuhi',
    ]);

    return compact('admin', 'instructorUser', 'instructor', 'period', 'group', 'santri', 'rekap');
}

test('recap detail shows complete attendance information without arrival or departure times', function () {
    $fixture = makeRekapDetailFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rekap-kehadiran-sik.show', $fixture['rekap']))
        ->assertOk()
        ->assertSee('Santri Detail')
        ->assertSee('SIK-DETAIL-001')
        ->assertSee('Kelompok Detail')
        ->assertSee('ganjil_detail')
        ->assertSee('Kegiatan Detail')
        ->assertSee('05/11/2026')
        ->assertSee('Total Hadir')
        ->assertSee('Total Izin')
        ->assertSee('Total Sakit')
        ->assertSee('Total Alpha')
        ->assertSee('87,5%')
        ->assertSee('Memenuhi')
        ->assertSee('Terlaksana')
        ->assertDontSee('Jam Datang')
        ->assertDontSee('Jam Pulang')
        ->assertDontSee('08:17')
        ->assertDontSee('12:03');
});

test('recap detail uses the same figures as the recap index', function () {
    $fixture = makeRekapDetailFixture();

    $indexResponse = $this->actingAs($fixture['instructorUser'])
        ->get(route('rekap-kehadiran-sik.index'))
        ->assertOk();

    expect($indexResponse->getContent())->toContain('87,5%');

    $this->actingAs($fixture['instructorUser'])
        ->get(route('rekap-kehadiran-sik.show', $fixture['rekap']))
        ->assertOk()
        ->assertSee('87,5%')
        ->assertSee('Memenuhi');
});

test('instructor cannot open a recap outside their own group', function () {
    $fixture = makeRekapDetailFixture();

    $otherUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    InfrastrukturSik::query()->create([
        'user_id' => $otherUser->id,
        'nama' => 'Instruktur Lain',
        'status' => 'Aktif',
    ]);

    $this->actingAs($otherUser)
        ->get(route('rekap-kehadiran-sik.show', $fixture['rekap']))
        ->assertNotFound();
});

test('santri cannot open the recap detail page', function () {
    $fixture = makeRekapDetailFixture();

    $santriUser = User::factory()->create(['role' => UserRole::Santri]);

    $this->actingAs($santriUser)
        ->get(route('rekap-kehadiran-sik.show', $fixture['rekap']))
        ->assertForbidden();
});
