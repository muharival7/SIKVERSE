<?php

use App\Enums\UserRole;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\User;

/** @return array<string, mixed> */
function createSharedCompletionFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $createInstructor = function (string $nama) use ($suffix): array {
        $user = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
        $instruktur = InfrastrukturSik::query()->create([
            'user_id' => $user->id,
            'nama' => $nama.' '.$suffix,
            'status' => 'Aktif',
        ]);

        return [$user, $instruktur];
    };

    [$assignedUser, $assignedInstruktur] = $createInstructor('Pengganti');
    [$otherUser] = $createInstructor('Instruktur Lain');

    $periode = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Bersama '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);

    $kegiatan = Kegiatan::query()->create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => null,
        'nama_kegiatan' => 'Rabu Mengaji Bersama '.$suffix,
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);

    $pertemuan = Pertemuan::query()->create([
        'kegiatan_id' => $kegiatan->id,
        'pertemuan_ke' => 1,
        'tanggal' => '2026-06-15',
        'status' => 'Terjadwal',
        'kelompok_host_id' => null,
        'instruktur_pengganti_id' => $assignedInstruktur->id,
    ]);

    return compact('admin', 'assignedUser', 'otherUser', 'pertemuan');
}

test('dosen pengganti yang ditunjuk dapat menandai selesai kegiatan bersama', function () {
    $fixture = createSharedCompletionFixture();

    $this->actingAs($fixture['assignedUser'])
        ->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuan']))
        ->assertRedirect(route('absensi-sik.index'));

    $this->assertDatabaseHas('pertemuan', [
        'id' => $fixture['pertemuan']->id,
        'status' => 'Terlaksana',
    ]);
});

test('dosen aktif yang tidak ditunjuk tidak dapat menandai selesai kegiatan bersama', function () {
    $fixture = createSharedCompletionFixture();

    $this->actingAs($fixture['otherUser'])
        ->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuan']))
        ->assertForbidden();

    $this->assertDatabaseHas('pertemuan', [
        'id' => $fixture['pertemuan']->id,
        'status' => 'Terjadwal',
    ]);
});

test('admin dapat menandai selesai kegiatan bersama', function () {
    $fixture = createSharedCompletionFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuan']))
        ->assertRedirect(route('absensi-sik.index'));

    $this->assertDatabaseHas('pertemuan', [
        'id' => $fixture['pertemuan']->id,
        'status' => 'Terlaksana',
    ]);
});
