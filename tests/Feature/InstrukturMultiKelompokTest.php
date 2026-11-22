<?php

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\Santri;
use App\Models\User;

/** @return array<string, mixed> */
function createMultiKelompokFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Multi '.$suffix,
        'status' => 'Aktif',
    ]);
    $otherUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $otherInstructor = InfrastrukturSik::query()->create([
        'user_id' => $otherUser->id,
        'nama' => 'Instruktur Lain '.$suffix,
        'status' => 'Aktif',
    ]);

    $period = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Multi '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);

    $createGroup = function (string $nama, InfrastrukturSik $owner) use ($period): Kelompok {
        return Kelompok::query()->create([
            'periode_sik_id' => $period->id,
            'instruktur_id' => $owner->id,
            'nama_kelompok' => $nama,
            'status' => 'Aktif',
        ]);
    };

    $groupA = $createGroup('Kelompok Alfa '.$suffix, $instructor);
    $groupB = $createGroup('Kelompok Beta '.$suffix, $instructor);
    $otherGroup = $createGroup('Kelompok Orang Lain '.$suffix, $otherInstructor);

    $createSantri = function (string $nama, string $nim): Santri {
        return Santri::query()->create([
            'nomor_induk' => $nim,
            'nama' => $nama,
            'jenis_kelamin' => 'Laki-laki',
            'status' => 'Aktif',
        ]);
    };

    $santriA = $createSantri('Santri Alfa '.$suffix, 'MUL-A-'.$suffix);
    $santriB = $createSantri('Santri Beta '.$suffix, 'MUL-B-'.$suffix);
    $santriCari = $createSantri('Santri Cari '.$suffix, 'MUL-C-'.$suffix);
    $santriOther = $createSantri('Santri Orang Lain '.$suffix, 'MUL-O-'.$suffix);

    $createMembership = function (Santri $santri, Kelompok $group) use ($period): AnggotaPeriodeSik {
        return AnggotaPeriodeSik::query()->create([
            'santri_id' => $santri->id,
            'periode_sik_id' => $period->id,
            'kelompok_id' => $group->id,
            'status' => 'Aktif',
        ]);
    };

    $anggotaA = $createMembership($santriA, $groupA);
    $anggotaB = $createMembership($santriB, $groupB);
    $anggotaCari = $createMembership($santriCari, $groupA);
    $anggotaOther = $createMembership($santriOther, $otherGroup);

    $createKegiatan = function (string $nama, Kelompok $group) use ($period): Kegiatan {
        return Kegiatan::query()->create([
            'periode_sik_id' => $period->id,
            'kelompok_id' => $group->id,
            'nama_kegiatan' => $nama,
            'jenis_kegiatan' => 'Kelas',
            'status' => 'Aktif',
        ]);
    };

    $kegiatanA = $createKegiatan('teknologi', $groupA);
    $kegiatanB = $createKegiatan('Praktikum '.$suffix, $groupB);
    $kegiatanOther = $createKegiatan('Kegiatan Lain '.$suffix, $otherGroup);

    $createPertemuan = function (Kegiatan $kegiatan, Kelompok $group): Pertemuan {
        return Pertemuan::query()->create([
            'kegiatan_id' => $kegiatan->id,
            'pertemuan_ke' => 1,
            'tanggal' => '2026-11-20',
            'status' => 'Terlaksana',
            'kelompok_host_id' => $group->id,
        ]);
    };

    $pertemuanA = $createPertemuan($kegiatanA, $groupA);
    $pertemuanB = $createPertemuan($kegiatanB, $groupB);
    $pertemuanOther = $createPertemuan($kegiatanOther, $otherGroup);

    $createAbsensi = function (AnggotaPeriodeSik $anggota, Pertemuan $pertemuan) use ($instructor): AbsensiSik {
        return AbsensiSik::query()->create([
            'anggota_periode_sik_id' => $anggota->id,
            'pertemuan_id' => $pertemuan->id,
            'metode_absensi' => 'Manual',
            'status' => 'Hadir',
            'dicatat_oleh' => $instructor->id,
        ]);
    };

    $absensiA = $createAbsensi($anggotaA, $pertemuanA);
    $absensiB = $createAbsensi($anggotaB, $pertemuanB);
    $absensiCari = $createAbsensi($anggotaCari, $pertemuanA);
    $absensiOther = $createAbsensi($anggotaOther, $pertemuanOther);

    $mapel = MataPelajaran::query()->create([
        'kode_mapel' => 'MUL-'.$suffix,
        'nama_mapel' => 'Mapel Multi '.$suffix,
        'sks' => 2,
        'semester' => 1,
        'status' => true,
    ]);

    $createNilai = function (Santri $santri, int $nilai) use ($mapel, $instructor): Nilai {
        return Nilai::query()->create([
            'santri_id' => $santri->id,
            'mata_pelajaran_id' => $mapel->id,
            'infrastruktur_sik_id' => $instructor->id,
            'semester' => '1',
            'tahun_ajaran' => '2026',
            'nilai' => $nilai,
            'keterangan' => 'Nilai awal',
        ]);
    };

    $nilaiA = $createNilai($santriA, 80);
    $nilaiB = $createNilai($santriB, 82);
    $nilaiOther = Nilai::query()->create([
        'santri_id' => $santriOther->id,
        'mata_pelajaran_id' => $mapel->id,
        'infrastruktur_sik_id' => $otherInstructor->id,
        'semester' => '1',
        'tahun_ajaran' => '2026',
        'nilai' => 70,
    ]);

    return compact(
        'suffix',
        'instructorUser',
        'instructor',
        'otherUser',
        'otherInstructor',
        'period',
        'groupA',
        'groupB',
        'otherGroup',
        'santriA',
        'santriB',
        'santriCari',
        'santriOther',
        'anggotaA',
        'anggotaB',
        'anggotaCari',
        'anggotaOther',
        'kegiatanA',
        'kegiatanB',
        'pertemuanA',
        'pertemuanB',
        'pertemuanOther',
        'absensiA',
        'absensiB',
        'absensiCari',
        'absensiOther',
        'mapel',
        'nilaiA',
        'nilaiB',
        'nilaiOther'
    );
}

