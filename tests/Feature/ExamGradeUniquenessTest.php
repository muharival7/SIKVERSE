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
use Illuminate\Database\QueryException;

/** @return array<string, mixed> */
function createExamGradeUniquenessFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Ujian Unik '.$suffix,
        'status' => 'Aktif',
    ]);
    $periode = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Ujian Unik '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompok = Kelompok::query()->create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Ujian Unik '.$suffix,
        'status' => 'Aktif',
    ]);
    $kegiatan = Kegiatan::query()->create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'nama_kegiatan' => 'Kegiatan Ujian A '.$suffix,
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);
    $santri = Santri::query()->create([
        'nomor_induk' => 'UJX-'.$suffix,
        'nama' => 'Santri Ujian Unik '.$suffix,
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
            'status' => $pertemuanKe <= 14 ? 'Hadir' : 'Alpha',
        ]);
    }

    return compact('suffix', 'instructorUser', 'instructor', 'periode', 'kelompok', 'kegiatan', 'santri', 'anggota');
}

/** @param array<string, mixed> $fixture */
function examGradeUniquenessPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'santri_id' => $fixture['santri']->id,
        'kegiatan_id' => $fixture['kegiatan']->id,
        'jenis_nilai' => 'Ujian',
        'nama_mapel' => 'Ujian SIK '.$fixture['suffix'],
        'semester' => '1',
        'tahun_ajaran' => '2026/2027',
        'nilai' => 90,
    ], $overrides);
}

test('santri dapat memperoleh satu nilai ujian akhir pada suatu kegiatan SIK', function () {
    $fixture = createExamGradeUniquenessFixture();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), examGradeUniquenessPayload($fixture))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('nilai', [
        'santri_id' => $fixture['santri']->id,
        'kegiatan_id' => $fixture['kegiatan']->id,
        'jenis_nilai' => 'Ujian',
        'nilai' => 90,
    ]);
});

test('nilai ujian akhir tanpa kegiatan SIK ditolak dengan pesan yang jelas', function () {
    $fixture = createExamGradeUniquenessFixture();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), examGradeUniquenessPayload($fixture, [
            'kegiatan_id' => '',
        ]))
        ->assertSessionHasErrors('kegiatan_id');

    $this->assertDatabaseMissing('nilai', [
        'santri_id' => $fixture['santri']->id,
        'jenis_nilai' => 'Ujian',
    ]);
});

test('santri tidak dapat memperoleh nilai ujian akhir kedua pada kegiatan SIK yang sama', function () {
    $fixture = createExamGradeUniquenessFixture();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), examGradeUniquenessPayload($fixture))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHasNoErrors();

    $this->post(route('nilai.store'), examGradeUniquenessPayload($fixture, [
        'nama_mapel' => 'Ujian SIK Ulang '.$fixture['suffix'],
        'nilai' => 95,
    ]))
        ->assertSessionHasErrors('jenis_nilai');

    expect(Nilai::query()
        ->where('santri_id', $fixture['santri']->id)
        ->where('kegiatan_id', $fixture['kegiatan']->id)
        ->where('jenis_nilai', 'Ujian')
        ->count())->toBe(1);
});

test('santri dapat memperoleh nilai ujian akhir pada kegiatan SIK berbeda', function () {
    $fixture = createExamGradeUniquenessFixture();
    $kegiatanLain = Kegiatan::query()->create([
        'periode_sik_id' => $fixture['periode']->id,
        'kelompok_id' => $fixture['kelompok']->id,
        'nama_kegiatan' => 'Kegiatan Ujian B '.$fixture['suffix'],
        'jenis_kegiatan' => 'Aula',
        'status' => 'Aktif',
    ]);

    $payload = examGradeUniquenessPayload($fixture);

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), $payload)
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHasNoErrors();

    $this->post(route('nilai.store'), array_merge($payload, [
        'kegiatan_id' => $kegiatanLain->id,
    ]))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHasNoErrors();

    expect(Nilai::query()
        ->where('santri_id', $fixture['santri']->id)
        ->where('jenis_nilai', 'Ujian')
        ->pluck('kegiatan_id')
        ->sort()
        ->values()
        ->all())->toBe([$fixture['kegiatan']->id, $kegiatanLain->id]);
});

test('nilai harian tetap dapat dicatat berulang sesuai aturan yang berlaku', function () {
    $fixture = createExamGradeUniquenessFixture();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), examGradeUniquenessPayload($fixture, [
            'jenis_nilai' => 'Harian',
            'nama_mapel' => 'Latihan Harian A '.$fixture['suffix'],
        ]))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHasNoErrors();

    $this->post(route('nilai.store'), examGradeUniquenessPayload($fixture, [
        'jenis_nilai' => 'Harian',
        'nama_mapel' => 'Latihan Harian B '.$fixture['suffix'],
    ]))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHasNoErrors();

    expect(Nilai::query()
        ->where('santri_id', $fixture['santri']->id)
        ->where('jenis_nilai', 'Harian')
        ->count())->toBe(2);

    $this->assertDatabaseHas('nilai', [
        'santri_id' => $fixture['santri']->id,
        'kegiatan_id' => $fixture['kegiatan']->id,
        'jenis_nilai' => 'Harian',
    ]);
});

test('constraint database mencegah duplikasi nilai ujian akhir pada permintaan bersamaan', function () {
    $fixture = createExamGradeUniquenessFixture();
    $atribut = [
        'santri_id' => $fixture['santri']->id,
        'kegiatan_id' => $fixture['kegiatan']->id,
        'jenis_nilai' => 'Ujian',
        'infrastruktur_sik_id' => $fixture['instructor']->id,
        'semester' => '1',
        'tahun_ajaran' => '2026/2027',
        'nilai' => 90,
    ];

    // Pastikan foreign key ke mata pelajaran tersedia tanpa mengubah data historis.
    $mataPelajaran = MataPelajaran::query()->create([
        'kode_mapel' => 'UX-'.$fixture['suffix'],
        'nama_mapel' => 'Ujian Constraint '.$fixture['suffix'],
        'sks' => 1,
        'semester' => 1,
        'status' => true,
    ]);
    $atribut['mata_pelajaran_id'] = $mataPelajaran->id;

    Nilai::query()->create($atribut);

    $exception = null;
    try {
        Nilai::query()->create(array_merge($atribut, ['nilai' => 95]));
    } catch (QueryException $queryException) {
        $exception = $queryException;
    }

    expect($exception)->toBeInstanceOf(QueryException::class)
        ->and(str_contains($exception->getMessage(), 'ujian_kegiatan_id')
            || str_contains($exception->getMessage(), 'nilai_ujian_santri_kegiatan_unique'))->toBeTrue();

    expect(Nilai::query()
        ->where('santri_id', $fixture['santri']->id)
        ->where('kegiatan_id', $fixture['kegiatan']->id)
        ->where('jenis_nilai', 'Ujian')
        ->count())->toBe(1);
});
