<?php

use App\Enums\UserRole;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\PeriodeSik;
use App\Models\Santri;
use App\Models\User;

function createNilaiFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Nilai '.$suffix,
        'status' => 'Aktif',
    ]);
    $otherInstructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $otherInstructor = InfrastrukturSik::query()->create([
        'user_id' => $otherInstructorUser->id,
        'nama' => 'Instruktur Lain '.$suffix,
        'status' => 'Aktif',
    ]);

    $period = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Nilai '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $group = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Nilai '.$suffix,
        'status' => 'Aktif',
    ]);
    $otherGroup = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $otherInstructor->id,
        'nama_kelompok' => 'Kelompok Lain '.$suffix,
        'status' => 'Aktif',
    ]);
    Kegiatan::query()->create([
        'periode_sik_id' => $period->id,
        'kelompok_id' => $group->id,
        'nama_kegiatan' => 'Kegiatan Nilai '.$suffix,
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);

    $santri = Santri::query()->create([
        'nomor_induk' => 'NIL-'.$suffix,
        'nama' => 'Santri Nilai '.$suffix,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $otherSantri = Santri::query()->create([
        'nomor_induk' => 'NIL-O-'.$suffix,
        'nama' => 'Santri Lain '.$suffix,
        'jenis_kelamin' => 'Perempuan',
        'status' => 'Aktif',
    ]);

    AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $period->id,
        'kelompok_id' => $group->id,
        'status' => 'Aktif',
    ]);
    AnggotaPeriodeSik::query()->create([
        'santri_id' => $otherSantri->id,
        'periode_sik_id' => $period->id,
        'kelompok_id' => $otherGroup->id,
        'status' => 'Aktif',
    ]);

    $activeMapel = MataPelajaran::query()->create([
        'kode_mapel' => 'MTA-'.$suffix,
        'nama_mapel' => 'Mapel Aktif '.$suffix,
        'sks' => 2,
        'semester' => 1,
        'status' => true,
    ]);
    $legacyMapel = MataPelajaran::query()->create([
        'kode_mapel' => 'MTL-'.$suffix,
        'nama_mapel' => 'Mapel Lama '.$suffix,
        'sks' => 2,
        'semester' => 2,
        'status' => false,
    ]);
    $legacyNilai = Nilai::query()->create([
        'santri_id' => $santri->id,
        'mata_pelajaran_id' => $legacyMapel->id,
        'infrastruktur_sik_id' => $instructor->id,
        'semester' => '2',
        'tahun_ajaran' => '2026',
        'nilai' => 80,
    ]);

    return compact(
        'admin',
        'instructorUser',
        'instructor',
        'otherInstructorUser',
        'otherInstructor',
        'period',
        'group',
        'otherGroup',
        'santri',
        'otherSantri',
        'activeMapel',
        'legacyMapel',
        'legacyNilai'
    );
}

/** @return array<string, mixed> */
function nilaiPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'santri_id' => $fixture['santri']->id,
        'nama_mapel' => $fixture['activeMapel']->nama_mapel,
        'semester' => '1',
        'tahun_ajaran' => '2026',
        'nilai' => 92,
        'keterangan' => 'Nilai audit',
    ], $overrides);
}

test('instruktur menyimpan nilai santri kelompoknya lalu admin dapat melihatnya', function () {
    $fixture = createNilaiFixture();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), nilaiPayload($fixture))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('nilai', [
        'santri_id' => $fixture['santri']->id,
        'mata_pelajaran_id' => $fixture['activeMapel']->id,
        'infrastruktur_sik_id' => $fixture['instructor']->id,
        'nilai' => 92,
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('nilai.index', ['q' => $fixture['santri']->nama]))
        ->assertOk()
        ->assertSee($fixture['santri']->nama)
        ->assertSee($fixture['activeMapel']->nama_mapel)
        ->assertSee('92');
});