/** @param array<string, mixed> $fixture */
function nilaiMultiPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'santri_id' => $fixture['santriB']->id,
        'nama_mapel' => $fixture['mapel']->nama_mapel,
        'semester' => '2',
        'tahun_ajaran' => '2026',
        'nilai' => 93,
        'keterangan' => 'Nilai kelompok kedua',
    ], $overrides);
}

/** @param array<string, mixed> $fixture */
function absensiIds(array $fixture): array
{
    return [
        $fixture['absensiA']->id,
        $fixture['absensiB']->id,
        $fixture['absensiCari']->id,
        $fixture['absensiOther']->id,
    ];
}

test('instruktur melihat semua kelompok aktif sebagai pilihan di halaman absensi', function () {
    $fixture = createMultiKelompokFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('absensi-sik.index'))
        ->assertOk()
        ->assertViewHas('kelompoks', function ($groups) use ($fixture): bool {
            return $groups->pluck('id')->sort()->values()->all()
                === collect([
                    $fixture['groupA']->id,
                    $fixture['groupB']->id,
                    $fixture['otherGroup']->id,
                ])->sort()->values()->all();
        })
        ->assertSee($fixture['groupA']->nama_kelompok)
        ->assertSee($fixture['groupB']->nama_kelompok)
        ->assertSee($fixture['otherGroup']->nama_kelompok);
});

