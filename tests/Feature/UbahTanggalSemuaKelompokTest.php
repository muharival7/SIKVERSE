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

/** @return array<string, mixed> */
function createUbahTanggalSemuaFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $createInstruktur = function (string $nama, string $status = 'Aktif'): array {
        $user = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
        $instruktur = InfrastrukturSik::query()->create([
            'user_id' => $user->id,
            'nama' => $nama.' ',
            'status' => $status,
        ]);

        return [$user, $instruktur];
    };

    [$userA, $instrukturA] = $createInstruktur('Instruktur A '.$suffix);
    [$userB, $instrukturB] = $createInstruktur('Instruktur B '.$suffix);
    [$userInaktif, $instrukturInaktif] = $createInstruktur('Instruktur Nonaktif '.$suffix, 'Nonaktif');

    $periodeAktif = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Ubah Tanggal '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $periodeLama = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Lama Ubah Tanggal '.$suffix,
        'tahun' => 2025,
        'tanggal_mulai' => '2025-01-01',
        'tanggal_selesai' => '2025-12-31',
        'status' => 'Selesai',
    ]);

    $createGroup = function (
        PeriodeSik $periode,
        InfrastrukturSik $instruktur,
        string $nama,
        string $status = 'Aktif'
    ) use ($suffix): Kelompok {
        return Kelompok::query()->create([
            'periode_sik_id' => $periode->id,
            'instruktur_id' => $instruktur->id,
            'nama_kelompok' => $nama.' '.$suffix,
            'status' => $status,
        ]);
    };

    $groupA = $createGroup($periodeAktif, $instrukturA, 'Kelompok A');
    $groupB = $createGroup($periodeAktif, $instrukturB, 'Kelompok B');
    $groupNonaktif = $createGroup($periodeAktif, $instrukturInaktif, 'Kelompok Nonaktif', 'Nonaktif');
    $groupLama = $createGroup($periodeLama, $instrukturA, 'Kelompok Lama');

    $createKegiatan = function (PeriodeSik $periode, Kelompok $group, string $nama) use ($suffix): Kegiatan {
        return Kegiatan::query()->create([
            'periode_sik_id' => $periode->id,
            'kelompok_id' => $group->id,
            'nama_kegiatan' => $nama.' '.$suffix,
            'jenis_kegiatan' => 'Kelas',
            'status' => 'Aktif',
        ]);
    };

    $kegiatanA = $createKegiatan($periodeAktif, $groupA, 'Kegiatan A');
    $kegiatanB = $createKegiatan($periodeAktif, $groupB, 'Kegiatan B');
    $kegiatanNonaktif = $createKegiatan($periodeAktif, $groupNonaktif, 'Kegiatan Nonaktif');
    $kegiatanLama = $createKegiatan($periodeLama, $groupLama, 'Kegiatan Lama');

    $createPertemuan = function (
        Kegiatan $kegiatan,
        Kelompok $group,
        int $ke,
        string $tanggal = '2026-06-15',
        ?InfrastrukturSik $pengganti = null
    ): Pertemuan {
        return Pertemuan::query()->create([
            'kegiatan_id' => $kegiatan->id,
            'pertemuan_ke' => $ke,
            'tanggal' => $tanggal,
            'status' => 'Terjadwal',
            'kelompok_host_id' => $group->id,
            'instruktur_pengganti_id' => $pengganti?->id,
        ]);
    };

    $pertemuanA = $createPertemuan($kegiatanA, $groupA, 1);
    $pertemuanB1 = $createPertemuan($kegiatanB, $groupB, 1);
    $pertemuanB2 = $createPertemuan($kegiatanB, $groupB, 2, '2026-06-22');
    $pertemuanB3 = $createPertemuan($kegiatanB, $groupB, 3, '2026-06-29', $instrukturA);
    $pertemuanNonaktif = $createPertemuan($kegiatanNonaktif, $groupNonaktif, 1);
    $pertemuanLama = $createPertemuan($kegiatanLama, $groupLama, 1, '2025-06-15');

    $createSantri = function (string $nama, string $nim): Santri {
        return Santri::query()->create([
            'nomor_induk' => $nim.'-'.$nama,
            'nama' => $nama.' '.fake()->unique()->numerify('######'),
            'jenis_kelamin' => 'Laki-laki',
            'status' => 'Aktif',
        ]);
    };

    $santriA = $createSantri('Santri A', 'UTS-A-'.$suffix);
    $santriB = $createSantri('Santri B', 'UTS-B-'.$suffix);
    $santriNonaktif = $createSantri('Santri Nonaktif', 'UTS-N-'.$suffix);
    $santriLama = $createSantri('Santri Lama', 'UTS-L-'.$suffix);

    $createAnggota = function (
        Santri $santri,
        PeriodeSik $periode,
        Kelompok $group
    ): AnggotaPeriodeSik {
        return AnggotaPeriodeSik::query()->create([
            'santri_id' => $santri->id,
            'periode_sik_id' => $periode->id,
            'kelompok_id' => $group->id,
            'status' => 'Aktif',
        ]);
    };

    $anggotaA = $createAnggota($santriA, $periodeAktif, $groupA);
    $anggotaB = $createAnggota($santriB, $periodeAktif, $groupB);
    $anggotaNonaktif = $createAnggota($santriNonaktif, $periodeAktif, $groupNonaktif);
    $anggotaLama = $createAnggota($santriLama, $periodeLama, $groupLama);

    $createAbsensi = function (AnggotaPeriodeSik $anggota, Pertemuan $pertemuan): AbsensiSik {
        return AbsensiSik::query()->create([
            'anggota_periode_sik_id' => $anggota->id,
            'pertemuan_id' => $pertemuan->id,
            'metode_absensi' => 'Manual',
            'status' => 'Hadir',
        ]);
    };

    $createAbsensi($anggotaA, $pertemuanA);
    $createAbsensi($anggotaB, $pertemuanB1);
    $createAbsensi($anggotaB, $pertemuanB2);
    $createAbsensi($anggotaNonaktif, $pertemuanNonaktif);
    $createAbsensi($anggotaLama, $pertemuanLama);

    return compact(
        'suffix',
        'userA',
        'instrukturA',
        'userB',
        'instrukturB',
        'userInaktif',
        'instrukturInaktif',
        'periodeAktif',
        'periodeLama',
        'groupA',
        'groupB',
        'groupNonaktif',
        'groupLama',
        'kegiatanA',
        'kegiatanB',
        'kegiatanNonaktif',
        'kegiatanLama',
        'pertemuanA',
        'pertemuanB1',
        'pertemuanB2',
        'pertemuanB3',
        'pertemuanNonaktif',
        'pertemuanLama',
        'santriA',
        'santriB',
        'santriNonaktif',
        'santriLama',
        'anggotaA',
        'anggotaB',
        'anggotaNonaktif',
        'anggotaLama'
    );
}

