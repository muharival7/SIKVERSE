<?php

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function createUbahTanggalFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instrukturUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instruktur = InfrastrukturSik::query()->create([
        'user_id' => $instrukturUser->id,
        'nama' => 'Instruktur Ubah Tanggal '.$suffix,
        'status' => 'Aktif',
    ]);

    $periode = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Ubah Tanggal '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);

    $kelompok = Kelompok::query()->create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instruktur->id,
        'nama_kelompok' => 'Kelompok Ubah Tanggal '.$suffix,
        'status' => 'Aktif',
    ]);

    $kegiatan = Kegiatan::query()->create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'nama_kegiatan' => 'Kegiatan Ubah Tanggal '.$suffix,
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
        'nomor_induk' => 'UBT-'.$suffix,
        'nama' => 'Santri Ubah Tanggal '.$suffix,
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
        'dicatat_oleh' => $instruktur->id,
    ]);

    return compact('admin', 'instrukturUser', 'instruktur', 'periode', 'kelompok', 'kegiatan', 'pertemuan', 'santri', 'anggota', 'absensi');
}

test('admin berhasil mengubah tanggal pertemuan yang sudah memiliki absensi dengan konfirmasi', function () {
    $fixture = createUbahTanggalFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
            'tanggal_baru' => '2026-08-20',
            'konfirmasi' => '1',
        ])
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHas('success');

    $this->assertTrue(DB::table('pertemuan')
        ->where('id', $fixture['pertemuan']->id)
        ->whereDate('tanggal', '2026-08-20')
        ->exists());

    // Catatan absensi lama tetap terhubung ke pertemuan yang sama.
    $this->assertDatabaseHas('absensi_sik', [
        'id' => $fixture['absensi']->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'status' => 'Hadir',
        'anggota_periode_sik_id' => $fixture['absensi']->anggota_periode_sik_id,
    ]);
});

test('perubahan tanggal di luar rentang periode SIK kini diizinkan', function () {
    $fixture = createUbahTanggalFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
            'tanggal_baru' => '2027-01-01',
            'konfirmasi' => '1',
        ])
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHas('success');

    $this->assertTrue(DB::table('pertemuan')
        ->where('id', $fixture['pertemuan']->id)
        ->whereDate('tanggal', '2027-01-01')
        ->exists());
});

test('format tanggal yang tidak valid tetap ditolak', function () {
    $fixture = createUbahTanggalFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
            'tanggal_baru' => 'bukan-tanggal',
            'konfirmasi' => '1',
        ])
        ->assertSessionHasErrors('tanggal_baru');

    $this->assertTrue(DB::table('pertemuan')
        ->where('id', $fixture['pertemuan']->id)
        ->whereDate('tanggal', '2026-06-15')
        ->exists());
});

test('konfirmasi wajib untuk pertemuan yang sudah memiliki absensi', function () {
    $fixture = createUbahTanggalFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
            'tanggal_baru' => '2026-08-20',
        ])
        ->assertSessionHasErrors('konfirmasi');

    $this->assertTrue(DB::table('pertemuan')
        ->where('id', $fixture['pertemuan']->id)
        ->whereDate('tanggal', '2026-06-15')
        ->exists());
});

test('instruktur berwenang dapat mengubah tanggal pertemuan kelompoknya', function () {
    $fixture = createUbahTanggalFixture();

    $this->actingAs($fixture['instrukturUser'])
        ->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
            'tanggal_baru' => '2026-09-10',
            'konfirmasi' => '1',
        ])
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHas('success');

    $this->assertTrue(DB::table('pertemuan')
        ->where('id', $fixture['pertemuan']->id)
        ->whereDate('tanggal', '2026-09-10')
        ->exists());
});

test('instruktur tanpa izin tidak dapat mengubah tanggal pertemuan', function () {
    $fixture = createUbahTanggalFixture();

    $otherUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    InfrastrukturSik::query()->create([
        'user_id' => $otherUser->id,
        'nama' => 'Instruktur Lain',
        'status' => 'Aktif',
    ]);

    $this->actingAs($otherUser)
        ->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
            'tanggal_baru' => '2026-08-20',
            'konfirmasi' => '1',
        ])
        ->assertForbidden();

    $this->assertTrue(DB::table('pertemuan')
        ->where('id', $fixture['pertemuan']->id)
        ->whereDate('tanggal', '2026-06-15')
        ->exists());
});

test('santri tidak dapat mengakses endpoint perubahan tanggal', function () {
    $fixture = createUbahTanggalFixture();
    $santriUser = User::factory()->create(['role' => UserRole::Santri]);

    $this->actingAs($santriUser)
        ->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
            'tanggal_baru' => '2026-08-20',
            'konfirmasi' => '1',
        ])
        ->assertForbidden();
});

