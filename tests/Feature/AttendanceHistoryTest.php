<?php

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\RiwayatPerubahanAbsensi;
use App\Models\Santri;
use App\Models\User;

/** @return array<string, mixed> */
function createAttendanceHistoryFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Riwayat '.$suffix,
        'status' => 'Aktif',
    ]);
    $periode = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Riwayat '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompok = Kelompok::query()->create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Riwayat '.$suffix,
        'status' => 'Aktif',
    ]);
    $kegiatan = Kegiatan::query()->create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'nama_kegiatan' => 'Kegiatan Riwayat '.$suffix,
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);
    $pertemuan = Pertemuan::query()->create([
        'kegiatan_id' => $kegiatan->id,
        'pertemuan_ke' => 1,
        'tanggal' => '2026-06-15',
        'status' => 'Terjadwal',
        'kelompok_host_id' => $kelompok->id,
    ]);
    $santri = Santri::query()->create([
        'nomor_induk' => 'RIW-'.$suffix,
        'nama' => 'Santri Riwayat '.$suffix,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $anggota = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'status' => 'Aktif',
    ]);
    $absensi = AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $anggota->id,
        'pertemuan_id' => $pertemuan->id,
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
        'dicatat_oleh' => $instructor->id,
    ]);

    return compact(
        'admin',
        'instructorUser',
        'instructor',
        'periode',
        'kelompok',
        'kegiatan',
        'pertemuan',
        'santri',
        'anggota',
        'absensi'
    );
}

test('perubahan status absensi oleh admin dicatat sebagai riwayat baru', function () {
    $fixture = createAttendanceHistoryFixture();

    $this->actingAs($fixture['admin'])
        ->put(route('absensi-sik.update', $fixture['absensi']), [
            'status' => 'Alpha',
            'alasan_perubahan' => 'Koreksi administrative.',
        ])
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('riwayat_perubahan_absensi', [
        'jenis_perubahan' => RiwayatPerubahanAbsensi::JENIS_STATUS_ABSENSI,
        'absensi_sik_id' => $fixture['absensi']->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'actor_user_id' => $fixture['admin']->id,
        'status_sebelumnya' => 'Hadir',
        'status_sesudahnya' => 'Alpha',
        'alasan_perubahan' => 'Koreksi administrative.',
    ]);
});

test('perubahan status yang sama tidak membuat riwayat baru', function () {
    $fixture = createAttendanceHistoryFixture();

    $this->actingAs($fixture['admin'])
        ->put(route('absensi-sik.update', $fixture['absensi']), [
            'status' => 'Hadir',
            'alasan_perubahan' => 'Tidak ada perubahan.',
        ])
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('riwayat_perubahan_absensi', 0);
});

test('menandai pertemuan selesai dicatat sebagai riwayat status pertemuan', function () {
    $fixture = createAttendanceHistoryFixture();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuan']))
        ->assertRedirect(route('absensi-sik.index'));

    $this->assertDatabaseHas('riwayat_perubahan_absensi', [
        'jenis_perubahan' => RiwayatPerubahanAbsensi::JENIS_STATUS_PERTEMUAN,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'actor_user_id' => $fixture['instructorUser']->id,
        'instruktur_id' => $fixture['instructor']->id,
        'status_sebelumnya' => 'Terjadwal',
        'status_sesudahnya' => 'Terlaksana',
    ]);
});

test('ubah tanggal lewat modul absensi dicatat sebagai riwayat tanggal', function () {
    $fixture = createAttendanceHistoryFixture();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
            'tanggal_baru' => '2026-06-18',
            'konfirmasi' => '1',
        ])
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('riwayat_perubahan_absensi', [
        'jenis_perubahan' => RiwayatPerubahanAbsensi::JENIS_TANGGAL_PERTEMUAN,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'actor_user_id' => $fixture['instructorUser']->id,
        'instruktur_id' => $fixture['instructor']->id,
        'tanggal_sebelumnya' => '2026-06-15 00:00:00',
        'tanggal_sesudahnya' => '2026-06-18 00:00:00',
    ]);
});

test('update pertemuan oleh admin mencatat perubahan status dan tanggal', function () {
    $fixture = createAttendanceHistoryFixture();
    $pertemuan = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['kegiatan']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-06-20',
        'status' => 'Terjadwal',
        'kelompok_host_id' => $fixture['kelompok']->id,
    ]);

    $this->actingAs($fixture['admin'])
        ->put(route('pertemuan.update', $pertemuan), [
            'kegiatan_id' => $fixture['kegiatan']->id,
            'pertemuan_ke' => 2,
            'tanggal' => '2026-06-22',
            'status' => 'Terlaksana',
            'kelompok_host_id' => $fixture['kelompok']->id,
        ])
        ->assertRedirect(route('pertemuan.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('riwayat_perubahan_absensi', [
        'jenis_perubahan' => RiwayatPerubahanAbsensi::JENIS_STATUS_PERTEMUAN,
        'pertemuan_id' => $pertemuan->id,
        'actor_user_id' => $fixture['admin']->id,
        'status_sebelumnya' => 'Terjadwal',
        'status_sesudahnya' => 'Terlaksana',
    ]);

    $this->assertDatabaseHas('riwayat_perubahan_absensi', [
        'jenis_perubahan' => RiwayatPerubahanAbsensi::JENIS_TANGGAL_PERTEMUAN,
        'pertemuan_id' => $pertemuan->id,
        'actor_user_id' => $fixture['admin']->id,
        'tanggal_sebelumnya' => '2026-06-20 00:00:00',
        'tanggal_sesudahnya' => '2026-06-22 00:00:00',
    ]);
});