test('instruktur tidak dapat menilai santri kelompok lain atau nilai milik instruktur lain', function () {
    $fixture = createNilaiFixture();
    $otherNilai = Nilai::query()->create([
        'santri_id' => $fixture['otherSantri']->id,
        'mata_pelajaran_id' => $fixture['activeMapel']->id,
        'infrastruktur_sik_id' => $fixture['otherInstructor']->id,
        'semester' => '1',
        'tahun_ajaran' => '2026',
        'nilai' => 70,
    ]);

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), nilaiPayload($fixture, [
            'santri_id' => $fixture['otherSantri']->id,
            'semester' => '2',
        ]))
        ->assertForbidden();
    $this->assertDatabaseMissing('nilai', [
        'santri_id' => $fixture['otherSantri']->id,
        'nilai' => 92,
    ]);

    $this->actingAs($fixture['instructorUser'])
        ->put(route('nilai.update', $otherNilai), nilaiPayload($fixture, [
            'santri_id' => $fixture['otherSantri']->id,
            'nilai' => 95,
        ]))
        ->assertForbidden();
    $this->assertDatabaseHas('nilai', [
        'id' => $otherNilai->id,
        'nilai' => 70,
        'infrastruktur_sik_id' => $fixture['otherInstructor']->id,
    ]);
});

test('nilai lama dengan mata pelajaran nonaktif tetap tersedia dan dapat dipertahankan', function () {
    $fixture = createNilaiFixture();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('nilai.index'))
        ->assertOk()
        ->assertSee($fixture['legacyMapel']->nama_mapel)
        ->assertSee('80')
        ->assertDontSee('Pemrograman Web');

    $this->put(route('nilai.update', $fixture['legacyNilai']), nilaiPayload($fixture, [
        'nama_mapel' => $fixture['legacyMapel']->nama_mapel,
        'semester' => '2',
        'nilai' => 85,
    ]))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('nilai', [
        'id' => $fixture['legacyNilai']->id,
        'mata_pelajaran_id' => $fixture['legacyMapel']->id,
        'nilai' => 85,
    ]);
});

test('mengetik nama mata pelajaran lama memakai ulang master yang sama tanpa duplikasi', function () {
    $fixture = createNilaiFixture();
    $jumlahMapelAwal = MataPelajaran::query()->count();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), nilaiPayload($fixture, [
            'nama_mapel' => '  '.mb_strtoupper($fixture['legacyMapel']->nama_mapel).'  ',
            'semester' => '3',
            'nilai' => 92,
        ]))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHas('success');

    expect(MataPelajaran::query()->count())->toBe($jumlahMapelAwal);

    $this->assertDatabaseHas('nilai', [
        'santri_id' => $fixture['santri']->id,
        'mata_pelajaran_id' => $fixture['legacyMapel']->id,
        'semester' => '3',
        'nilai' => 92,
    ]);
});

test('nama mata pelajaran baru dibuat sebagai master dan dipakai pada nilai', function () {
    $fixture = createNilaiFixture();
    $jumlahMapelAwal = MataPelajaran::query()->count();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), nilaiPayload($fixture, [
            'nama_mapel' => 'Basis Data Terdistribusi',
            'semester' => '3',
        ]))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHas('success');

    expect(MataPelajaran::query()->count())->toBe($jumlahMapelAwal + 1);

    $mapelBaru = MataPelajaran::query()->where('nama_mapel', 'Basis Data Terdistribusi')->firstOrFail();
    expect($mapelBaru->status)->toBeTrue();

    $this->assertDatabaseHas('nilai', [
        'santri_id' => $fixture['santri']->id,
        'mata_pelajaran_id' => $mapelBaru->id,
        'nilai' => 92,
    ]);
});

