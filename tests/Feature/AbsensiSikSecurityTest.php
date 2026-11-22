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
use App\Models\RiwayatAbsensi;
use App\Models\Santri;
use App\Models\User;

function createAbsensiSecurityFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $santriUser = User::factory()->create(['role' => UserRole::Santri]);
    $santri = Santri::create([
        'user_id' => $santriUser->id,
        'nomor_induk' => 'ABS-'.$suffix,
        'nama' => 'Santri Uji Absensi',
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);

    $createInstruktur = function (string $nama): array {
        $user = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
        $instruktur = InfrastrukturSik::create([
            'user_id' => $user->id,
            'nama' => $nama,
            'status' => 'Aktif',
        ]);

        return [$user, $instruktur];
    };

    [$memberUser, $memberInstruktur] = $createInstruktur('Instruktur Kelompok Anggota');
    [$hostUser, $hostInstruktur] = $createInstruktur('Instruktur Kelompok Host');
    [$substituteUser, $substituteInstruktur] = $createInstruktur('Instruktur Pengganti');
    [$unrelatedUser, $unrelatedInstruktur] = $createInstruktur('Instruktur Tidak Terkait');

    $periode = PeriodeSik::create([
        'nama_periode' => 'Periode Absensi '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);

    $memberKelompok = Kelompok::create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $memberInstruktur->id,
        'nama_kelompok' => 'Kelompok Anggota '.$suffix,
        'status' => 'Aktif',
    ]);
    $hostKelompok = Kelompok::create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $hostInstruktur->id,
        'nama_kelompok' => 'Kelompok Host '.$suffix,
        'status' => 'Aktif',
    ]);

    $anggota = AnggotaPeriodeSik::create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $memberKelompok->id,
        'status' => 'Aktif',
    ]);

    $kegiatan = Kegiatan::create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => null,
        'nama_kegiatan' => 'Kegiatan Absensi '.$suffix,
        'jenis_kegiatan' => 'Aula',
        'status' => 'Aktif',
    ]);

    $pertemuan = Pertemuan::create([
        'kegiatan_id' => $kegiatan->id,
        'pertemuan_ke' => 1,
        'tanggal' => '2026-11-20',
        'status' => 'Terlaksana',
        'kelompok_host_id' => $memberKelompok->id,
    ]);

    return compact(
        'admin',
        'santri',
        'memberUser',
        'memberInstruktur',
        'memberKelompok',
        'hostUser',
        'hostInstruktur',
        'hostKelompok',
        'substituteUser',
        'substituteInstruktur',
        'unrelatedUser',
        'unrelatedInstruktur',
        'periode',
        'anggota',
        'kegiatan',
        'pertemuan'
    );
}

function absensiSecurityPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'status' => 'Hadir',
        'waktu_masuk' => '07:00',
        'waktu_pulang' => '14:00',
        'keterangan' => 'Uji keamanan absensi',
    ], $overrides);
}

test('the instructor of the member group can record attendance when the target comes from the activity', function () {
    $fixture = createAbsensiSecurityFixture();
    $fixture['pertemuan']->update(['kelompok_host_id' => null]);
    $fixture['kegiatan']->update([
        'kelompok_id' => $fixture['memberKelompok']->id,
        'jenis_kegiatan' => 'Kelas',
    ]);

    $this->actingAs($fixture['memberUser'])
        ->post(route('absensi-sik.store'), absensiSecurityPayload($fixture))
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'status' => 'Hadir',
        'dicatat_oleh' => $fixture['memberInstruktur']->id,
    ]);
});

test('the instructor of the host group can record attendance when the member belongs to that host group', function () {
    $fixture = createAbsensiSecurityFixture();
    $fixture['pertemuan']->update(['kelompok_host_id' => $fixture['hostKelompok']->id]);
    $fixture['anggota']->update(['kelompok_id' => $fixture['hostKelompok']->id]);

    $this->actingAs($fixture['hostUser'])
        ->post(route('absensi-sik.store'), absensiSecurityPayload($fixture))
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'status' => 'Hadir',
        'dicatat_oleh' => $fixture['hostInstruktur']->id,
    ]);
});

test('a valid substitute instructor can record attendance for a target group they do not own', function () {
    $fixture = createAbsensiSecurityFixture();
    $fixture['pertemuan']->update([
        'instruktur_pengganti_id' => $fixture['substituteInstruktur']->id,
    ]);

    $this->actingAs($fixture['substituteUser'])
        ->post(route('absensi-sik.store'), absensiSecurityPayload($fixture))
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'dicatat_oleh' => $fixture['substituteInstruktur']->id,
    ]);
});

test('an active instructor from an unrelated group can record attendance on any active group', function () {
    $fixture = createAbsensiSecurityFixture();

    $this->actingAs($fixture['unrelatedUser'])
        ->post(route('absensi-sik.store'), absensiSecurityPayload($fixture))
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'dicatat_oleh' => $fixture['unrelatedInstruktur']->id,
    ]);
});

