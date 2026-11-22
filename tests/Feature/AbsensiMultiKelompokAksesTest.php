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
function createAbsensiAksesFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $createInstruktur = function (string $nama, string $status = 'Aktif'): array {
        $user = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
        $instruktur = InfrastrukturSik::query()->create([
            'user_id' => $user->id,
            'nama' => $nama,
            'status' => $status,
        ]);

        return [$user, $instruktur];
    };

    [$userA, $instrukturA] = $createInstruktur('Instruktur A '.$suffix);
    [$userB, $instrukturB] = $createInstruktur('Instruktur B '.$suffix);
    [$userC, $instrukturC] = $createInstruktur('Instruktur C '.$suffix);
    [$userNonaktif, $instrukturNonaktif] = $createInstruktur('Instruktur Nonaktif '.$suffix, 'Nonaktif');
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $periodeAktif = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Akses '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $periodeLama = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Lama Akses '.$suffix,
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
    ): Kelompok {
        return Kelompok::query()->create([
            'periode_sik_id' => $periode->id,
            'instruktur_id' => $instruktur->id,
            'nama_kelompok' => $nama.' '.$periode->tahun,
            'status' => $status,
        ]);
    };

    $groupA = $createGroup($periodeAktif, $instrukturA, 'Sains '.$suffix);
    $groupB = $createGroup($periodeAktif, $instrukturB, 'Sistem Informasi '.$suffix);
    $groupC = $createGroup($periodeAktif, $instrukturC, 'Menjadi Imam '.$suffix);
    $groupLama = $createGroup($periodeLama, $instrukturA, 'Kelompok Lama '.$suffix);
    $groupNonaktif = $createGroup($periodeAktif, $instrukturA, 'Kelompok Nonaktif '.$suffix, 'Nonaktif');

    $createSantri = function (string $nama, string $nim): Santri {
        return Santri::query()->create([
            'nomor_induk' => $nim,
            'nama' => $nama.' '.fake()->unique()->numerify('######'),
            'jenis_kelamin' => 'Laki-laki',
            'status' => 'Aktif',
        ]);
    };

    $santriA = $createSantri('Santri Sains '.$suffix, 'AKS-A-'.$suffix);
    $santriB = $createSantri('Santri Sistem Informasi '.$suffix, 'AKS-B-'.$suffix);
    $santriC = $createSantri('Santri Imam '.$suffix, 'AKS-C-'.$suffix);
    $santriLama = $createSantri('Santri Lama '.$suffix, 'AKS-L-'.$suffix);
    $santriNonaktif = $createSantri('Santri Nonaktif '.$suffix, 'AKS-N-'.$suffix);

    $createAnggota = function (Santri $santri, Kelompok $group, PeriodeSik $periode): AnggotaPeriodeSik {
        return AnggotaPeriodeSik::query()->create([
            'santri_id' => $santri->id,
            'periode_sik_id' => $periode->id,
            'kelompok_id' => $group->id,
            'status' => 'Aktif',
        ]);
    };

    $anggotaA = $createAnggota($santriA, $groupA, $periodeAktif);
    $anggotaB = $createAnggota($santriB, $groupB, $periodeAktif);
    $anggotaC = $createAnggota($santriC, $groupC, $periodeAktif);
    $anggotaLama = $createAnggota($santriLama, $groupLama, $periodeLama);
    $anggotaNonaktif = $createAnggota($santriNonaktif, $groupNonaktif, $periodeAktif);

    $createKegiatan = function (PeriodeSik $periode, Kelompok $group, string $nama) use ($suffix): Kegiatan {
        return Kegiatan::query()->create([
            'periode_sik_id' => $periode->id,
            'kelompok_id' => $group->id,
            'nama_kegiatan' => $nama.' '.$suffix,
            'jenis_kegiatan' => 'Kelas',
            'status' => 'Aktif',
        ]);
    };

    $kegiatanA = $createKegiatan($periodeAktif, $groupA, 'Kegiatan Sains');
    $kegiatanB = $createKegiatan($periodeAktif, $groupB, 'Kegiatan Sistem Informasi');
    $kegiatanC = $createKegiatan($periodeAktif, $groupC, 'Kegiatan Imam');
    $kegiatanLama = $createKegiatan($periodeLama, $groupLama, 'Kegiatan Lama');
    $kegiatanNonaktif = $createKegiatan($periodeAktif, $groupNonaktif, 'Kegiatan Nonaktif');

    $createPertemuan = function (Kegiatan $kegiatan, Kelompok $group, int $ke = 1): Pertemuan {
        return Pertemuan::query()->create([
            'kegiatan_id' => $kegiatan->id,
            'pertemuan_ke' => $ke,
            'tanggal' => '2026-11-20',
            'status' => 'Terjadwal',
            'kelompok_host_id' => $group->id,
        ]);
    };

    $pertemuanA = $createPertemuan($kegiatanA, $groupA);
    $pertemuanA2 = $createPertemuan($kegiatanA, $groupA, 2);
    $pertemuanB = $createPertemuan($kegiatanB, $groupB);
    $pertemuanB2 = $createPertemuan($kegiatanB, $groupB, 2);
    $pertemuanC = $createPertemuan($kegiatanC, $groupC);
    $pertemuanLama = $createPertemuan($kegiatanLama, $groupLama);
    $pertemuanNonaktif = $createPertemuan($kegiatanNonaktif, $groupNonaktif);

    $createAbsensi = function (AnggotaPeriodeSik $anggota, Pertemuan $pertemuan): AbsensiSik {
        return AbsensiSik::query()->create([
            'anggota_periode_sik_id' => $anggota->id,
            'pertemuan_id' => $pertemuan->id,
            'metode_absensi' => 'Manual',
            'status' => 'Hadir',
        ]);
    };

    $absensiA = $createAbsensi($anggotaA, $pertemuanA);
    $absensiB = $createAbsensi($anggotaB, $pertemuanB);
    $absensiC = $createAbsensi($anggotaC, $pertemuanC);
    $absensiLama = $createAbsensi($anggotaLama, $pertemuanLama);

    return compact(
        'suffix',
        'userA',
        'instrukturA',
        'userB',
        'instrukturB',
        'userC',
        'instrukturC',
        'userNonaktif',
        'instrukturNonaktif',
        'admin',
        'periodeAktif',
        'periodeLama',
        'groupA',
        'groupB',
        'groupC',
        'groupLama',
        'groupNonaktif',
        'santriA',
        'santriB',
        'santriC',
        'santriLama',
        'santriNonaktif',
        'anggotaA',
        'anggotaB',
        'anggotaC',
        'anggotaLama',
        'anggotaNonaktif',
        'kegiatanA',
        'kegiatanB',
        'kegiatanC',
        'kegiatanLama',
        'kegiatanNonaktif',
        'pertemuanA',
        'pertemuanA2',
        'pertemuanB',
        'pertemuanB2',
        'pertemuanC',
        'pertemuanLama',
        'pertemuanNonaktif',
        'absensiA',
        'absensiB',
        'absensiC',
        'absensiLama'
    );
}

