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
    $otherInstructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $otherInstructor = InfrastrukturSik::query()->create([
        'user_id' => $otherInstructorUser->id,
        'nama' => 'Instruktur Sains',
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
        'nama_kelompok' => 'Sistem Informasi',
        'status' => 'Aktif',
    ]);
    $otherGroup = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $otherInstructor->id,
        'nama_kelompok' => 'Sains',
        'status' => 'Aktif',
    ]);

    $activity = Kegiatan::query()->create([
        'periode_sik_id' => $period->id,
        'kelompok_id' => null,
        'nama_kegiatan' => 'Rabu Mengaji',
        'jenis_kegiatan' => 'Aula',
        'status' => 'Aktif',
    ]);
    $otherActivity = Kegiatan::query()->create([
        'periode_sik_id' => $period->id,
        'kelompok_id' => null,
        'nama_kegiatan' => 'teknologi',
        'jenis_kegiatan' => 'Aula',
        'status' => 'Aktif',
    ]);

    $santri = Santri::query()->create([
        'nomor_induk' => 'MENGAJI-001',
        'nama' => 'Santri Sistem Informasi',
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $membership = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $period->id,
        'kelompok_id' => $group->id,
        'status' => 'Aktif',
    ]);
    $otherSantri = Santri::query()->create([
        'nomor_induk' => 'MENGAJI-002',
        'nama' => 'Santri Sains',
        'jenis_kelamin' => 'Perempuan',
        'status' => 'Aktif',
    ]);
    $otherMembership = AnggotaPeriodeSik::query()->create([
        'santri_id' => $otherSantri->id,
        'periode_sik_id' => $period->id,
        'kelompok_id' => $otherGroup->id,
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

    return compact(
        'admin',
        'instructorUser',
        'otherInstructorUser',
        'group',
        'otherGroup',
        'santri',
        'otherSantri',
        'membership',
        'otherMembership',
        'attendance',
        'rekap',
        'activity'
    );
}

test('rabu mengaji page lists attendance from the existing activity structure', function () {
    $fixture = makeRabuMengajiFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index'))
        ->assertOk()
        ->assertSee('Rabu Mengaji')
        ->assertSee('Santri Sistem Informasi')
        ->assertSee('MENGAJI-001')
        ->assertSee('Sistem Informasi')
        ->assertSee('18/11/2026')
        ->assertSee('Detail')
        ->assertDontSee('teknologi');
});

test('rabu mengaji page can search by santri name', function () {
    $fixture = makeRabuMengajiFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index', ['search' => 'Santri Sistem Informasi']))
        ->assertOk()
        ->assertSee('Santri Sistem Informasi');

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
        ->assertSee('Santri Sistem Informasi');

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
        ->assertSee('Santri Sistem Informasi')
        ->assertSee('MENGAJI-001')
        ->assertSee('Sistem Informasi')
        ->assertSee('ganjil_mengaji')
        ->assertSee('Rabu Mengaji')
        ->assertSee('18/11/2026')
        ->assertSee('Riwayat pertemuan')
        ->assertSee('Total Hadir')
        ->assertSee('87,5%')
        ->assertSee('Memenuhi');
});

test('input kehadiran button appears on index for active meeting', function () {
    $fixture = makeRabuMengajiFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index'))
        ->assertOk()
        ->assertSee('Input Kehadiran');
});

test('input kehadiran button does not appear on index for cancelled meeting row', function () {
    $fixture = makeRabuMengajiFixture();

    $cancelledMeeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-25',
        'status' => 'Dibatalkan',
    ]);
    AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $fixture['attendance']->anggota_periode_sik_id,
        'pertemuan_id' => $cancelledMeeting->id,
        'metode_absensi' => 'Manual',
        'status' => 'Alpha',
    ]);

    $response = $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index'))
        ->assertOk();

    // Active meeting row still renders Input Kehadiran button
    $response->assertSee('Input Kehadiran');

    // Only one Input Kehadiran button total (cancelled row does not render it)
    $content = $response->getContent();
    $buttonCount = substr_count($content, 'Input Kehadiran');
    expect($buttonCount)->toBe(1);
});

test('input page receives pertemuan object not absensi id', function () {
    $fixture = makeRabuMengajiFixture();

    $meeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-25',
        'status' => 'Terlaksana',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.input', $meeting))
        ->assertOk()
        ->assertSee('Input Kehadiran Rabu Mengaji');
});

test('instructor can open rabu mengaji data for every group in the same period', function () {
    $fixture = makeRabuMengajiFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('rabu-mengaji.index'))
        ->assertOk()
        ->assertSee('Santri Sistem Informasi')
        ->assertSee('Sains')
        ->assertDontSee('teknologi');

    $this->actingAs($fixture['otherInstructorUser'])
        ->get(route('rabu-mengaji.show', $fixture['attendance']))
        ->assertOk()
        ->assertSee('Santri Sistem Informasi');
});

test('santri cannot open the rabu mengaji page', function () {
    makeRabuMengajiFixture();

    $santriUser = User::factory()->create(['role' => UserRole::Santri]);

    $this->actingAs($santriUser)
        ->get(route('rabu-mengaji.index'))
        ->assertForbidden();
});

test('admin can input attendance for rabu mengaji meeting', function () {
    $fixture = makeRabuMengajiFixture();

    $otherMembership = $fixture['otherMembership'];

    $meeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-25',
        'status' => 'Terlaksana',
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.store'), [
            'pertemuan_id' => $meeting->id,
            'status' => [
                $fixture['attendance']->anggota_periode_sik_id => 'Hadir',
                $otherMembership->id => 'Izin',
            ],
            'keterangan' => [
                $otherMembership->id => 'Izin sakit',
            ],
        ])
        ->assertRedirect(route('rabu-mengaji.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $otherMembership->id,
        'pertemuan_id' => $meeting->id,
        'status' => 'Izin',
        'keterangan' => 'Izin sakit',
        'metode_absensi' => 'Manual',
    ]);
});