test('instruktur dapat membuka absensi dengan kelompok milik instruktur lain', function () {
    $fixture = createMultiKelompokFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('absensi-sik.index', ['kelompok_id' => [$fixture['otherGroup']->id]]))
        ->assertOk()
        ->assertViewHas('selectedGroupIds', [$fixture['otherGroup']->id])
        ->assertViewHas('absensi', fn ($paginator) => $paginator->getCollection()
            ->pluck('id')
            ->all() === [$fixture['absensiOther']->id]);

    $this->get(route('absensi-sik.index', ['kelompok_id' => $fixture['otherGroup']->id]))
        ->assertOk()
        ->assertViewHas('selectedGroupIds', [$fixture['otherGroup']->id]);
});

test('filter satu kelompok pada absensi hanya menampilkan catatan kelompok tersebut', function () {
    $fixture = createMultiKelompokFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('absensi-sik.index', ['kelompok_id' => [$fixture['groupA']->id]]))
        ->assertOk()
        ->assertViewHas('selectedGroupIds', [$fixture['groupA']->id])
        ->assertViewHas('absensi', function ($paginator) use ($fixture): bool {
            return $paginator->getCollection()->pluck('id')->sort()->values()->all()
                === collect([$fixture['absensiA']->id, $fixture['absensiCari']->id])->sort()->values()->all();
        })
        ->assertViewHas('anggota', function ($members) use ($fixture): bool {
            return $members->pluck('id')->sort()->values()->all()
                === collect([$fixture['anggotaA']->id, $fixture['anggotaCari']->id])->sort()->values()->all();
        })
        ->assertDontSee($fixture['santriB']->nama)
        ->assertDontSee($fixture['santriOther']->nama);
});

test('filter beberapa kelompok pada absensi menggabungkan catatan kelompok terpilih', function () {
    $fixture = createMultiKelompokFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('absensi-sik.index', [
            'kelompok_id' => [$fixture['groupA']->id, $fixture['groupB']->id],
        ]))
        ->assertOk()
        ->assertViewHas('absensi', function ($paginator) use ($fixture): bool {
            return $paginator->getCollection()->pluck('id')->sort()->values()->all()
                === collect([
                    $fixture['absensiA']->id,
                    $fixture['absensiB']->id,
                    $fixture['absensiCari']->id,
                ])->sort()->values()->all();
        })
        ->assertViewHas('pertemuan', function ($meetings) use ($fixture): bool {
            return $meetings->pluck('id')->sort()->values()->all()
                === collect([$fixture['pertemuanA']->id, $fixture['pertemuanB']->id])->sort()->values()->all();
        })
        ->assertDontSee($fixture['pertemuanOther']->kegiatan->nama_kegiatan);
});

test('pilihan semua kelompok pada absensi menampilkan seluruh catatan instruktur', function () {
    $fixture = createMultiKelompokFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('absensi-sik.index', ['kelompok_id' => ['']]))
        ->assertOk()
        ->assertViewHas('selectedGroupIds', [])
        ->assertViewHas('absensi', function ($paginator) use ($fixture): bool {
            return $paginator->getCollection()->pluck('id')->sort()->values()->all()
                === collect(absensiIds($fixture))->sort()->values()->all();
        });
});

test('pencarian nama dan nim tetap berfungsi bersama filter kelompok pada absensi', function () {
    $fixture = createMultiKelompokFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('absensi-sik.index', [
            'q' => $fixture['santriB']->nama,
            'kelompok_id' => [$fixture['groupB']->id],
        ]))
        ->assertOk()
        ->assertViewHas('absensi', fn ($paginator) => $paginator->getCollection()
            ->pluck('id')
            ->all() === [$fixture['absensiB']->id]);

    $this->get(route('absensi-sik.index', [
        'q' => $fixture['santriCari']->nomor_induk,
        'kelompok_id' => [$fixture['groupA']->id],
    ]))
        ->assertOk()
        ->assertViewHas('absensi', fn ($paginator) => $paginator->getCollection()
            ->pluck('id')
            ->all() === [$fixture['absensiCari']->id]);
});