test('a santri from a different group cannot have attendance recorded', function () {
    $fixture = createAbsensiSecurityFixture();
    $fixture['anggota']->update(['kelompok_id' => $fixture['hostKelompok']->id]);

    $this->actingAs($fixture['memberUser'])
        ->post(route('absensi-sik.store'), absensiSecurityPayload($fixture))
        ->assertSessionHasErrors([
            'anggota_periode_sik_id' => 'Santri tidak terdaftar pada kelompok target pertemuan ini.',
        ]);

    $this->assertDatabaseCount('absensi_sik', 0);
});

test('attendance is rejected when both group sources are null', function () {
    $fixture = createAbsensiSecurityFixture();
    $fixture['pertemuan']->update(['kelompok_host_id' => null]);

    $this->actingAs($fixture['memberUser'])
        ->post(route('absensi-sik.store'), absensiSecurityPayload($fixture))
        ->assertSessionHasErrors('pertemuan_id');

    $this->assertDatabaseCount('absensi_sik', 0);
});

test('attendance is rejected when the activity group and host group differ', function () {
    $fixture = createAbsensiSecurityFixture();
    $fixture['kegiatan']->update([
        'kelompok_id' => $fixture['hostKelompok']->id,
        'jenis_kegiatan' => 'Kelas',
    ]);

    $this->actingAs($fixture['memberUser'])
        ->post(route('absensi-sik.store'), absensiSecurityPayload($fixture))
        ->assertSessionHasErrors([
            'pertemuan_id' => 'Kelompok host pertemuan dan kelompok kegiatan tidak konsisten.',
        ]);

    $this->assertDatabaseCount('absensi_sik', 0);
});

test('attendance is rejected when the group period does not match the activity period', function () {
    $fixture = createAbsensiSecurityFixture();
    $otherPeriode = PeriodeSik::create([
        'nama_periode' => 'Periode Berbeda '.fake()->unique()->numerify('######'),
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $fixture['memberKelompok']->update(['periode_sik_id' => $otherPeriode->id]);

    $this->actingAs($fixture['memberUser'])
        ->post(route('absensi-sik.store'), absensiSecurityPayload($fixture))
        ->assertSessionHasErrors([
            'anggota_periode_sik_id' => 'Periode santri, kegiatan, dan kelompok harus sama.',
        ]);

    $this->assertDatabaseCount('absensi_sik', 0);
});

test('attendance cannot be recorded on a cancelled meeting', function () {
    $fixture = createAbsensiSecurityFixture();
    $fixture['pertemuan']->update(['status' => 'Dibatalkan']);

    $this->actingAs($fixture['memberUser'])
        ->post(route('absensi-sik.store'), absensiSecurityPayload($fixture))
        ->assertSessionHasErrors([
            'pertemuan_id' => 'Pertemuan yang dibatalkan tidak menerima absensi.',
        ]);

    $this->assertDatabaseCount('absensi_sik', 0);
});

test('a rejected update leaves the existing attendance and its history unchanged', function () {
    $fixture = createAbsensiSecurityFixture();
    $absensi = AbsensiSik::create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
    ]);
    $fixture['anggota']->update(['kelompok_id' => $fixture['hostKelompok']->id]);

    $this->actingAs($fixture['memberUser'])
        ->put(route('absensi-sik.update', $absensi), [
            'status' => 'Alpha',
            'alasan_perubahan' => 'Perubahan yang harus ditolak',
        ])
        ->assertSessionHasErrors('anggota_periode_sik_id');

    expect($absensi->refresh()->status)->toBe('Hadir');
    $this->assertDatabaseCount('riwayat_absensi', 0);
    $this->assertDatabaseHas('absensi_sik', [
        'id' => $absensi->id,
        'status' => 'Hadir',
    ]);
});

test('a valid attendance record is stored and the recap remains consistent', function () {
    $fixture = createAbsensiSecurityFixture();
    $fixture['kegiatan']->update(['kelompok_id' => $fixture['memberKelompok']->id]);

    $this->actingAs($fixture['memberUser'])
        ->post(route('absensi-sik.store'), absensiSecurityPayload($fixture))
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'status' => 'Hadir',
        'metode_absensi' => 'Manual',
        'dicatat_oleh' => $fixture['memberInstruktur']->id,
    ]);
    $this->assertDatabaseHas('rekap_kehadiran_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'kegiatan_id' => $fixture['kegiatan']->id,
        'jumlah_hadir' => 1,
        'persentase_kehadiran' => 100.00,
        'status_kehadiran' => 'Memenuhi',
    ]);
    $this->assertDatabaseCount('riwayat_absensi', 0);
    expect(RekapKehadiranSik::count())->toBe(1);
    expect(RiwayatAbsensi::count())->toBe(0);
});
