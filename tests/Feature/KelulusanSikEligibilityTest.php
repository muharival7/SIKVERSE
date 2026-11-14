<?php

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\KelulusanSik;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\Santri;
use App\Models\SertifikatSik;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeKelulusanEligibilityFixture(int $jumlahHadir): array
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $santriUser = User::factory()->create(['role' => UserRole::Santri]);
    $santri = Santri::create([
        'user_id' => $santriUser->id,
        'nomor_induk' => 'SIK-'.fake()->unique()->numerify('######'),
        'nama' => $santriUser->name,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $instrukturUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instruktur = InfrastrukturSik::create([
        'user_id' => $instrukturUser->id,
        'nama' => $instrukturUser->name,
        'status' => 'Aktif',
    ]);
    $periode = PeriodeSik::create([
        'nama_periode' => 'Periode Uji '.fake()->unique()->numerify('######'),
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompok = Kelompok::create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instruktur->id,
        'nama_kelompok' => 'Kelompok Uji '.fake()->unique()->numerify('######'),
        'status' => 'Aktif',
    ]);
    $anggota = AnggotaPeriodeSik::create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'status' => 'Aktif',
    ]);
    $kegiatan = Kegiatan::create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'nama_kegiatan' => 'Kegiatan Uji',
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);

    for ($pertemuanKe = 1; $pertemuanKe <= 16; $pertemuanKe++) {
        $pertemuan = Pertemuan::create([
            'kegiatan_id' => $kegiatan->id,
            'pertemuan_ke' => $pertemuanKe,
            'tanggal' => now()->startOfYear()->addDays($pertemuanKe - 1)->toDateString(),
            'status' => 'Terlaksana',
        ]);

        AbsensiSik::create([
            'anggota_periode_sik_id' => $anggota->id,
            'pertemuan_id' => $pertemuan->id,
            'metode_absensi' => 'Manual',
            'status' => $pertemuanKe <= $jumlahHadir ? 'Hadir' : 'Alpha',
        ]);
    }

    return compact('admin', 'santri', 'anggota');
}

test('a santri with 13 of 16 attendances cannot be marked as passed', function () {
    $fixture = makeKelulusanEligibilityFixture(13);

    $this->actingAs($fixture['admin'])
        ->post(route('kelulusan-sik.store'), [
            'anggota_periode_sik_id' => $fixture['anggota']->id,
            'status' => 'Lulus',
        ])
        ->assertSessionHasErrors('status');

    $this->assertDatabaseMissing('kelulusan_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
    ]);
});

test('a santri with 14 of 16 attendances can be marked as passed', function () {
    $fixture = makeKelulusanEligibilityFixture(14);

    $this->actingAs($fixture['admin'])
        ->post(route('kelulusan-sik.store'), [
            'anggota_periode_sik_id' => $fixture['anggota']->id,
            'status' => 'Lulus',
        ])
        ->assertRedirect(route('kelulusan-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('kelulusan_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'status' => 'Lulus',
    ]);
});

test('a certificate cannot be issued to a lulus record below the attendance threshold', function () {
    Storage::fake('local');
    $fixture = makeKelulusanEligibilityFixture(13);
    $kelulusan = KelulusanSik::create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'status' => 'Lulus',
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('sertifikat-sik.store'), [
            'kelulusan_sik_id' => $kelulusan->id,
            'nomor_sertifikat' => 'CERT-13-001',
            'file_sertifikat' => UploadedFile::fake()->createWithContent(
                'sertifikat.pdf',
                "%PDF-1.4\nSertifikat uji\n"
            ),
        ])
        ->assertSessionHasErrors('kelulusan_sik_id');

    $this->assertDatabaseMissing('sertifikat_sik', [
        'kelulusan_sik_id' => $kelulusan->id,
    ]);
});

test('a certificate can be issued to a lulus record meeting the attendance threshold', function () {
    Storage::fake('local');
    $fixture = makeKelulusanEligibilityFixture(14);
    $kelulusan = KelulusanSik::create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'status' => 'Lulus',
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('sertifikat-sik.store'), [
            'kelulusan_sik_id' => $kelulusan->id,
            'nomor_sertifikat' => 'CERT-14-001',
            'file_sertifikat' => UploadedFile::fake()->createWithContent(
                'sertifikat.pdf',
                "%PDF-1.4\nSertifikat uji\n"
            ),
        ])
        ->assertRedirect(route('sertifikat-sik.index'))
        ->assertSessionHasNoErrors();

    $sertifikat = SertifikatSik::query()->where('kelulusan_sik_id', $kelulusan->id)->firstOrFail();
    Storage::disk('local')->assertExists($sertifikat->file_sertifikat);
});
