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
use Illuminate\Support\Facades\File;

/** @return array<string, mixed> */
function createNilaiRevisionFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $user = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instruktur = InfrastrukturSik::query()->create([
        'user_id' => $user->id,
        'nama' => 'Instruktur Nilai '.$suffix,
        'status' => 'Aktif',
    ]);

    $otherUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $otherInstruktur = InfrastrukturSik::query()->create([
        'user_id' => $otherUser->id,
        'nama' => 'Instruktur Lain '.$suffix,
        'status' => 'Aktif',
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $period = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Nilai '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);

    $createGroup = function (string $nama, InfrastrukturSik $owner, string $status = 'Aktif') use ($period, $suffix): Kelompok {
        return Kelompok::query()->create([
            'periode_sik_id' => $period->id,
            'instruktur_id' => $owner->id,
            'nama_kelompok' => $nama.' '.$suffix,
            'status' => $status,
        ]);
    };

    $groupA = $createGroup('Kelompok Nilai A', $instruktur);
    $groupB = $createGroup('Kelompok Nilai B', $instruktur);
    $groupNonaktif = $createGroup('Kelompok Nilai Nonaktif', $instruktur, 'Nonaktif');
    $otherGroup = $createGroup('Kelompok Orang Lain', $otherInstruktur);

    $createSantri = function (string $nama, string $nim): Santri {
        return Santri::query()->create([
            'nomor_induk' => $nim,
            'nama' => $nama.' '.fake()->unique()->numerify('######'),
            'jenis_kelamin' => 'Laki-laki',
            'status' => 'Aktif',
        ]);
    };

    $santriA = $createSantri('Santri Nilai A', 'NILR-A-'.$suffix);
    $santriB = $createSantri('Santri Nilai B', 'NILR-B-'.$suffix);
    $santriNonaktif = $createSantri('Santri Nilai Nonaktif', 'NILR-N-'.$suffix);
    $santriOther = $createSantri('Santri Orang Lain', 'NILR-O-'.$suffix);

    $createAnggota = function (Santri $santri, Kelompok $group) use ($period): AnggotaPeriodeSik {
        return AnggotaPeriodeSik::query()->create([
            'santri_id' => $santri->id,
            'periode_sik_id' => $period->id,
            'kelompok_id' => $group->id,
            'status' => 'Aktif',
        ]);
    };

    $anggotaA = $createAnggota($santriA, $groupA);
    $anggotaB = $createAnggota($santriB, $groupB);
    $anggotaNonaktif = $createAnggota($santriNonaktif, $groupNonaktif);
    $anggotaOther = $createAnggota($santriOther, $otherGroup);

    $mapel = MataPelajaran::query()->create([
        'kode_mapel' => 'NILR-'.$suffix,
        'nama_mapel' => 'Mapel Nilai '.$suffix,
        'sks' => 2,
        'semester' => 1,
        'status' => true,
    ]);

    $nilaiA = Nilai::query()->create([
        'santri_id' => $santriA->id,
        'mata_pelajaran_id' => $mapel->id,
        'infrastruktur_sik_id' => $instruktur->id,
        'semester' => '1',
        'tahun_ajaran' => '2026',
        'nilai' => 80,
        'keterangan' => 'Nilai awal',
    ]);

    $kegiatan = Kegiatan::query()->create([
        'periode_sik_id' => $period->id,
        'kelompok_id' => $groupA->id,
        'nama_kegiatan' => 'Kegiatan Nilai '.$suffix,
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);

    $pertemuan = Pertemuan::query()->create([
        'kegiatan_id' => $kegiatan->id,
        'pertemuan_ke' => 1,
        'tanggal' => '2026-11-20',
        'status' => 'Terjadwal',
        'kelompok_host_id' => $groupA->id,
    ]);

    $absensi = AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $anggotaA->id,
        'pertemuan_id' => $pertemuan->id,
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
    ]);

    return compact(
        'suffix',
        'user',
        'instruktur',
        'otherUser',
        'otherInstruktur',
        'admin',
        'period',
        'groupA',
        'groupB',
        'groupNonaktif',
        'otherGroup',
        'santriA',
        'santriB',
        'santriNonaktif',
        'santriOther',
        'anggotaA',
        'anggotaB',
        'anggotaNonaktif',
        'anggotaOther',
        'mapel',
        'nilaiA',
        'kegiatan',
        'pertemuan',
        'absensi'
    );
}