test('nilai ganda untuk mata pelajaran, semester, dan tahun yang sama ditolak', function () {
    $fixture = createNilaiFixture();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), nilaiPayload($fixture))
        ->assertRedirect(route('nilai.index'));

    $this->post(route('nilai.store'), nilaiPayload($fixture))
        ->assertSessionHasErrors('nama_mapel');

    $this->assertDatabaseCount('nilai', 2);
});

test('admin dapat memfilter daftar nilai berdasarkan kelompok dan nama santri', function () {
    $fixture = createNilaiFixture();
    $suffix = fake()->unique()->numerify('######');
    Nilai::query()->create([
        'santri_id' => $fixture['santri']->id,
        'mata_pelajaran_id' => $fixture['activeMapel']->id,
        'infrastruktur_sik_id' => $fixture['instructor']->id,
        'semester' => '1',
        'tahun_ajaran' => '2026',
        'nilai' => 91,
    ]);
    Nilai::query()->create([
        'santri_id' => $fixture['otherSantri']->id,
        'mata_pelajaran_id' => $fixture['activeMapel']->id,
        'infrastruktur_sik_id' => $fixture['otherInstructor']->id,
        'semester' => '1',
        'tahun_ajaran' => '2026',
        'nilai' => 77,
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('nilai.index', [
            'kelompok_id' => $fixture['group']->id,
            'q' => $fixture['santri']->nama,
        ]))
        ->assertOk()
        ->assertSee($fixture['santri']->nama)
        ->assertSee('91')
        ->assertDontSee($fixture['otherSantri']->nama)
        ->assertViewHas('nilai', fn ($paginator): bool => $paginator->getCollection()
            ->pluck('santri_id')
            ->unique()
            ->all() === [$fixture['santri']->id]);

    $this->get(route('nilai.index', ['periode_sik_id' => 999999]))
        ->assertSessionHasErrors('periode_sik_id');

    $periodeLain = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Filter Nilai '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);

    $this->get(route('nilai.index', [
        'periode_sik_id' => $periodeLain->id,
        'kelompok_id' => $fixture['group']->id,
    ]))
        ->assertOk()
        ->assertViewHas('nilai', fn ($paginator): bool => $paginator->isEmpty());
});

test('peran tanpa akses ditolak pada halaman dan penyimpanan nilai', function (UserRole $role) {
    $fixture = createNilaiFixture();
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('nilai.index'))
        ->assertForbidden();

    $this->post(route('nilai.store'), nilaiPayload($fixture))
        ->assertForbidden();
})->with([
    'santri' => [UserRole::Santri],
    'ka lab mutu' => [UserRole::KaLabMutu],
]);

test('admin dapat membaca nilai tetapi tidak dapat menulisnya', function () {
    $fixture = createNilaiFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('nilai.index'))
        ->assertOk()
        ->assertSee($fixture['legacyNilai']->mataPelajaran->nama_mapel);

    $this->post(route('nilai.store'), nilaiPayload($fixture))
        ->assertForbidden();

    $this->put(route('nilai.update', $fixture['legacyNilai']), nilaiPayload($fixture, [
        'nama_mapel' => $fixture['legacyMapel']->nama_mapel,
        'semester' => '2',
    ]))
        ->assertForbidden();
});

test('admin melihat tombol Rekap Nilai Santri pada halaman Data Santri', function () {
    $fixture = createNilaiFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('santris.index'))
        ->assertOk()
        ->assertSee('Rekap Nilai Santri')
        ->assertSee(route('nilai.index'));
});

test('halaman rekap nilai admin menampilkan ringkasan dan empty state', function () {
    $fixture = createNilaiFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('nilai.index'))
        ->assertOk()
        ->assertSee('Total Catatan Nilai')
        ->assertSee('Santri Dinilai')
        ->assertSee('Rata-rata Nilai')
        ->assertSee($fixture['santri']->nama);

    $this->get(route('nilai.index', ['q' => 'Tidak Ada Santri Ini']))
        ->assertOk()
        ->assertSee('Belum ada nilai yang sesuai dengan filter.');
});
