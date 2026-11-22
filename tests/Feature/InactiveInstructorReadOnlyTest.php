<?php

use App\Enums\UserRole;
use App\Http\Middleware\EnsureInstructorReadOnlyOnWrite;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\BuktiKetidakhadiran;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\KelulusanSik;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\RiwayatAbsensi;
use App\Models\RiwayatKeputusanKelulusan;
use App\Models\Santri;
use App\Models\SertifikatSik;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/** @return array<string, mixed> */
function createReadOnlyInstructorFixture(string $status = 'Tidak Aktif'): array
{
    $suffix = fake()->unique()->numerify('######');
    $user = User::factory()->create([
        'role' => UserRole::InfrastrukturSik,
        'must_change_password' => false,
    ]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $user->id,
        'nama' => 'Dosen Akses '.$suffix,
        'status' => $status,
    ]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $santriUser = User::factory()->create(['role' => UserRole::Santri]);
    $santri = Santri::query()->create([
        'user_id' => $santriUser->id,
        'nomor_induk' => 'RO-'.$suffix,
        'nama' => 'Santri Akses '.$suffix,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $periode = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Akses '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompok = Kelompok::query()->create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Akses '.$suffix,
        'status' => 'Aktif',
    ]);
    $anggota = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'status' => 'Aktif',
    ]);
    $kegiatan = Kegiatan::query()->create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'nama_kegiatan' => 'Kegiatan Akses '.$suffix,
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);
    $pertemuan = Pertemuan::query()->create([
        'kegiatan_id' => $kegiatan->id,
        'pertemuan_ke' => 1,
        'tanggal' => '2026-01-01',
        'status' => 'Terjadwal',
    ]);
    $absensi = AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $anggota->id,
        'pertemuan_id' => $pertemuan->id,
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
        'dicatat_oleh' => $instructor->id,
    ]);

    return compact(
        'suffix',
        'user',
        'instructor',
        'admin',
        'santriUser',
        'santri',
        'periode',
        'kelompok',
        'anggota',
        'kegiatan',
        'pertemuan',
        'absensi'
    );
}

/** @param array<string, mixed> $fixture
 * @return array<string, mixed>
 */
function readOnlyNilaiPayload(array $fixture): array
{
    return [
        'santri_id' => $fixture['santri']->id,
        'jenis_nilai' => 'Harian',
        'nama_mapel' => 'Nilai Akses '.$fixture['suffix'],
        'semester' => '1',
        'tahun_ajaran' => '2026/2027',
        'nilai' => 85,
    ];
}

test('dosen tidak aktif tetap dapat membuka halaman yang menjadi hak aksesnya', function () {
    $fixture = createReadOnlyInstructorFixture();

    $this->actingAs($fixture['user'])
        ->get(route('dashboard.infrastruktur_sik'))
        ->assertOk();

    $this->actingAs($fixture['user'])
        ->get(route('nilai.index'))
        ->assertOk();

    $this->actingAs($fixture['user'])
        ->get(route('absensi-sik.index'))
        ->assertOk();

    $this->actingAs($fixture['user'])
        ->get(route('rekap-kehadiran-sik.index'))
        ->assertOk();

    $this->actingAs($fixture['user'])
        ->get(route('instruktur.kelompok.index'))
        ->assertOk();

    $this->actingAs($fixture['user'])
        ->get(route('instruktur.anggota.index'))
        ->assertOk();

    $this->actingAs($fixture['user'])
        ->get(route('profile.edit'))
        ->assertOk();

    $this->actingAs($fixture['user'])
        ->get(route('password.change'))
        ->assertOk();
});