/** @param array<string, mixed> $overrides */
function ubahTanggalSemuaPayload(string $tanggal, array $overrides = []): array
{
    return array_merge([
        'tanggal_baru' => $tanggal,
        'konfirmasi' => '1',
    ], $overrides);
}

function assertTanggalPertemuan(int $pertemuanId, string $tanggal): void
{
    expect(Pertemuan::query()->whereKey($pertemuanId)->whereDate('tanggal', $tanggal)->exists())
        ->toBeTrue("Pertemuan {$pertemuanId} tidak berada pada tanggal {$tanggal}.");
}

test('instruktur A melihat tombol Ubah Tanggal untuk pertemuan kelompok A', function () {
    $fixture = createUbahTanggalSemuaFixture();

    $this->actingAs($fixture['userA'])
        ->get(route('absensi-sik.index'))
        ->assertOk()
        ->assertSee('Ubah Tanggal Pertemuan')
        ->assertSee(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuanA']), false)
        ->assertSee($fixture['groupA']->nama_kelompok);
});

test('instruktur A melihat tombol Ubah Tanggal untuk pertemuan kelompok B', function () {
    $fixture = createUbahTanggalSemuaFixture();

    $this->actingAs($fixture['userA'])
        ->get(route('absensi-sik.index'))
        ->assertOk()
        ->assertSee(route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuanB1']), false)
        ->assertSee($fixture['groupB']->nama_kelompok);
});

test('instruktur A berhasil mengubah tanggal pertemuan kelompok B', function () {
    $fixture = createUbahTanggalSemuaFixture();

    $this->actingAs($fixture['userA'])
        ->post(
            route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuanB1']),
            ubahTanggalSemuaPayload('2026-07-01', [
                'kelompok_id' => $fixture['groupB']->id,
                'periode_sik_id' => $fixture['periodeAktif']->id,
            ])
        )
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    assertTanggalPertemuan($fixture['pertemuanB1']->id, '2026-07-01');
});

test('instruktur B berhasil mengubah tanggal pertemuan kelompok A', function () {
    $fixture = createUbahTanggalSemuaFixture();

    $this->actingAs($fixture['userB'])
        ->post(
            route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuanA']),
            ubahTanggalSemuaPayload('2026-07-02', [
                'kelompok_id' => $fixture['groupA']->id,
                'periode_sik_id' => $fixture['periodeAktif']->id,
            ])
        )
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    assertTanggalPertemuan($fixture['pertemuanA']->id, '2026-07-02');
});

test('perubahan hanya mengenai pertemuan yang dipilih', function () {
    $fixture = createUbahTanggalSemuaFixture();

    $this->actingAs($fixture['userA'])
        ->post(
            route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuanB1']),
            ubahTanggalSemuaPayload('2026-08-01', [
                'kelompok_id' => $fixture['groupB']->id,
                'periode_sik_id' => $fixture['periodeAktif']->id,
            ])
        )
        ->assertRedirect(route('absensi-sik.index'));

    assertTanggalPertemuan($fixture['pertemuanB1']->id, '2026-08-01');
    assertTanggalPertemuan($fixture['pertemuanB2']->id, '2026-06-22');
    assertTanggalPertemuan($fixture['pertemuanB3']->id, '2026-06-29');
    assertTanggalPertemuan($fixture['pertemuanA']->id, '2026-06-15');
    assertTanggalPertemuan($fixture['pertemuanLama']->id, '2025-06-15');

    expect((int) Pertemuan::query()->findOrFail($fixture['pertemuanB1']->id)->pertemuan_ke)->toBe(1);
});

test('pertemuan periode berbeda tidak tertukar dan periode manipulatif ditolak', function () {
    $fixture = createUbahTanggalSemuaFixture();

    $this->actingAs($fixture['userA'])
        ->post(
            route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuanB1']),
            ubahTanggalSemuaPayload('2026-08-02', [
                'kelompok_id' => $fixture['groupB']->id,
                'periode_sik_id' => $fixture['periodeAktif']->id,
            ])
        )
        ->assertRedirect(route('absensi-sik.index'));

    assertTanggalPertemuan($fixture['pertemuanB1']->id, '2026-08-02');
    assertTanggalPertemuan($fixture['pertemuanLama']->id, '2025-06-15');

    $this->post(
        route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuanB2']),
        ubahTanggalSemuaPayload('2026-09-01', [
            'kelompok_id' => $fixture['groupB']->id,
            'periode_sik_id' => $fixture['periodeLama']->id,
        ])
    )->assertSessionHasErrors('periode_sik_id');

    assertTanggalPertemuan($fixture['pertemuanB2']->id, '2026-06-22');
});

test('id pertemuan yang dimanipulasi ditolak', function () {
    $fixture = createUbahTanggalSemuaFixture();
    $idPalsu = $fixture['pertemuanB1']->id + 100000;

    $this->actingAs($fixture['userA'])
        ->post(
            route('absensi-sik.pertemuan.ubah-tanggal', $idPalsu),
            ubahTanggalSemuaPayload('2026-08-03')
        )
        ->assertNotFound();

    assertTanggalPertemuan($fixture['pertemuanB1']->id, '2026-06-15');
    assertTanggalPertemuan($fixture['pertemuanA']->id, '2026-06-15');
});

test('id kelompok yang dimanipulasi ditolak', function () {
    $fixture = createUbahTanggalSemuaFixture();

    $this->actingAs($fixture['userA'])
        ->post(
            route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuanB1']),
            ubahTanggalSemuaPayload('2026-08-03', [
                'kelompok_id' => $fixture['groupA']->id,
                'periode_sik_id' => $fixture['periodeAktif']->id,
            ])
        )
        ->assertSessionHasErrors('kelompok_id');

    assertTanggalPertemuan($fixture['pertemuanB1']->id, '2026-06-15');
});

test('kelompok nonaktif ditolak saat mengubah tanggal', function () {
    $fixture = createUbahTanggalSemuaFixture();

    $this->actingAs($fixture['userA'])
        ->post(
            route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuanNonaktif']),
            ubahTanggalSemuaPayload('2026-08-04', [
                'kelompok_id' => $fixture['groupNonaktif']->id,
                'periode_sik_id' => $fixture['periodeAktif']->id,
            ])
        )
        ->assertSessionHasErrors(['kelompok_id' => 'Kelompok target pertemuan tidak aktif.']);

    assertTanggalPertemuan($fixture['pertemuanNonaktif']->id, '2026-06-15');
});

test('instruktur nonaktif tidak dapat mengubah tanggal', function () {
    $fixture = createUbahTanggalSemuaFixture();

    $this->actingAs($fixture['userInaktif'])
        ->post(
            route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuanB1']),
            ubahTanggalSemuaPayload('2026-08-05')
        )
        ->assertForbidden();

    assertTanggalPertemuan($fixture['pertemuanB1']->id, '2026-06-15');
});

test('santri dan ka lab mutu tetap tidak dapat mengubah tanggal', function (UserRole $role) {
    $fixture = createUbahTanggalSemuaFixture();
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->post(
            route('absensi-sik.pertemuan.ubah-tanggal', $fixture['pertemuanB1']),
            ubahTanggalSemuaPayload('2026-08-05')
        )
        ->assertForbidden();

    assertTanggalPertemuan($fixture['pertemuanB1']->id, '2026-06-15');
})->with([
    'santri' => [UserRole::Santri],
    'ka lab mutu' => [UserRole::KaLabMutu],
]);

test('tandai selesai tetap hanya untuk penanggung jawab atau pengganti', function () {
    $fixture = createUbahTanggalSemuaFixture();

    $this->actingAs($fixture['userA'])
        ->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuanB1']))
        ->assertForbidden();

    $this->actingAs($fixture['userB'])
        ->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuanB1']))
        ->assertRedirect(route('absensi-sik.index'));

    $this->assertDatabaseHas('pertemuan', [
        'id' => $fixture['pertemuanB1']->id,
        'status' => 'Terlaksana',
    ]);

    $this->actingAs($fixture['userA'])
        ->post(route('absensi-sik.pertemuan.selesai', $fixture['pertemuanB3']))
        ->assertRedirect(route('absensi-sik.index'));

    $this->assertDatabaseHas('pertemuan', [
        'id' => $fixture['pertemuanB3']->id,
        'status' => 'Terlaksana',
    ]);
});

test('absensi lintas kelompok yang sudah diperbaiki tetap berfungsi', function () {
    $fixture = createUbahTanggalSemuaFixture();

    $this->actingAs($fixture['userA'])
        ->post(route('absensi-sik.store'), [
            'anggota_periode_sik_id' => $fixture['anggotaB']->id,
            'pertemuan_id' => $fixture['pertemuanB3']->id,
            'status' => 'Hadir',
        ])
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggotaB']->id,
        'pertemuan_id' => $fixture['pertemuanB3']->id,
        'dicatat_oleh' => $fixture['instrukturA']->id,
    ]);
});

test('filter kelompok absensi memakai kontrol ringkas dan menampilkan jumlah pilihan', function () {
    $fixture = createUbahTanggalSemuaFixture();

    $this->actingAs($fixture['userA'])
        ->get(route('absensi-sik.index'))
        ->assertOk()
        ->assertSee('attendance-filter', false)
        ->assertSee('data-kelompok-multiselect', false)
        ->assertSee('Semua kelompok')
        ->assertDontSee('size="4"', false);

    $this->get(route('absensi-sik.index', [
        'kelompok_id' => [$fixture['groupA']->id, $fixture['groupB']->id],
    ]))
        ->assertOk()
        ->assertSee('2 kelompok dipilih')
        ->assertViewHas('selectedGroupIds', [$fixture['groupA']->id, $fixture['groupB']->id]);
});
