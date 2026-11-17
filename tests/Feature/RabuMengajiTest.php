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

function makeRabuMengajiFixture(): array
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Mengaji',
        'status' => 'Aktif',
    ]);
    $period = PeriodeSik::query()->create([
        'nama_periode' => 'ganjil_mengaji',
        'tahun' => now()->year,
        'tanggal_mulai' => now()->startOfYear()->toDateString(),
        'tanggal_selesai' => now()->endOfYear()->toDateString(),
        'status' => 'Aktif',
    ]);
    $group = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Mengaji',
        'status' => 'Aktif',
    ]);

    $activity = Kegiatan::query()->create([
        'periode_sik_id' => $period->id,
        'kelompok_id' => $group->id,
        'nama_kegiatan' => 'Rabu Mengaji',
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);
    $otherActivity = Kegiatan::query()->create([
        'periode_sik_id' => $period->id,
        'kelompok_id' => $group->id,
        'nama_kegiatan' => 'teknologi',
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);

    $santri = Santri::query()->create([
        'nomor_induk' => 'MENGAJI-001',
        'nama' => 'Santri Mengaji',
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $membership = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $period->id,
        'kelompok_id' => $group->id,
        'status' => 'Aktif',
    ]);

    $meeting = Pertemuan::query()->create([
        'kegiatan_id' => $activity->id,
        'pertemuan_ke' => 1,
        'tanggal' => '2026-11-18',
        'status' => 'Terlaksana',
    ]);
    $attendance = AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $membership->id,
        'pertemuan_id' => $meeting->id,
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
    ]);

    $otherMeeting = Pertemuan::query()->create([
        'kegiatan_id' => $otherActivity->id,
        'pertemuan_ke' => 1,
        'tanggal' => '2026-11-18',
        'status' => 'Terlaksana',
    ]);
    AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $membership->id,
        'pertemuan_id' => $otherMeeting->id,
        'metode_absensi' => 'Manual',
        'status' => 'Alpha',
    ]);

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

    return compact('admin', 'instructorUser', 'attendance', 'rekap', 'activity');
}

test('rabu mengaji page lists attendance from the existing activity structure', function () {
    $fixture = makeRabuMengajiFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index'))
        ->assertOk()
        ->assertSee('Rabu Mengaji')
        ->assertSee('Santri Mengaji')
        ->assertSee('MENGAJI-001')
        ->assertSee('Kelompok Mengaji')
        ->assertSee('18/11/2026')
        ->assertSee('Detail')
        ->assertDontSee('teknologi');
});

test('rabu mengaji page can search by santri name', function () {
    $fixture = makeRabuMengajiFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index', ['search' => 'Santri Mengaji']))
        ->assertOk()
        ->assertSee('Santri Mengaji');

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index', ['search' => 'Tidak Ada']))
        ->assertOk()
        ->assertSee('Belum ada kehadiran Rabu Mengaji untuk filter terpilih.');
});

test('rabu mengaji page can filter by execution date', function () {
    $fixture = makeRabuMengajiFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index', ['tanggal' => '2026-11-18']))
        ->assertOk()
        ->assertSee('Santri Mengaji');

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index', ['tanggal' => '2026-11-19']))
        ->assertOk()
        ->assertSee('Belum ada kehadiran Rabu Mengaji untuk filter terpilih.');
});

test('rabu mengaji detail shows identity riwayat and rekap', function () {
    $fixture = makeRabuMengajiFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.show', $fixture['attendance']))
        ->assertOk()
        ->assertSee('Santri Mengaji')
        ->assertSee('MENGAJI-001')
        ->assertSee('Kelompok Mengaji')
        ->assertSee('ganjil_mengaji')
        ->assertSee('Rabu Mengaji')
        ->assertSee('18/11/2026')
        ->assertSee('Riwayat pertemuan')
        ->assertSee('Total Hadir')
        ->assertSee('87,5%')
        ->assertSee('Memenuhi');
});

test('instructor can open rabu mengaji data from their own group only', function () {
    $fixture = makeRabuMengajiFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('rabu-mengaji.index'))
        ->assertOk()
        ->assertSee('Santri Mengaji')
        ->assertDontSee('teknologi');

    $otherUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    InfrastrukturSik::query()->create([
        'user_id' => $otherUser->id,
        'nama' => 'Instruktur Lain',
        'status' => 'Aktif',
    ]);

    $this->actingAs($otherUser)
        ->get(route('rabu-mengaji.show', $fixture['attendance']))
        ->assertNotFound();
});

test('santri cannot open the rabu mengaji page', function () {
    makeRabuMengajiFixture();

    $santriUser = User::factory()->create(['role' => UserRole::Santri]);

    $this->actingAs($santriUser)
        ->get(route('rabu-mengaji.index'))
        ->assertForbidden();
});