test('dosen tidak aktif ditolak pada seluruh endpoint POST yang diuji', function () {
    $fixture = createReadOnlyInstructorFixture();
    $userCount = User::query()->count();
    $password = $fixture['user']->fresh()->password;

    $passwordPayload = [
        'current_password' => 'password',
        'password' => 'DosenBaru123!',
        'password_confirmation' => 'DosenBaru123!',
    ];

    $posts = [
        ['absensi-sik.store', [], ['anggota_periode_sik_id' => $fixture['anggota']->id, 'pertemuan_id' => $fixture['pertemuan']->id, 'status' => 'Alpha']],
        ['nilai.store', [], readOnlyNilaiPayload($fixture)],
        ['kelulusan-sik.store', [], ['anggota_periode_sik_id' => $fixture['anggota']->id, 'status' => 'Tidak Lulus']],
        ['users.store', [], []],
        ['santris.import.preview', [], []],
        ['rekap-absensi-dosen.store', [], []],
        ['rabu-mengaji.store', [], []],
        ['absensi-sik.pertemuan.selesai', ['pertemuan' => $fixture['pertemuan']->id], []],
        ['absensi-sik.pertemuan.ubah-tanggal', ['pertemuan' => $fixture['pertemuan']->id], ['tanggal_baru' => '2026-02-02', 'konfirmasi' => '1']],
        ['change-password.update', [], $passwordPayload],
        ['password.custom-update', [], $passwordPayload],
        ['verification.send', [], []],
    ];

    foreach ($posts as [$routeName, $parameters, $payload]) {
        $this->actingAs($fixture['user'])
            ->post(route($routeName, $parameters), $payload)
            ->assertForbidden()
            ->assertSee(EnsureInstructorReadOnlyOnWrite::MESSAGE);
    }

    $this->actingAs($fixture['user'])
        ->post('/confirm-password', ['password' => 'password'])
        ->assertForbidden()
        ->assertSee(EnsureInstructorReadOnlyOnWrite::MESSAGE);

    $userWithoutProfile = User::factory()->create([
        'role' => UserRole::InfrastrukturSik,
        'must_change_password' => false,
    ]);

    expect(InfrastrukturSik::query()->where('user_id', $userWithoutProfile->id)->exists())->toBeFalse();

    $this->actingAs($userWithoutProfile)
        ->post(route('nilai.store'), readOnlyNilaiPayload($fixture))
        ->assertForbidden()
        ->assertSee(EnsureInstructorReadOnlyOnWrite::MESSAGE);

    $this->actingAs($userWithoutProfile)
        ->patch(route('profile.update'), [
            'name' => 'Dosen Tanpa Profil Diubah',
            'email' => $userWithoutProfile->email,
        ])
        ->assertForbidden()
        ->assertSee(EnsureInstructorReadOnlyOnWrite::MESSAGE);

    expect(User::query()->count())->toBe($userCount + 1)
        ->and(AbsensiSik::query()->count())->toBe(1)
        ->and(Nilai::query()->count())->toBe(0)
        ->and(KelulusanSik::query()->count())->toBe(0)
        ->and(RiwayatKeputusanKelulusan::query()->count())->toBe(0)
        ->and(RiwayatAbsensi::query()->count())->toBe(0)
        ->and($fixture['user']->fresh()->password)->toBe($password)
        ->and($fixture['user']->fresh()->name)->toBe($fixture['user']->name)
        ->and($fixture['pertemuan']->fresh()->status)->toBe('Terjadwal');
});

test('dosen tidak aktif ditolak pada endpoint PUT dan PATCH', function () {
    $fixture = createReadOnlyInstructorFixture();
    $nilai = Nilai::query()->create([
        'santri_id' => $fixture['santri']->id,
        'mata_pelajaran_id' => MataPelajaran::query()->create([
            'kode_mapel' => 'RO-'.$fixture['suffix'],
            'nama_mapel' => 'Mapel Akses '.$fixture['suffix'],
            'sks' => 1,
            'semester' => 1,
            'status' => true,
        ])->id,
        'infrastruktur_sik_id' => $fixture['instructor']->id,
        'semester' => '1',
        'tahun_ajaran' => '2026/2027',
        'nilai' => 80,
    ]);
    $password = $fixture['user']->fresh()->password;
    $name = $fixture['user']->name;

    $this->actingAs($fixture['user'])
        ->put(route('absensi-sik.update', $fixture['absensi']), ['status' => 'Alpha'])
        ->assertForbidden();

    $this->actingAs($fixture['user'])
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'DosenBaru123!',
            'password_confirmation' => 'DosenBaru123!',
        ])
        ->assertForbidden();

    $this->actingAs($fixture['user'])
        ->patch(route('profile.update'), [
            'name' => 'Nama Diubah',
            'email' => $fixture['user']->email,
        ])
        ->assertForbidden();

    $this->actingAs($fixture['user'])
        ->patch(route('nilai.update', $nilai), ['nilai' => 99, 'nama_mapel' => 'Ubah Nilai'])
        ->assertForbidden();

    $this->actingAs($fixture['user'])
        ->patch(route('santris.update', $fixture['santri']), ['nama' => 'Santri Diubah'])
        ->assertForbidden();

    $this->assertDatabaseHas('nilai', ['id' => $nilai->id, 'nilai' => 80]);

    expect($fixture['absensi']->fresh()->status)->toBe('Hadir')
        ->and($fixture['user']->fresh()->name)->toBe($name)
        ->and($fixture['user']->fresh()->password)->toBe($password)
        ->and($fixture['santri']->fresh()->nama)->toBe('Santri Akses '.$fixture['suffix']);
});