/** @param array<string, mixed> $fixture */
function absensiAksesPayload(array $fixture, AnggotaPeriodeSik $anggota, Pertemuan $pertemuan, array $overrides = []): array
{
    return array_merge([
        'anggota_periode_sik_id' => $anggota->id,
        'pertemuan_id' => $pertemuan->id,
        'status' => 'Hadir',
        'waktu_masuk' => '07:30',
        'waktu_pulang' => '14:00',
        'keterangan' => 'Absensi lintas kelompok',
    ], $overrides);
}

test('instruktur A dapat melihat kelompoknya pada dropdown absensi', function () {
    $fixture = createAbsensiAksesFixture();

    $this->actingAs($fixture['userA'])
        ->get(route('absensi-sik.index'))
        ->assertOk()
        ->assertViewHas('kelompoks', fn ($groups) => $groups->pluck('id')->contains($fixture['groupA']->id))
        ->assertSee($fixture['groupA']->nama_kelompok);
});

test('instruktur A dapat melihat kelompok instruktur lain pada dropdown absensi', function () {
    $fixture = createAbsensiAksesFixture();

    $this->actingAs($fixture['userA'])
        ->get(route('absensi-sik.index'))
        ->assertOk()
        ->assertViewHas('kelompoks', function ($groups) use ($fixture): bool {
            $ids = $groups->pluck('id');

            return $ids->contains($fixture['groupB']->id)
                && $ids->contains($fixture['groupC']->id)
                && ! $ids->contains($fixture['groupNonaktif']->id);
        })
        ->assertSee($fixture['groupB']->nama_kelompok)
        ->assertSee($fixture['groupC']->nama_kelompok)
        ->assertDontSee($fixture['groupNonaktif']->nama_kelompok);
});

