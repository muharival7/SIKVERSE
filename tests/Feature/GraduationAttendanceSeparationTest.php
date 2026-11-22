<?php

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\KelulusanSik;
use App\Models\Nilai;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\RiwayatKeputusanKelulusan;
use App\Models\Santri;
use App\Models\SertifikatSik;
use App\Models\User;
use App\Services\AttendanceRecapService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<int, string>  $statuses
 * @param  array<int, string>  $nonTerlaksanaStatuses
 * @return array<string, mixed>
 */
function createAttendanceSeparationFixture(array $statuses, array $nonTerlaksanaStatuses = []): array
{
    $suffix = fake()->unique()->numerify('######');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Pemisahan '.$suffix,
        'status' => 'Aktif',
    ]);
    $periode = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Pemisahan '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompok = Kelompok::query()->create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Pemisahan '.$suffix,
        'status' => 'Aktif',
    ]);
    $kegiatan = Kegiatan::query()->create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'nama_kegiatan' => 'Kegiatan Pemisahan '.$suffix,
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);
    $santri = Santri::query()->create([
        'nomor_induk' => 'PEM-'.$suffix,
        'nama' => 'Santri Pemisahan '.$suffix,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $anggota = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'status' => 'Aktif',
    ]);

    $pertemuanKe = 1;
    foreach ($statuses as $absensiStatus) {
        createAttendanceSeparationMeeting($anggota, $kegiatan, $pertemuanKe++, 'Terlaksana', $absensiStatus);
    }

    foreach ($nonTerlaksanaStatuses as $pertemuanStatus) {
        createAttendanceSeparationMeeting($anggota, $kegiatan, $pertemuanKe++, $pertemuanStatus, 'Hadir');
    }

    return compact('admin', 'instructorUser', 'instructor', 'periode', 'kelompok', 'kegiatan', 'santri', 'anggota');
}

/** @return array<string, mixed> */
function createAttendanceSeparationMeeting(
    AnggotaPeriodeSik $anggota,
    Kegiatan $kegiatan,
    int $pertemuanKe,
    string $pertemuanStatus,
    string $absensiStatus
): array {
    $pertemuan = Pertemuan::query()->create([
        'kegiatan_id' => $kegiatan->id,
        'pertemuan_ke' => $pertemuanKe,
        'tanggal' => sprintf('2026-01-%02d', $pertemuanKe),
        'status' => $pertemuanStatus,
    ]);

    AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $anggota->id,
        'pertemuan_id' => $pertemuan->id,
        'metode_absensi' => 'Manual',
        'status' => $absensiStatus,
    ]);

    return ['pertemuan' => $pertemuan];
}

/**
 * @param  array<string, mixed>  $fixture
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function separationExamPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'santri_id' => $fixture['santri']->id,
        'kegiatan_id' => $fixture['kegiatan']->id,
        'jenis_nilai' => 'Ujian',
        'nama_mapel' => 'Ujian SIK Pemisahan',
        'semester' => '1',
        'tahun_ajaran' => '2026/2027',
        'nilai' => 90,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $fixture
 * @return array<string, mixed>
 */
function separationCertificatePayload(array $fixture, string $nomor): array
{
    return [
        'kelulusan_sik_id' => $fixture['kelulusan']->id,
        'nomor_sertifikat' => $nomor,
        'file_sertifikat' => UploadedFile::fake()->createWithContent(
            'sertifikat.pdf',
            "%PDF-1.4\nSertifikat tahap C\n"
        ),
    ];
}

test('kehadiran 85 persen dari pertemuan terlaksana mengizinkan nilai ujian tersimpan', function () {
    $fixture = createAttendanceSeparationFixture(array_merge(array_fill(0, 17, 'Hadir'), ['Alpha']));

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), separationExamPayload($fixture))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('nilai', [
        'santri_id' => $fixture['santri']->id,
        'kegiatan_id' => $fixture['kegiatan']->id,
        'jenis_nilai' => 'Ujian',
        'nilai' => 90,
    ]);
});