test('identitas kelompok dan kegiatan tampil benar pada absensi', function () {
    $fixture = createMultiKelompokFixture();
    $periodeLain = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Lain '.$fixture['suffix'],
        'tahun' => 2025,
        'tanggal_mulai' => '2025-01-01',
        'tanggal_selesai' => '2025-12-31',
        'status' => 'Selesai',
    ]);
    $groupLain = Kelompok::query()->create([
        'periode_sik_id' => $periodeLain->id,
        'instruktur_id' => $fixture['instructor']->id,
        'nama_kelompok' => 'Kelompok Periode Lain '.$fixture['suffix'],
        'status' => 'Aktif',
    ]);
    $anggotaLain = AnggotaPeriodeSik::query()->create([
        'santri_id' => $fixture['santriA']->id,
        'periode_sik_id' => $periodeLain->id,
        'kelompok_id' => $groupLain->id,
        'status' => 'Aktif',
    ]);
    $kegiatanLain = Kegiatan::query()->create([
        'periode_sik_id' => $periodeLain->id,
        'kelompok_id' => $groupLain->id,
        'nama_kegiatan' => 'Kegiatan Periode Lain '.$fixture['suffix'],
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);
    $pertemuanLain = Pertemuan::query()->create([
        'kegiatan_id' => $kegiatanLain->id,
        'pertemuan_ke' => 1,
        'tanggal' => '2025-11-20',
        'status' => 'Terlaksana',
        'kelompok_host_id' => $groupLain->id,
    ]);
    $absensiLain = AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $anggotaLain->id,
        'pertemuan_id' => $pertemuanLain->id,
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
    ]);

    $this->actingAs($fixture['instructorUser'])
        ->get(route('absensi-sik.index'))
        ->assertOk()
        ->assertSee('Kegiatan: teknologi')
        ->assertSee($fixture['groupA']->nama_kelompok)
        ->assertSee($fixture['groupB']->nama_kelompok)
        ->assertSee($groupLain->nama_kelompok)
        ->assertSee($periodeLain->nama_periode)
        ->assertDontSee('</strong><span class="d-block small text-muted">teknologi')
        ->assertViewHas('absensi', function ($paginator) use ($fixture, $absensiLain): bool {
            $ids = $paginator->getCollection()->pluck('id');

            return $ids->contains($fixture['absensiA']->id)
                && $ids->contains($absensiLain->id);
        });

    $this->get(route('absensi-sik.index', ['periode_sik_id' => $fixture['period']->id]))
        ->assertOk()
        ->assertViewHas('kelompoks', fn ($groups) => ! $groups->pluck('id')->contains($groupLain->id))
        ->assertViewHas('absensi', fn ($paginator) => ! $paginator->getCollection()
            ->pluck('id')
            ->contains($absensiLain->id))
        ->assertDontSee($groupLain->nama_kelompok);
});

test('instruktur melihat seluruh kelompoknya sebagai pilihan di halaman nilai', function () {
    $fixture = createMultiKelompokFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('nilai.index'))
        ->assertOk()
        ->assertViewHas('kelompoks', function ($groups) use ($fixture): bool {
            return $groups->pluck('id')->sort()->values()->all()
                === collect([$fixture['groupA']->id, $fixture['groupB']->id])->sort()->values()->all();
        })
        ->assertSee($fixture['groupA']->nama_kelompok)
        ->assertSee($fixture['groupB']->nama_kelompok)
        ->assertDontSee($fixture['otherGroup']->nama_kelompok);
});

test('instruktur tidak dapat membuka nilai dengan kelompok milik instruktur lain', function () {
    $fixture = createMultiKelompokFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('nilai.index', ['kelompok_id' => [$fixture['otherGroup']->id]]))
        ->assertForbidden();

    $this->get(route('nilai.index', ['kelompok_id' => $fixture['otherGroup']->id]))
        ->assertForbidden();
});

