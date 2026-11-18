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

function createPertemuanFixture(array $overrides = []): array
{
    $suffix = fake()->unique()->numerify('######');

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $periode = PeriodeSik::create([
        'nama_periode' => 'Periode Pertemuan '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);

    $instrukturUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instruktur = InfrastrukturSik::create([
        'user_id' => $instrukturUser->id,
        'nama' => 'Instruktur Uji '.$suffix,
        'status' => 'Aktif',
    ]);

    $kelompok = Kelompok::create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instruktur->id,
        'nama_kelompok' => 'Kelompok Uji '.$suffix,
        'status' => 'Aktif',
    ]);

    $kegiatan = Kegiatan::create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => null,
        'nama_kegiatan' => 'Kegiatan Uji '.$suffix,
        'jenis_kegiatan' => 'Aula',
        'status' => 'Aktif',
    ]);

    $pertemuan = Pertemuan::create([
        'kegiatan_id' => $kegiatan->id,
        'pertemuan_ke' => 1,
        'tanggal' => '2026-06-15',
        'status' => 'Terjadwal',
        'kelompok_host_id' => $kelompok->id,
    ]);

    return array_merge(compact('admin', 'periode', 'instruktur', 'kelompok', 'kegiatan', 'pertemuan'), $overrides);
}

function createAbsensiForPertemuan(array $fixture): void
{
    $suffix = fake()->unique()->numerify('######');

    $santriUser = User::factory()->create(['role' => UserRole::Santri]);
    $santri = Santri::create([
        'user_id' => $santriUser->id,
        'nomor_induk' => 'PERT-'.$suffix,
        'nama' => 'Santri Pertemuan Uji',
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);

    $anggota = AnggotaPeriodeSik::create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $fixture['periode']->id,
        'kelompok_id' => $fixture['kelompok']->id,
        'status' => 'Aktif',
    ]);

    AbsensiSik::create([
        'anggota_periode_sik_id' => $anggota->id,
        'pertemuan_id' => $fixture['pertemuan']->id,
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
        'dicatat_oleh' => $fixture['instruktur']->id,
    ]);
}

test('pertemuan dengan tanggal dalam rentang periode berhasil dibuat', function () {
    $fixture = createPertemuanFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('pertemuan.store'), [
            'kegiatan_id' => $fixture['kegiatan']->id,
            'pertemuan_ke' => 2,
            'tanggal' => '2026-06-15',
            'status' => 'Terjadwal',
            'kelompok_host_id' => $fixture['kelompok']->id,
        ])
        ->assertRedirect(route('pertemuan.index'))
        ->assertSessionHas('success');

    $this->assertTrue(DB::table('pertemuan')
        ->where('kegiatan_id', $fixture['kegiatan']->id)
        ->where('pertemuan_ke', 2)
        ->whereDate('tanggal', '2026-06-15')
        ->exists());
});

test('pertemuan dengan tanggal sebelum periode ditolak', function () {
    $fixture = createPertemuanFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('pertemuan.store'), [
            'kegiatan_id' => $fixture['kegiatan']->id,
            'pertemuan_ke' => 2,
            'tanggal' => '2025-12-31',
            'status' => 'Terjadwal',
            'kelompok_host_id' => $fixture['kelompok']->id,
        ])
        ->assertSessionHasErrors('tanggal');

    $this->assertDatabaseCount('pertemuan', 1);
});

test('pertemuan dengan tanggal setelah periode ditolak', function () {
    $fixture = createPertemuanFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('pertemuan.store'), [
            'kegiatan_id' => $fixture['kegiatan']->id,
            'pertemuan_ke' => 2,
            'tanggal' => '2027-01-01',
            'status' => 'Terjadwal',
            'kelompok_host_id' => $fixture['kelompok']->id,
        ])
        ->assertSessionHasErrors('tanggal');

    $this->assertDatabaseCount('pertemuan', 1);
});

test('tanggal pertemuan yang sudah memiliki absensi tetap terkunci saat diupdate', function () {
    $fixture = createPertemuanFixture();
    createAbsensiForPertemuan($fixture);

    $this->assertDatabaseHas('absensi_sik', ['pertemuan_id' => $fixture['pertemuan']->id]);

    $this->actingAs($fixture['admin'])
        ->put(route('pertemuan.update', $fixture['pertemuan']), [
            'kegiatan_id' => $fixture['kegiatan']->id,
            'pertemuan_ke' => 1,
            'tanggal' => '2026-07-01',
            'status' => 'Terjadwal',
            'kelompok_host_id' => $fixture['kelompok']->id,
        ])
        ->assertSessionHasErrors('kegiatan_id');

    $this->assertTrue(DB::table('pertemuan')
        ->where('id', $fixture['pertemuan']->id)
        ->whereDate('tanggal', '2026-06-15')
        ->exists());
});

test('pertemuan tanpa absensi dapat diupdate tanggalnya', function () {
    $fixture = createPertemuanFixture();

    $this->assertDatabaseCount('absensi_sik', 0);

    $this->actingAs($fixture['admin'])
        ->put(route('pertemuan.update', $fixture['pertemuan']), [
            'kegiatan_id' => $fixture['kegiatan']->id,
            'pertemuan_ke' => 1,
            'tanggal' => '2026-07-01',
            'status' => 'Terjadwal',
            'kelompok_host_id' => $fixture['kelompok']->id,
        ])
        ->assertRedirect(route('pertemuan.index'))
        ->assertSessionHas('success');

    $this->assertTrue(DB::table('pertemuan')
        ->where('id', $fixture['pertemuan']->id)
        ->whereDate('tanggal', '2026-07-01')
        ->exists());
});