test('instruktur A dapat mengabsen santri kelompok B', function () {
    $fixture = createAbsensiAksesFixture();

    $this->actingAs($fixture['userA'])
        ->post(route('absensi-sik.store'), absensiAksesPayload($fixture, $fixture['anggotaB'], $fixture['pertemuanB2']))
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggotaB']->id,
        'pertemuan_id' => $fixture['pertemuanB2']->id,
        'dicatat_oleh' => $fixture['instrukturA']->id,
    ]);
});

test('instruktur B dapat mengabsen santri kelompok A', function () {
    $fixture = createAbsensiAksesFixture();

    $this->actingAs($fixture['userB'])
        ->post(route('absensi-sik.store'), absensiAksesPayload($fixture, $fixture['anggotaA'], $fixture['pertemuanA2']))
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggotaA']->id,
        'pertemuan_id' => $fixture['pertemuanA2']->id,
        'dicatat_oleh' => $fixture['instrukturB']->id,
    ]);
});

test('instruktur dapat memilih beberapa kelompok secara bersamaan', function () {
    $fixture = createAbsensiAksesFixture();

    $this->actingAs($fixture['userA'])
        ->get(route('absensi-sik.index', [
            'kelompok_id' => [$fixture['groupA']->id, $fixture['groupB']->id],
        ]))
        ->assertOk()
        ->assertViewHas('selectedGroupIds', [$fixture['groupA']->id, $fixture['groupB']->id])
        ->assertViewHas('absensi', function ($paginator) use ($fixture): bool {
            return $paginator->getCollection()->pluck('id')->sort()->values()->all()
                === collect([$fixture['absensiA']->id, $fixture['absensiB']->id])->sort()->values()->all();
        })
        ->assertViewHas('pertemuan', function ($meetings) use ($fixture): bool {
            $ids = $meetings->pluck('id');

            return ! $ids->contains($fixture['pertemuanC']->id)
                && ! $ids->contains($fixture['pertemuanLama']->id);
        })
        ->assertDontSee($fixture['santriC']->nama);
});

test('filter periode memisahkan kelompok dari periode berbeda', function () {
    $fixture = createAbsensiAksesFixture();

    $this->actingAs($fixture['userA'])
        ->get(route('absensi-sik.index', ['periode_sik_id' => $fixture['periodeAktif']->id]))
        ->assertOk()
        ->assertViewHas('selectedPeriodeId', $fixture['periodeAktif']->id)
        ->assertViewHas('kelompoks', function ($groups) use ($fixture): bool {
            $ids = $groups->pluck('id');

            return $ids->contains($fixture['groupA']->id)
                && ! $ids->contains($fixture['groupLama']->id);
        })
        ->assertViewHas('absensi', fn ($paginator) => ! $paginator->getCollection()
            ->pluck('id')
            ->contains($fixture['absensiLama']->id));

    $this->get(route('absensi-sik.index', ['periode_sik_id' => $fixture['periodeLama']->id]))
        ->assertOk()
        ->assertViewHas('selectedPeriodeId', $fixture['periodeLama']->id)
        ->assertViewHas('kelompoks', function ($groups) use ($fixture): bool {
            return $groups->pluck('id')->all() === [$fixture['groupLama']->id];
        })
        ->assertViewHas('absensi', fn ($paginator) => $paginator->getCollection()
            ->pluck('id')
            ->all() === [$fixture['absensiLama']->id]);
});

test('kelompok nonaktif tidak dapat dipakai untuk absensi baru', function () {
    $fixture = createAbsensiAksesFixture();

    $this->actingAs($fixture['userA'])
        ->post(route('absensi-sik.store'), absensiAksesPayload(
            $fixture,
            $fixture['anggotaNonaktif'],
            $fixture['pertemuanNonaktif']
        ))
        ->assertSessionHasErrors([
            'pertemuan_id' => 'Kelompok target pertemuan tidak aktif.',
        ]);

    $this->assertDatabaseMissing('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggotaNonaktif']->id,
        'pertemuan_id' => $fixture['pertemuanNonaktif']->id,
    ]);
});