test('dosen tidak aktif ditolak pada endpoint DELETE', function () {
    $fixture = createReadOnlyInstructorFixture();
    $userCount = User::query()->count();

    $this->actingAs($fixture['user'])
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    $this->actingAs($fixture['user'])
        ->delete(route('pertemuan.destroy', $fixture['pertemuan']))
        ->assertForbidden();

    $this->actingAs($fixture['user'])
        ->delete(route('santris.destroy', $fixture['santri']))
        ->assertForbidden();

    $this->actingAs($fixture['user'])
        ->delete(route('users.destroy', $fixture['admin']))
        ->assertForbidden();

    expect(User::query()->count())->toBe($userCount)
        ->and(Pertemuan::query()->whereKey($fixture['pertemuan']->id)->exists())->toBeTrue()
        ->and(Santri::query()->whereKey($fixture['santri']->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($fixture['admin']->id)->exists())->toBeTrue();
});

test('logout dosen tidak aktif diizinkan dan mengakhiri sesi', function () {
    $fixture = createReadOnlyInstructorFixture();
    $pertemuanStatus = $fixture['pertemuan']->fresh()->status;
    $password = $fixture['user']->fresh()->password;

    $this->actingAs($fixture['user'])
        ->post(route('logout'))
        ->assertRedirect(route('login'))
        ->assertSessionHasNoErrors();

    $this->assertGuest();

    expect(User::query()->whereKey($fixture['user']->id)->exists())->toBeTrue()
        ->and($fixture['user']->fresh()->password)->toBe($password)
        ->and(Nilai::query()->count())->toBe(0)
        ->and(AbsensiSik::query()->count())->toBe(1)
        ->and($fixture['pertemuan']->fresh()->status)->toBe($pertemuanStatus);
});

test('permintaan AJAX dan API tulis dari dosen tidak aktif mendapat respons JSON 403', function () {
    $fixture = createReadOnlyInstructorFixture();

    $this->actingAs($fixture['user'])
        ->postJson(route('nilai.store'), readOnlyNilaiPayload($fixture))
        ->assertForbidden()
        ->assertJsonPath('message', EnsureInstructorReadOnlyOnWrite::MESSAGE);

    $this->actingAs($fixture['user'])
        ->patchJson(route('profile.update'), [
            'name' => 'Nama AJAX',
            'email' => $fixture['user']->email,
        ])
        ->assertForbidden()
        ->assertJsonPath('message', EnsureInstructorReadOnlyOnWrite::MESSAGE);

    expect(Nilai::query()->count())->toBe(0)
        ->and($fixture['user']->fresh()->name)->toBe($fixture['user']->name);
});

test('upload dan berkas tidak berubah setelah endpoint tulis dosen tidak aktif ditolak', function () {
    Storage::fake('local');
    $fixture = createReadOnlyInstructorFixture();
    $fixture['absensi']->update(['status' => 'Izin']);

    $this->actingAs($fixture['user'])
        ->post(route('sertifikat-sik.store'), [
            'kelulusan_sik_id' => 999999,
            'nomor_sertifikat' => 'RO-FILE-'.$fixture['suffix'],
            'file_sertifikat' => UploadedFile::fake()->createWithContent(
                'sertifikat.pdf',
                "%PDF-1.4\nSertifikat ditolak\n"
            ),
        ])
        ->assertForbidden();

    $this->actingAs($fixture['user'])
        ->post(route('absensi-saya.bukti.store', $fixture['absensi']), [
            'jenis_bukti' => 'Izin',
            'file_bukti' => UploadedFile::fake()->createWithContent(
                'izin.pdf',
                "%PDF-1.4\nBukti ditolak\n"
            ),
        ])
        ->assertForbidden();

    expect(Storage::disk('local')->allFiles())->toBe([])
        ->and(SertifikatSik::query()->count())->toBe(0)
        ->and(BuktiKetidakhadiran::query()->count())->toBe(0);
});

test('dosen aktif masih dapat memakai endpoint tulis yang menjadi hak aksesnya', function () {
    $fixture = createReadOnlyInstructorFixture('Aktif');
    $pertemuanBaru = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['kegiatan']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-01-02',
        'status' => 'Terjadwal',
    ]);

    $this->actingAs($fixture['user'])
        ->post(route('absensi-sik.store'), [
            'anggota_periode_sik_id' => $fixture['anggota']->id,
            'pertemuan_id' => $pertemuanBaru->id,
            'status' => 'Alpha',
        ])
        ->assertRedirect(route('absensi-sik.index'))
        ->assertSessionHasNoErrors();

    $this->actingAs($fixture['user'])
        ->post(route('nilai.store'), readOnlyNilaiPayload($fixture))
        ->assertRedirect(route('nilai.index'))
        ->assertSessionHasNoErrors();

    $this->actingAs($fixture['user'])
        ->post(route('absensi-sik.pertemuan.selesai', $pertemuanBaru))
        ->assertRedirect(route('absensi-sik.index'));

    $this->actingAs($fixture['user'])
        ->patch(route('profile.update'), [
            'name' => 'Dosen Aktif Diubah',
            'email' => $fixture['user']->email,
        ])
        ->assertRedirect(route('profile.edit'));

    $this->actingAs($fixture['user'])
        ->post(route('change-password.update'), [
            'current_password' => 'password',
            'password' => 'DosenAktif123!',
            'password_confirmation' => 'DosenAktif123!',
        ])
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $pertemuanBaru->id,
        'status' => 'Alpha',
    ]);
    $this->assertDatabaseHas('nilai', [
        'santri_id' => $fixture['santri']->id,
        'nilai' => 85,
    ]);

    expect($pertemuanBaru->fresh()->status)->toBe('Terlaksana')
        ->and($fixture['user']->fresh()->name)->toBe('Dosen Aktif Diubah')
        ->and(Hash::check('DosenAktif123!', $fixture['user']->fresh()->password))->toBeTrue();
});

