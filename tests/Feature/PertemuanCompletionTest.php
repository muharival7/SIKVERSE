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

function createCompletionFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Selesai '.$suffix,
        'status' => 'Aktif',
    ]);
    $otherInstructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $otherInstructor = InfrastrukturSik::query()->create([
        'user_id' => $otherInstructorUser->id,
        'nama' => 'Instruktur Lain '.$suffix,
        'status' => 'Aktif',
    ]);

    $periode = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Selesai '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompok = Kelompok::query()->create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Selesai '.$suffix,
        'status' => 'Aktif',
    ]);
    $otherKelompok = Kelompok::query()->create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $otherInstructor->id,
        'nama_kelompok' => 'Kelompok Lain '.$suffix,
        'status' => 'Aktif',
    ]);
    $kegiatan = Kegiatan::query()->create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'nama_kegiatan' => 'Kegiatan Selesai '.$suffix,
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
        'nomor_induk' => 'SLS-'.$suffix,
        'nama' => 'Santri Selesai '.$suffix,
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
        'otherInstructorUser',
        'otherInstructor',
        'periode',
        'kelompok',
        'otherKelompok',
        'kegiatan',
        'pertemuan',
        'santri',
        'anggota',
        'absensi'
    );
}

test('instruktur menandai pertemuan selesai dan rekap tersinkron tanpa mengubah absensi', function () {
    $fixture = createCompletionFixture();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuan']))
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('pertemuan', [
        'id' => $fixture['pertemuan']->id,
        'status' => 'Terlaksana',
    ]);
    $this->assertDatabaseHas('absensi_sik', [
        'id' => $fixture['absensi']->id,
        'status' => 'Hadir',
        'pertemuan_id' => $fixture['pertemuan']->id,
    ]);
    $this->assertDatabaseHas('rekap_kehadiran_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'kegiatan_id' => $fixture['kegiatan']->id,
        'total_pertemuan' => 1,
        'jumlah_hadir' => 1,
        'status_kehadiran' => AttendanceRecapService::STATUS_MET,
    ]);
});

test('absensi yang diisi instruktur ikut tersinkron saat pertemuan ditandai selesai', function () {
    $fixture = createCompletionFixture();
    $suffix = fake()->unique()->numerify('######');
    $santriBaru = Santri::query()->create([
        'nomor_induk' => 'SIN-'.$suffix,
        'nama' => 'Santri Input '.$suffix,
        'jenis_kelamin' => 'Perempuan',
        'status' => 'Aktif',
    ]);
    $anggotaBaru = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santriBaru->id,
        'periode_sik_id' => $fixture['periode']->id,
        'kelompok_id' => $fixture['kelompok']->id,
        'status' => 'Aktif',
    ]);

    $this->actingAs($fixture['instructorUser'])
        ->post(route('absensi-sik.store'), [
            'anggota_periode_sik_id' => $anggotaBaru->id,
            'pertemuan_id' => $fixture['pertemuan']->id,
            'status' => 'Hadir',
            'waktu_masuk' => '08:00',
            'waktu_pulang' => '10:00',
        ])
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $anggotaBaru->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'status' => 'Hadir',
        'metode_absensi' => 'Manual',
    ]);

    $this->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuan']))
        ->assertRedirect(route('absensi-sik.index'));

    $this->assertDatabaseHas('rekap_kehadiran_sik', [
        'anggota_periode_sik_id' => $anggotaBaru->id,
        'kegiatan_id' => $fixture['kegiatan']->id,
        'total_pertemuan' => 1,
        'jumlah_hadir' => 1,
        'status_kehadiran' => AttendanceRecapService::STATUS_MET,
    ]);
});

test('instruktur kelompok lain tidak dapat menandai pertemuan selesai', function () {
    $fixture = createCompletionFixture();

    $this->actingAs($fixture['otherInstructorUser'])
        ->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuan']))
        ->assertForbidden();

    $this->assertDatabaseHas('pertemuan', [
        'id' => $fixture['pertemuan']->id,
        'status' => 'Terjadwal',
    ]);
    expect(RekapKehadiranSik::query()->count())->toBe(0);
});

test('role tanpa akses absensi ditolak saat menandai pertemuan selesai', function (UserRole $role) {
    $fixture = createCompletionFixture();
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuan']))
        ->assertForbidden();

    $this->assertDatabaseHas('pertemuan', [
        'id' => $fixture['pertemuan']->id,
        'status' => 'Terjadwal',
    ]);
})->with([
    'santri' => [UserRole::Santri],
    'ka lab mutu' => [UserRole::KaLabMutu],
]);

test('ka lab ditolak pada semua endpoint tulis absensi', function () {
    $fixture = createCompletionFixture();
    $kaLab = User::factory()->create(['role' => UserRole::KaLabMutu]);

    $this->actingAs($kaLab)
        ->post(route('absensi-sik.store'), [
            'pertemuan_id' => $fixture['pertemuan']->id,
            'anggota_periode_sik_id' => $fixture['anggota']->id,
            'status' => 'Hadir',
        ])
        ->assertForbidden();

    $this->put(route('absensi-sik.update', $fixture['absensi']), ['status' => 'Alpha'])
        ->assertForbidden();

    $this->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
        'tanggal_baru' => '2026-06-16',
        'konfirmasi' => '1',
    ])
        ->assertForbidden();

    $this->assertDatabaseHas('pertemuan', [
        'id' => $fixture['pertemuan']->id,
        'status' => 'Terjadwal',
        'tanggal' => '2026-06-15 00:00:00',
    ]);
    $this->assertDatabaseHas('absensi_sik', [
        'id' => $fixture['absensi']->id,
        'status' => 'Hadir',
    ]);
});

test('pertemuan dibatalkan tidak dapat ditandai selesai dan pertemuan selesai tidak berubah', function () {
    $fixture = createCompletionFixture();

    $fixture['pertemuan']->update(['status' => 'Dibatalkan']);
    $this->actingAs($fixture['admin'])
        ->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuan']))
        ->assertSessionHasErrors('pertemuan_id');
    $this->assertDatabaseHas('pertemuan', [
        'id' => $fixture['pertemuan']->id,
        'status' => 'Dibatalkan',
    ]);

    $fixture['pertemuan']->update(['status' => 'Terlaksana']);
    $this->actingAs($fixture['admin'])
        ->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuan']))
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHas('success');
    $this->assertDatabaseHas('pertemuan', [
        'id' => $fixture['pertemuan']->id,
        'status' => 'Terlaksana',
    ]);
    expect(RekapKehadiranSik::query()->count())->toBe(0);
});