test('instruktur_siK can input attendance for shared rabu mengaji activity', function () {
    $fixture = makeRabuMengajiFixture();

    $meeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-25',
        'status' => 'Terlaksana',
    ]);

    $this->actingAs($fixture['instructorUser'])
        ->post(route('rabu-mengaji.store'), [
            'pertemuan_id' => $meeting->id,
            'status' => [
                $fixture['attendance']->anggota_periode_sik_id => 'Sakit',
            ],
        ])
        ->assertRedirect(route('rabu-mengaji.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['attendance']->anggota_periode_sik_id,
        'pertemuan_id' => $meeting->id,
        'status' => 'Sakit',
    ]);
});

test('instruktur_siK can input attendance for santri from another group', function () {
    $fixture = makeRabuMengajiFixture();

    $meeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-25',
        'status' => 'Terlaksana',
    ]);

    $this->actingAs($fixture['instructorUser'])
        ->post(route('rabu-mengaji.store'), [
            'pertemuan_id' => $meeting->id,
            'status' => [
                $fixture['otherMembership']->id => 'Hadir',
            ],
        ])
        ->assertRedirect(route('rabu-mengaji.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['otherMembership']->id,
        'pertemuan_id' => $meeting->id,
        'status' => 'Hadir',
    ]);
});

test('santri cannot input attendance', function () {
    $fixture = makeRabuMengajiFixture();

    $santriUser = User::factory()->create(['role' => UserRole::Santri]);

    $meeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-25',
        'status' => 'Terlaksana',
    ]);

    $this->actingAs($santriUser)
        ->post(route('rabu-mengaji.store'), [
            'pertemuan_id' => $meeting->id,
            'status' => [
                $fixture['attendance']->anggota_periode_sik_id => 'Hadir',
            ],
        ])
        ->assertForbidden();
});

test('ka lab mutu cannot input attendance', function () {
    $fixture = makeRabuMengajiFixture();

    $kaLabUser = User::factory()->create(['role' => UserRole::KaLabMutu]);

    $meeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-25',
        'status' => 'Terlaksana',
    ]);

    $this->actingAs($kaLabUser)
        ->post(route('rabu-mengaji.store'), [
            'pertemuan_id' => $meeting->id,
            'status' => [
                $fixture['attendance']->anggota_periode_sik_id => 'Hadir',
            ],
        ])
        ->assertForbidden();
});

test('cancelled meeting cannot receive attendance', function () {
    $fixture = makeRabuMengajiFixture();

    $cancelledMeeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-25',
        'status' => 'Dibatalkan',
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.store'), [
            'pertemuan_id' => $cancelledMeeting->id,
            'status' => [
                $fixture['attendance']->anggota_periode_sik_id => 'Hadir',
            ],
        ])
        ->assertSessionHasErrors();

    $this->assertDatabaseMissing('absensi_sik', [
        'pertemuan_id' => $cancelledMeeting->id,
    ]);
});

test('duplicate attendance is skipped', function () {
    $fixture = makeRabuMengajiFixture();

    $meeting = $fixture['attendance']->pertemuan;

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.store'), [
            'pertemuan_id' => $meeting->id,
            'status' => [
                $fixture['attendance']->anggota_periode_sik_id => 'Alpha',
            ],
        ])
        ->assertSessionHasErrors();

    $this->assertDatabaseHas('absensi_sik', [
        'id' => $fixture['attendance']->id,
        'status' => 'Hadir',
    ]);
});

test('invalid status is rejected', function () {
    $fixture = makeRabuMengajiFixture();

    $meeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-25',
        'status' => 'Terlaksana',
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.store'), [
            'pertemuan_id' => $meeting->id,
            'status' => [
                $fixture['attendance']->anggota_periode_sik_id => 'Cuti',
            ],
        ])
        ->assertSessionHasErrors();
});

test('recap is synchronized after attendance saved', function () {
    $fixture = makeRabuMengajiFixture();

    $meeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-25',
        'status' => 'Terlaksana',
    ]);

    $otherMembership = $fixture['otherMembership'];

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.store'), [
            'pertemuan_id' => $meeting->id,
            'status' => [
                $otherMembership->id => 'Hadir',
            ],
        ])
        ->assertRedirect(route('rabu-mengaji.index'));

    $this->assertDatabaseHas('rekap_kehadiran_sik', [
        'anggota_periode_sik_id' => $otherMembership->id,
        'kegiatan_id' => $fixture['activity']->id,
        'jumlah_hadir' => 1,
    ]);
});

test('input page is accessible for admin', function () {
    $fixture = makeRabuMengajiFixture();

    $meeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-25',
        'status' => 'Terlaksana',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.input', $meeting->id))
        ->assertOk()
        ->assertSee('Input Kehadiran Rabu Mengaji')
        ->assertSee('Semua Kelompok')
        ->assertSee('Santri Sistem Informasi')
        ->assertSee('Santri Sains')
        ->assertSee('MENGAJI-001')
        ->assertSee('MENGAJI-002');
});

test('input page shows existing attendance as disabled', function () {
    $fixture = makeRabuMengajiFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.input', $fixture['attendance']->pertemuan->id))
        ->assertOk()
        ->assertSee('Sudah tercatat');
});
