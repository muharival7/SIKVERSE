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
function createExamEligibilityFixture(int $jumlahHadir): array
{
    $suffix = fake()->unique()->numerify('######');
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Ujian '.$suffix,
        'status' => 'Aktif',
    ]);
    $periode = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Ujian '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompok = Kelompok::query()->create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Ujian '.$suffix,
        'status' => 'Aktif',
    ]);
    $kegiatan = Kegiatan::query()->create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'nama_kegiatan' => 'Kegiatan Ujian '.$suffix,
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);
    $santri = Santri::query()->create([
        'nomor_induk' => 'UJI-'.$suffix,
        'nama' => 'Santri Ujian '.$suffix,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $anggota = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'status' => 'Aktif',
    ]);

    for ($pertemuanKe = 1; $pertemuanKe <= 16; $pertemuanKe++) {
        $pertemuan = Pertemuan::query()->create([
            'kegiatan_id' => $kegiatan->id,
            'pertemuan_ke' => $pertemuanKe,
            'tanggal' => now()->startOfYear()->addDays($pertemuanKe - 1)->toDateString(),
            'status' => 'Terlaksana',
        ]);

        AbsensiSik::query()->create([
            'anggota_periode_sik_id' => $anggota->id,
            'pertemuan_id' => $pertemuan->id,
            'metode_absensi' => 'Manual',
            'status' => $pertemuanKe <= $jumlahHadir ? 'Hadir' : 'Alpha',
        ]);
    }

    return compact('instructorUser', 'instructor', 'periode', 'kelompok', 'kegiatan', 'santri', 'anggota');
}

/** @param array<string, mixed> $overrides */
function examEligibilityPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'santri_id' => $fixture['santri']->id,
        'kegiatan_id' => $fixture['kegiatan']->id,
        'jenis_nilai' => 'Ujian',
        'nama_mapel' => 'Ujian SIK',
        'semester' => '1',
        'tahun_ajaran' => '2026/2027',
        'nilai' => 90,
    ], $overrides);
}

test('nilai ujian diterima bila kehadiran memenuhi ambang 85 persen', function () {
    $fixture = createExamEligibilityFixture(14);

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), examEligibilityPayload($fixture))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('nilai', [
        'santri_id' => $fixture['santri']->id,
        'jenis_nilai' => 'Ujian',
        'nilai' => 90,
    ]);
});

test('nilai ujian ditolak bila kehadiran di bawah ambang 85 persen', function () {
    $fixture = createExamEligibilityFixture(13);

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), examEligibilityPayload($fixture, [
            'nama_mapel' => 'Ujian SIK Tidak Eligible',
        ]))
        ->assertSessionHasErrors('jenis_nilai');

    $this->assertDatabaseMissing('nilai', [
        'santri_id' => $fixture['santri']->id,
        'jenis_nilai' => 'Ujian',
    ]);
});

test('nilai harian tetap dapat dicatat tanpa syarat kehadiran ujian', function () {
    $fixture = createExamEligibilityFixture(13);

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), examEligibilityPayload($fixture, [
            'jenis_nilai' => 'Harian',
            'nama_mapel' => 'Latihan Harian',
        ]))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('nilai', [
        'santri_id' => $fixture['santri']->id,
        'jenis_nilai' => 'Harian',
    ]);
});

test('mengubah nilai harian menjadi ujian ditolak untuk santri tidak eligible', function () {
    $fixture = createExamEligibilityFixture(13);
    $nilai = Nilai::query()->create([
        'santri_id' => $fixture['santri']->id,
        'mata_pelajaran_id' => createExamSubject($fixture),
        'infrastruktur_sik_id' => $fixture['instructor']->id,
        'semester' => '1',
        'tahun_ajaran' => '2026/2027',
        'nilai' => 70,
        'jenis_nilai' => 'Harian',
    ]);

    $this->actingAs($fixture['instructorUser'])
        ->put(route('nilai.update', $nilai), examEligibilityPayload($fixture, [
            'jenis_nilai' => 'Ujian',
            'nama_mapel' => 'Latihan Harian',
            'nilai' => 95,
        ]))
        ->assertSessionHasErrors('jenis_nilai');

    expect($nilai->fresh()->jenis_nilai)->toBe('Harian');
});

test('halaman nilai menampilkan badge kelayakan ujian', function () {
    $fixture = createExamEligibilityFixture(14);

    $this->actingAs($fixture['instructorUser'])
        ->get(route('nilai.index'))
        ->assertOk()
        ->assertSee('Kelayakan ujian: Memenuhi');
});

/** @param array<string, mixed> $fixture */
function createExamSubject(array $fixture): int
{
    return MataPelajaran::query()->create([
        'kode_mapel' => 'UE-'.fake()->unique()->numerify('######'),
        'nama_mapel' => 'Latihan Harian',
        'sks' => 1,
        'semester' => 1,
        'status' => true,
    ])->id;
}