/** @param array<string, mixed> $overrides */
function nilaiRevisionPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'santri_id' => $fixture['santriB']->id,
        'nama_mapel' => 'Mapel Baru '.$fixture['suffix'],
        'semester' => '3',
        'tahun_ajaran' => '2026',
        'nilai' => 91,
        'keterangan' => 'Nilai revisi',
    ], $overrides);
}

test('instruktur hanya melihat kelompok aktif tanggung jawabnya pada halaman nilai', function () {
    $fixture = createNilaiRevisionFixture();

    $this->actingAs($fixture['user'])
        ->get(route('nilai.index'))
        ->assertOk()
        ->assertViewHas('kelompoks', function ($groups) use ($fixture): bool {
            return $groups->modelKeys() === [$fixture['groupA']->id, $fixture['groupB']->id];
        })
        ->assertViewHas('santris', function ($santris) use ($fixture): bool {
            return $santris->modelKeys() === [$fixture['santriA']->id, $fixture['santriB']->id];
        })
        ->assertSee($fixture['groupA']->nama_kelompok)
        ->assertSee($fixture['groupB']->nama_kelompok)
        ->assertDontSee($fixture['groupNonaktif']->nama_kelompok)
        ->assertDontSee($fixture['otherGroup']->nama_kelompok)
        ->assertDontSee($fixture['santriOther']->nama)
        ->assertDontSee($fixture['santriNonaktif']->nama)
        ->assertDontSee('Semua kelompok')
        ->assertSee('Kelompok saya');
});

test('kelompok nonaktif dan kelompok instruktur lain ditolak melalui parameter nilai', function () {
    $fixture = createNilaiRevisionFixture();

    $this->actingAs($fixture['user'])
        ->get(route('nilai.index', ['kelompok_id' => [$fixture['groupNonaktif']->id]]))
        ->assertForbidden();

    $this->get(route('nilai.index', ['kelompok_id' => [$fixture['otherGroup']->id]]))
        ->assertForbidden();

    $this->get(route('nilai.index', ['kelompok_id' => $fixture['otherGroup']->id]))
        ->assertForbidden();
});

test('santri kelompok lain dan kelompok nonaktif tidak dapat dinilai lewat manipulasi', function () {
    $fixture = createNilaiRevisionFixture();

    $this->actingAs($fixture['user'])
        ->post(route('nilai.store'), nilaiRevisionPayload($fixture, [
            'santri_id' => $fixture['santriOther']->id,
        ]))
        ->assertForbidden();

    $this->post(route('nilai.store'), nilaiRevisionPayload($fixture, [
        'santri_id' => $fixture['santriNonaktif']->id,
    ]))
        ->assertForbidden();

    $this->assertDatabaseMissing('nilai', [
        'santri_id' => $fixture['santriOther']->id,
        'nilai' => 91,
    ]);
    $this->assertDatabaseMissing('nilai', [
        'santri_id' => $fixture['santriNonaktif']->id,
        'nilai' => 91,
    ]);
});

test('penyimpanan nilai pada kelompok tanggung jawab tetap berfungsi', function () {
    $fixture = createNilaiRevisionFixture();

    $this->actingAs($fixture['user'])
        ->post(route('nilai.store'), nilaiRevisionPayload($fixture))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('nilai', [
        'santri_id' => $fixture['santriB']->id,
        'infrastruktur_sik_id' => $fixture['instruktur']->id,
        'nilai' => 91,
        'keterangan' => 'Nilai revisi',
    ]);

    $this->get(route('nilai.index', ['kelompok_id' => [$fixture['groupB']->id]]))
        ->assertOk()
        ->assertSee('91')
        ->assertSee('Mapel Baru '.$fixture['suffix']);
});