test('kehadiran di bawah 85 persen menolak nilai ujian pada database', function () {
    $fixture = createAttendanceSeparationFixture(array_merge(array_fill(0, 15, 'Hadir'), ['Alpha', 'Alpha', 'Alpha']));

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), separationExamPayload($fixture))
        ->assertSessionHasErrors('jenis_nilai');

    $this->assertDatabaseMissing('nilai', [
        'santri_id' => $fixture['santri']->id,
        'kegiatan_id' => $fixture['kegiatan']->id,
        'jenis_nilai' => 'Ujian',
    ]);
});

test('pertemuan terjadwal dan dibatalkan tidak masuk penyebut syarat ujian', function () {
    $fixture = createAttendanceSeparationFixture(
        array_merge(array_fill(0, 8, 'Hadir'), array_fill(0, 2, 'Alpha')),
        ['Terjadwal', 'Dibatalkan', 'Terjadwal', 'Dibatalkan', 'Terjadwal']
    );

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), separationExamPayload($fixture))
        ->assertSessionHasErrors('jenis_nilai');

    $metric = app(AttendanceRecapService::class)->calculate(
        (int) $fixture['anggota']->id,
        (int) $fixture['kegiatan']->id
    );

    expect($metric['total_pertemuan'])->toBe(10)
        ->and($metric['jumlah_hadir'])->toBe(8);

    $this->assertDatabaseMissing('nilai', [
        'santri_id' => $fixture['santri']->id,
        'jenis_nilai' => 'Ujian',
    ]);
});

test('admin dapat memutuskan lulus walaupun kehadiran di bawah ambang ujian', function () {
    $fixture = createAttendanceSeparationFixture(array_merge(array_fill(0, 13, 'Hadir'), array_fill(0, 3, 'Alpha')));

    $this->actingAs($fixture['admin'])
        ->post(route('kelulusan-sik.store'), [
            'anggota_periode_sik_id' => $fixture['anggota']->id,
            'status' => 'Lulus',
            'keterangan' => 'Keputusan Admin tahap C.',
        ])
        ->assertRedirect(route('kelulusan-sik.index'))
        ->assertSessionHasNoErrors();

    $kelulusan = KelulusanSik::query()
        ->where('anggota_periode_sik_id', $fixture['anggota']->id)
        ->firstOrFail();

    $this->assertDatabaseHas('kelulusan_sik', [
        'id' => $kelulusan->id,
        'status' => 'Lulus',
        'keterangan' => 'Keputusan Admin tahap C.',
    ]);
    $this->assertDatabaseHas('riwayat_keputusan_kelulusan', [
        'kelulusan_sik_id' => $kelulusan->id,
        'actor_user_id' => $fixture['admin']->id,
        'status_sebelumnya' => null,
        'status_sesudahnya' => 'Lulus',
        'alasan' => 'Keputusan Admin tahap C.',
    ]);
});

test('admin dapat memutuskan tidak lulus walaupun kehadiran memenuhi ambang ujian', function () {
    $fixture = createAttendanceSeparationFixture(array_merge(array_fill(0, 17, 'Hadir'), ['Alpha']));

    $this->actingAs($fixture['admin'])
        ->post(route('kelulusan-sik.store'), [
            'anggota_periode_sik_id' => $fixture['anggota']->id,
            'status' => 'Tidak Lulus',
            'keterangan' => 'Keputusan Admin tetap berlaku.',
        ])
        ->assertRedirect(route('kelulusan-sik.index'))
        ->assertSessionHasNoErrors();

    $kelulusan = KelulusanSik::query()
        ->where('anggota_periode_sik_id', $fixture['anggota']->id)
        ->firstOrFail();

    $this->assertDatabaseHas('kelulusan_sik', [
        'id' => $kelulusan->id,
        'status' => 'Tidak Lulus',
    ]);
    $this->assertDatabaseHas('riwayat_keputusan_kelulusan', [
        'kelulusan_sik_id' => $kelulusan->id,
        'status_sesudahnya' => 'Tidak Lulus',
        'alasan' => 'Keputusan Admin tetap berlaku.',
    ]);
});