test('admin santri dan ka lab mutu tidak terkena pembatasan dosen tidak aktif', function () {
    Storage::fake('local');
    $fixture = createReadOnlyInstructorFixture('Aktif');

    $this->actingAs($fixture['admin'])
        ->post(route('periode-sik.store'), [
            'nama_periode' => 'Periode Admin '.$fixture['suffix'],
            'tahun' => 2027,
            'tanggal_mulai' => '2027-01-01',
            'tanggal_selesai' => '2027-12-31',
            'status' => 'Aktif',
        ])
        ->assertRedirect(route('periode-sik.index'));

    $this->assertDatabaseHas('periode_sik', [
        'nama_periode' => 'Periode Admin '.$fixture['suffix'],
    ]);

    $fixture['absensi']->update(['status' => 'Izin']);

    $this->actingAs($fixture['santriUser'])
        ->post(route('absensi-saya.bukti.store', $fixture['absensi']), [
            'jenis_bukti' => 'Izin',
            'file_bukti' => UploadedFile::fake()->createWithContent(
                'izin-santri.pdf',
                "%PDF-1.4\nBukti santri\n"
            ),
        ])
        ->assertSessionHasNoErrors();

    $bukti = BuktiKetidakhadiran::query()->where('absensi_sik_id', $fixture['absensi']->id)->firstOrFail();
    Storage::disk('local')->assertExists($bukti->file_bukti);

    $kaLab = User::factory()->create(['role' => UserRole::KaLabMutu]);

    $this->actingAs($kaLab)
        ->get(route('laporan-sik'))
        ->assertOk();

    $this->actingAs($kaLab)
        ->post(route('nilai.store'), readOnlyNilaiPayload($fixture))
        ->assertForbidden();

    expect(Nilai::query()->count())->toBe(0);
});