test('tamu tidak dapat mengakses endpoint perubahan tanggal', function () {
    $fixture = createUbahTanggalFixture();

    $this->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
        'tanggal_baru' => '2026-08-20',
        'konfirmasi' => '1',
    ])->assertRedirect(route('login'));
});

test('pertemuan dibatalkan tidak dapat diubah tanggalnya', function () {
    $fixture = createUbahTanggalFixture();
    Pertemuan::query()->whereKey($fixture['pertemuan']->id)->update(['status' => 'Dibatalkan']);

    $this->actingAs($fixture['admin'])
        ->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
            'tanggal_baru' => '2026-08-20',
            'konfirmasi' => '1',
        ])
        ->assertSessionHasErrors('tanggal_baru');
});

test('perubahan tanggal tidak mengubah identitas kegiatan, kelompok, maupun status absensi', function () {
    $fixture = createUbahTanggalFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
            'tanggal_baru' => '2026-08-20',
            'konfirmasi' => '1',
        ])
        ->assertRedirect(route('absensi-sik.index'));

    $pertemuan = Pertemuan::query()->findOrFail($fixture['pertemuan']->id);
    expect((int) $pertemuan->kegiatan_id)->toBe((int) $fixture['kegiatan']->id)
        ->and((int) $pertemuan->kelompok_host_id)->toBe((int) $fixture['kelompok']->id)
        ->and($pertemuan->status)->toBe('Terjadwal')
        ->and($pertemuan->tanggal->toDateString())->toBe('2026-08-20');

    $this->assertDatabaseHas('absensi_sik', [
        'id' => $fixture['absensi']->id,
        'status' => 'Hadir',
        'anggota_periode_sik_id' => $fixture['absensi']->anggota_periode_sik_id,
        'pertemuan_id' => $fixture['pertemuan']->id,
    ]);
});

test('input tanggal pertemuan tidak lagi dikunci dengan atribut min dan max', function () {
    $fixture = createUbahTanggalFixture();
    $fixture['periode']->update([
        'tanggal_mulai' => '2026-11-01',
        'tanggal_selesai' => '2026-11-01',
    ]);
    $fixture['pertemuan']->update(['tanggal' => '2026-11-20']);

    $this->actingAs($fixture['admin'])
        ->get(route('absensi-sik.index'))
        ->assertOk()
        ->assertDontSee('min="2026-11-01"', false)
        ->assertDontSee('max="2026-11-01"', false)
        ->assertSee('name="tanggal_baru"', false);
});

test('instruktur dapat memilih tanggal pertemuan di luar rentang periode maupun riwayat', function () {
    $fixture = createUbahTanggalFixture();
    $fixture['periode']->update([
        'tanggal_mulai' => '2026-11-01',
        'tanggal_selesai' => '2026-11-01',
    ]);
    $fixture['pertemuan']->update(['tanggal' => '2026-11-20']);

    $pertemuanKedua = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['kegiatan']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-27',
        'status' => 'Terlaksana',
        'kelompok_host_id' => $fixture['kelompok']->id,
    ]);
    AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $pertemuanKedua->id,
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
        'dicatat_oleh' => $fixture['instruktur']->id,
    ]);

    $this->actingAs($fixture['instrukturUser'])
        ->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
            'tanggal_baru' => '2027-03-05',
            'konfirmasi' => '1',
        ])
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHas('success');

    $this->assertTrue(DB::table('pertemuan')
        ->where('id', $fixture['pertemuan']->id)
        ->where('status', 'Terjadwal')
        ->whereDate('tanggal', '2027-03-05')
        ->exists());
    $this->assertDatabaseHas('absensi_sik', [
        'id' => $fixture['absensi']->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'status' => 'Hadir',
    ]);
});

test('mengubah tanggal pertemuan tidak mengubah status pertemuan maupun absensi terkait', function () {
    $fixture = createUbahTanggalFixture();
    $fixture['pertemuan']->update(['status' => 'Terlaksana']);

    $this->actingAs($fixture['instrukturUser'])
        ->post(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuan']), [
            'tanggal_baru' => '2026-12-31',
            'konfirmasi' => '1',
        ])
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHas('success');

    $pertemuan = Pertemuan::query()->findOrFail($fixture['pertemuan']->id);
    expect($pertemuan->status)->toBe('Terlaksana')
        ->and($pertemuan->tanggal->toDateString())->toBe('2026-12-31');

    $this->assertDatabaseHas('absensi_sik', [
        'id' => $fixture['absensi']->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'status' => 'Hadir',
    ]);
});