test('riwayat keputusan tercatat dan keputusan terbaru menjadi acuan sertifikat', function () {
    Storage::fake('local');
    $fixture = createAttendanceSeparationFixture(array_merge(array_fill(0, 13, 'Hadir'), array_fill(0, 3, 'Alpha')));

    $this->actingAs($fixture['admin'])
        ->post(route('kelulusan-sik.store'), [
            'anggota_periode_sik_id' => $fixture['anggota']->id,
            'status' => 'Lulus',
        ])
        ->assertRedirect(route('kelulusan-sik.index'));

    $kelulusan = KelulusanSik::query()
        ->where('anggota_periode_sik_id', $fixture['anggota']->id)
        ->firstOrFail();

    $this->actingAs($fixture['admin'])
        ->put(route('kelulusan-sik.update', $kelulusan), [
            'anggota_periode_sik_id' => $fixture['anggota']->id,
            'status' => 'Tidak Lulus',
        ])
        ->assertRedirect(route('kelulusan-sik.index'));

    $this->actingAs($fixture['admin'])
        ->put(route('kelulusan-sik.update', $kelulusan), [
            'anggota_periode_sik_id' => $fixture['anggota']->id,
            'status' => 'Lulus',
            'keterangan' => 'Keputusan terakhir Admin.',
        ])
        ->assertRedirect(route('kelulusan-sik.index'));

    $riwayat = RiwayatKeputusanKelulusan::query()
        ->where('kelulusan_sik_id', $kelulusan->id)
        ->orderBy('id')
        ->get();

    expect($riwayat)->toHaveCount(3)
        ->and($riwayat->pluck('status_sesudahnya')->all())->toBe(['Lulus', 'Tidak Lulus', 'Lulus'])
        ->and($riwayat->last()->alasan)->toBe('Keputusan terakhir Admin.');

    $fixture['kelulusan'] = $kelulusan;
    $this->post(route('sertifikat-sik.store'), separationCertificatePayload($fixture, 'CER-PEM-001'))
        ->assertRedirect(route('sertifikat-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('sertifikat_sik', [
        'kelulusan_sik_id' => $kelulusan->id,
        'nomor_sertifikat' => 'CER-PEM-001',
    ]);
    $this->assertDatabaseHas('kelulusan_sik', [
        'id' => $kelulusan->id,
        'status' => 'Lulus',
    ]);
});

test('sertifikat baru dapat diterbitkan untuk keputusan lulus di bawah ambang kehadiran', function () {
    Storage::fake('local');
    $fixture = createAttendanceSeparationFixture(array_merge(array_fill(0, 13, 'Hadir'), array_fill(0, 3, 'Alpha')));
    $kelulusan = KelulusanSik::query()->create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'status' => 'Lulus',
    ]);
    RiwayatKeputusanKelulusan::query()->create([
        'kelulusan_sik_id' => $kelulusan->id,
        'actor_user_id' => $fixture['admin']->id,
        'status_sesudahnya' => 'Lulus',
    ]);
    $fixture['kelulusan'] = $kelulusan;

    $this->actingAs($fixture['admin'])
        ->post(route('sertifikat-sik.store'), separationCertificatePayload($fixture, 'CER-PEM-002'))
        ->assertRedirect(route('sertifikat-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('sertifikat_sik', [
        'kelulusan_sik_id' => $kelulusan->id,
        'nomor_sertifikat' => 'CER-PEM-002',
    ]);
});

test('sertifikat baru ditolak untuk keputusan tidak lulus walaupun kehadiran memenuhi ambang', function () {
    Storage::fake('local');
    $fixture = createAttendanceSeparationFixture(array_merge(array_fill(0, 17, 'Hadir'), ['Alpha']));
    $kelulusan = KelulusanSik::query()->create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'status' => 'Lulus',
    ]);
    RiwayatKeputusanKelulusan::query()->create([
        'kelulusan_sik_id' => $kelulusan->id,
        'actor_user_id' => $fixture['admin']->id,
        'status_sesudahnya' => 'Lulus',
    ]);
    RiwayatKeputusanKelulusan::query()->create([
        'kelulusan_sik_id' => $kelulusan->id,
        'actor_user_id' => $fixture['admin']->id,
        'status_sesudahnya' => 'Tidak Lulus',
    ]);
    $fixture['kelulusan'] = $kelulusan;

    $this->actingAs($fixture['admin'])
        ->post(route('sertifikat-sik.store'), separationCertificatePayload($fixture, 'CER-PEM-003'))
        ->assertSessionHasErrors('kelulusan_sik_id');

    $this->assertDatabaseMissing('sertifikat_sik', [
        'kelulusan_sik_id' => $kelulusan->id,
    ]);
    $this->assertDatabaseHas('riwayat_keputusan_kelulusan', [
        'id' => RiwayatKeputusanKelulusan::query()
            ->where('kelulusan_sik_id', $kelulusan->id)
            ->latest()
            ->orderByDesc('id')
            ->value('id'),
        'status_sesudahnya' => 'Tidak Lulus',
    ]);
});