test('filter satu kelompok pada nilai hanya menampilkan santri kelompok tersebut', function () {
    $fixture = createMultiKelompokFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('nilai.index', ['kelompok_id' => [$fixture['groupB']->id]]))
        ->assertOk()
        ->assertViewHas('selectedGroupIds', [$fixture['groupB']->id])
        ->assertViewHas('santris', fn ($santris) => $santris->modelKeys() === [$fixture['santriB']->id])
        ->assertSee($fixture['santriB']->nama)
        ->assertDontSee($fixture['santriA']->nama)
        ->assertDontSee($fixture['santriOther']->nama);
});

test('filter beberapa kelompok dan pencarian berfungsi pada nilai', function () {
    $fixture = createMultiKelompokFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('nilai.index', [
            'kelompok_id' => [$fixture['groupA']->id, $fixture['groupB']->id],
        ]))
        ->assertOk()
        ->assertViewHas('santris', function ($santris) use ($fixture): bool {
            return $santris->modelKeys() === [
                $fixture['santriA']->id,
                $fixture['santriB']->id,
                $fixture['santriCari']->id,
            ];
        })
        ->assertDontSee($fixture['santriOther']->nama);

    $this->get(route('nilai.index', [
        'q' => $fixture['santriCari']->nomor_induk,
        'kelompok_id' => [$fixture['groupA']->id, $fixture['groupB']->id],
    ]))
        ->assertOk()
        ->assertViewHas('santris', fn ($santris) => $santris->modelKeys() === [$fixture['santriCari']->id]);
});

test('instruktur dapat menilai santri pada kelompok keduanya tanpa menukar nilai lama', function () {
    $fixture = createMultiKelompokFixture();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), nilaiMultiPayload($fixture))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('nilai', [
        'santri_id' => $fixture['santriB']->id,
        'mata_pelajaran_id' => $fixture['mapel']->id,
        'infrastruktur_sik_id' => $fixture['instructor']->id,
        'semester' => '2',
        'nilai' => 93,
    ]);
    $this->assertDatabaseHas('nilai', [
        'id' => $fixture['nilaiA']->id,
        'santri_id' => $fixture['santriA']->id,
        'nilai' => 80,
    ]);

    $this->get(route('nilai.index', ['kelompok_id' => [$fixture['groupB']->id]]))
        ->assertOk()
        ->assertViewHas('santris', function ($santris) use ($fixture): bool {
            return $santris->firstWhere('id', $fixture['santriB']->id)
                ?->nilai->contains(fn ($nilai): bool => (int) $nilai->nilai === 93) === true;
        })
        ->assertSee('93');

    $this->get(route('nilai.index', ['kelompok_id' => [$fixture['groupA']->id]]))
        ->assertOk()
        ->assertViewHas('santris', function ($santris) use ($fixture): bool {
            return $santris->firstWhere('id', $fixture['santriA']->id)
                ?->nilai->pluck('nilai')->all() === [80];
        })
        ->assertSee($fixture['mapel']->nama_mapel);
});

test('instruktur tidak dapat menilai santri kelompok lain dan form edit tetap tersedia', function () {
    $fixture = createMultiKelompokFixture();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), nilaiMultiPayload($fixture, [
            'santri_id' => $fixture['santriOther']->id,
            'semester' => '3',
        ]))
        ->assertForbidden();

    $this->assertDatabaseMissing('nilai', [
        'santri_id' => $fixture['santriOther']->id,
        'nilai' => 93,
    ]);

    $this->get(route('nilai.index', ['kelompok_id' => [$fixture['groupA']->id]]))
        ->assertOk()
        ->assertSee('Tambah nilai')
        ->assertSee(route('nilai.update', $fixture['nilaiA']), false)
        ->assertSee($fixture['mapel']->nama_mapel)
        ->assertDontSee($fixture['santriOther']->nama);
});