test('santri yang bukan anggota kelompok target ditolak', function () {
    $fixture = createAbsensiAksesFixture();

    $this->actingAs($fixture['userA'])
        ->post(route('absensi-sik.store'), absensiAksesPayload(
            $fixture,
            $fixture['anggotaA'],
            $fixture['pertemuanB2']
        ))
        ->assertSessionHasErrors([
            'anggota_periode_sik_id' => 'Santri tidak terdaftar pada kelompok target pertemuan ini.',
        ]);

    $this->assertDatabaseMissing('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggotaA']->id,
        'pertemuan_id' => $fixture['pertemuanB2']->id,
    ]);
});

test('pertemuan dengan kelompok host yang tidak sesuai ditolak', function () {
    $fixture = createAbsensiAksesFixture();
    $pertemuanManipulatif = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['kegiatanB']->id,
        'pertemuan_ke' => 3,
        'tanggal' => '2026-11-21',
        'status' => 'Terjadwal',
        'kelompok_host_id' => $fixture['groupC']->id,
    ]);

    $this->actingAs($fixture['userB'])
        ->post(route('absensi-sik.store'), absensiAksesPayload(
            $fixture,
            $fixture['anggotaB'],
            $pertemuanManipulatif
        ))
        ->assertSessionHasErrors([
            'pertemuan_id' => 'Kelompok host pertemuan dan kelompok kegiatan tidak konsisten.',
        ]);

    $this->assertDatabaseCount('absensi_sik', 4);
});

test('id kelompok dan santri yang dimanipulasi tidak melewati validasi', function () {
    $fixture = createAbsensiAksesFixture();

    $this->actingAs($fixture['userA'])
        ->post(route('absensi-sik.store'), absensiAksesPayload(
            $fixture,
            $fixture['anggotaC'],
            $fixture['pertemuanA2'],
            [
                'kelompok_id' => $fixture['groupB']->id,
                'dicatat_oleh' => $fixture['admin']->id,
            ]
        ))
        ->assertSessionHasErrors('anggota_periode_sik_id');

    $this->assertDatabaseMissing('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggotaC']->id,
        'pertemuan_id' => $fixture['pertemuanA2']->id,
    ]);
    $this->assertDatabaseMissing('absensi_sik', [
        'dicatat_oleh' => $fixture['admin']->id,
    ]);
    $this->assertDatabaseCount('absensi_sik', 4);
});

test('absensi tidak tercatat ganda untuk santri dan pertemuan yang sama', function () {
    $fixture = createAbsensiAksesFixture();

    $this->actingAs($fixture['userA'])
        ->post(route('absensi-sik.store'), absensiAksesPayload($fixture, $fixture['anggotaC'], $fixture['pertemuanC']))
        ->assertSessionHasErrors('pertemuan_id');

    expect(AbsensiSik::query()
        ->where('anggota_periode_sik_id', $fixture['anggotaC']->id)
        ->where('pertemuan_id', $fixture['pertemuanC']->id)
        ->count())->toBe(1);

    $this->assertDatabaseCount('absensi_sik', 4);
});

test('hak akses role lain tetap sesuai aturan', function (UserRole $role) {
    $fixture = createAbsensiAksesFixture();
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->post(route('absensi-sik.store'), absensiAksesPayload($fixture, $fixture['anggotaB'], $fixture['pertemuanB2']))
        ->assertForbidden();

    $this->assertDatabaseCount('absensi_sik', 4);
})->with([
    'santri' => [UserRole::Santri],
    'ka lab mutu' => [UserRole::KaLabMutu],
]);

test('instruktur nonaktif ditolak saat menyimpan absensi', function () {
    $fixture = createAbsensiAksesFixture();

    $this->actingAs($fixture['userNonaktif'])
        ->post(route('absensi-sik.store'), absensiAksesPayload($fixture, $fixture['anggotaB'], $fixture['pertemuanB2']))
        ->assertForbidden();

    $this->assertDatabaseCount('absensi_sik', 4);
});

test('admin tetap dapat menyimpan absensi', function () {
    $fixture = createAbsensiAksesFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('absensi-sik.store'), absensiAksesPayload($fixture, $fixture['anggotaB'], $fixture['pertemuanB2']))
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggotaB']->id,
        'pertemuan_id' => $fixture['pertemuanB2']->id,
        'dicatat_oleh' => null,
    ]);
});