test('perubahan kehadiran tidak mengoreksi data kelulusan dan sertifikat historis', function () {
    Storage::fake('local');
    $fixture = createAttendanceSeparationFixture(array_merge(array_fill(0, 13, 'Hadir'), array_fill(0, 3, 'Alpha')));
    $kelulusan = KelulusanSik::query()->create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'status' => 'Lulus',
        'tanggal_kelulusan' => '2026-11-01',
        'keterangan' => 'Catatan historis tidak diubah.',
    ]);
    Storage::disk('local')->put('sertifikat-sik/pem-historis.pdf', "%PDF-1.4\nArsip\n");
    $sertifikat = SertifikatSik::query()->create([
        'kelulusan_sik_id' => $kelulusan->id,
        'nomor_sertifikat' => 'CER-PEM-HIS',
        'file_sertifikat' => 'sertifikat-sik/pem-historis.pdf',
        'tanggal_terbit' => '2026-11-02',
    ]);
    $riwayatSebelum = RiwayatKeputusanKelulusan::query()
        ->where('kelulusan_sik_id', $kelulusan->id)
        ->count();

    foreach (range(1, 4) as $pertemuanKe) {
        createAttendanceSeparationMeeting($fixture['anggota'], $fixture['kegiatan'], 20 + $pertemuanKe, 'Terlaksana', 'Hadir');
    }

    $this->assertDatabaseHas('kelulusan_sik', [
        'id' => $kelulusan->id,
        'status' => 'Lulus',
        'tanggal_kelulusan' => '2026-11-01 00:00:00',
        'keterangan' => 'Catatan historis tidak diubah.',
    ]);
    $this->assertDatabaseHas('sertifikat_sik', [
        'id' => $sertifikat->id,
        'nomor_sertifikat' => 'CER-PEM-HIS',
        'file_sertifikat' => 'sertifikat-sik/pem-historis.pdf',
    ]);
    expect(RiwayatKeputusanKelulusan::query()
        ->where('kelulusan_sik_id', $kelulusan->id)
        ->count())->toBe($riwayatSebelum)
        ->and(app(AttendanceRecapService::class)->graduationEvaluation($kelulusan->fresh())['code'])
        ->toBe(AttendanceRecapService::GRADUATION_PASSED);
});

test('nilai harian tidak menentukan kelulusan dan nilai ujian tetap unik per kegiatan', function () {
    $fixture = createAttendanceSeparationFixture(array_merge(array_fill(0, 17, 'Hadir'), ['Alpha']));

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), separationExamPayload($fixture, [
            'jenis_nilai' => 'Harian',
            'nama_mapel' => 'Latihan Harian',
            'nilai' => 10,
        ]))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHasNoErrors();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), separationExamPayload($fixture, [
            'nama_mapel' => 'Ujian SIK Pertama',
            'nilai' => 20,
        ]))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHasNoErrors();

    $this->actingAs($fixture['instructorUser'])
        ->post(route('nilai.store'), separationExamPayload($fixture, [
            'nama_mapel' => 'Ujian SIK Kedua',
            'nilai' => 30,
        ]))
        ->assertSessionHasErrors('jenis_nilai');

    $this->assertDatabaseHas('nilai', [
        'santri_id' => $fixture['santri']->id,
        'jenis_nilai' => 'Harian',
        'nilai' => 10,
    ]);
    expect(Nilai::query()
        ->where('santri_id', $fixture['santri']->id)
        ->where('kegiatan_id', $fixture['kegiatan']->id)
        ->where('jenis_nilai', 'Ujian')
        ->count())->toBe(1);

    $this->assertDatabaseMissing('kelulusan_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
    ]);
});