test('deskripsi yang diminta dihapus dan judul halaman tetap ada', function () {
    $fixture = createNilaiRevisionFixture();

    $this->actingAs($fixture['user'])
        ->get(route('nilai.index'))
        ->assertOk()
        ->assertSee('Input dan Pengelolaan Nilai')
        ->assertDontSee('Nilai hanya dapat dikelola untuk santri di kelompok aktif yang menjadi tanggung jawab Anda.')
        ->assertDontSee('Ketik nama mata pelajaran. Sistem memakai data master yang sudah ada atau menambahkan mata pelajaran baru bila belum tersedia.');
});

test('halaman absensi tetap mendukung banyak kelompok filter periode dan semua kelompok', function () {
    $fixture = createNilaiRevisionFixture();

    $this->actingAs($fixture['user'])
        ->get(route('absensi-sik.index', [
            'kelompok_id' => [$fixture['groupA']->id, $fixture['groupB']->id],
        ]))
        ->assertOk()
        ->assertViewHas('selectedGroupIds', [$fixture['groupA']->id, $fixture['groupB']->id])
        ->assertViewHas('kelompoks', function ($groups) use ($fixture): bool {
            $ids = $groups->pluck('id');

            return $ids->contains($fixture['groupA']->id)
                && $ids->contains($fixture['groupB']->id)
                && $ids->contains($fixture['otherGroup']->id)
                && ! $ids->contains($fixture['groupNonaktif']->id);
        })
        ->assertSee('Semua kelompok')
        ->assertSee('data-kelompok-multiselect', false)
        ->assertSee($fixture['groupA']->nama_kelompok)
        ->assertSee($fixture['groupB']->nama_kelompok);

    $this->get(route('absensi-sik.index', ['periode_sik_id' => $fixture['period']->id]))
        ->assertOk()
        ->assertViewHas('selectedPeriodeId', $fixture['period']->id)
        ->assertSee('Semua kelompok');
});

test('hak akses nilai untuk peran lain tidak berubah', function (UserRole $role) {
    $fixture = createNilaiRevisionFixture();
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('nilai.index'))
        ->assertForbidden();

    $this->post(route('nilai.store'), nilaiRevisionPayload($fixture))
        ->assertForbidden();
})->with([
    'santri' => [UserRole::Santri],
    'ka lab mutu' => [UserRole::KaLabMutu],
]);

test('admin tetap dapat membaca nilai tetapi tidak dapat menulisnya', function () {
    $fixture = createNilaiRevisionFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('nilai.index'))
        ->assertOk()
        ->assertSee($fixture['santriA']->nama)
        ->assertSee($fixture['mapel']->nama_mapel);

    $this->post(route('nilai.store'), nilaiRevisionPayload($fixture))
        ->assertForbidden();

    $this->put(route('nilai.update', $fixture['nilaiA']), nilaiRevisionPayload($fixture))
        ->assertForbidden();
});

test('tampilan nilai memakai aset dan tata letak responsif', function () {
    $fixture = createNilaiRevisionFixture();

    $this->actingAs($fixture['user'])
        ->get(route('nilai.index'))
        ->assertOk()
        ->assertSee('Css/nilai.css', false)
        ->assertSee('nilai-filter', false)
        ->assertSee('nilai-student-meta', false)
        ->assertSee('nilai-form-grid', false)
        ->assertSee('nilai-add-details', false)
        ->assertSee('Mata pelajaran')
        ->assertSee('Semester')
        ->assertSee('Tahun ajaran')
        ->assertSee('Nilai (0–100)')
        ->assertSee('Keterangan')
        ->assertSee('Simpan')
        ->assertSee('Tambah nilai');

    $css = File::get(public_path('Css/nilai.css'));

    expect($css)
        ->toContain('@media (max-width: 1180px)')
        ->toContain('@media (max-width: 760px)')
        ->toContain('grid-template-columns: 1fr;')
        ->toContain('min-width: 0');
});
