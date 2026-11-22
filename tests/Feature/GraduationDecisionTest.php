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
use App\Models\RiwayatKeputusanKelulusan;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** @return array<string, mixed> */
function createGraduationDecisionFixture(): array
{
    $suffix = fake()->unique()->numerify('######');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $santriUser = User::factory()->create(['role' => UserRole::Santri]);
    $santri = Santri::query()->create([
        'user_id' => $santriUser->id,
        'nomor_induk' => 'KDP-'.$suffix,
        'nama' => 'Santri Keputusan '.$suffix,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $instruktur = InfrastrukturSik::query()->create([
        'user_id' => User::factory()->create(['role' => UserRole::InfrastrukturSik])->id,
        'nama' => 'Instruktur Keputusan '.$suffix,
        'status' => 'Aktif',
    ]);
    $periode = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Keputusan '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompok = Kelompok::query()->create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instruktur->id,
        'nama_kelompok' => 'Kelompok Keputusan '.$suffix,
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
        'nama_kegiatan' => 'Kegiatan Keputusan '.$suffix,
        'jenis_kegiatan' => 'Kelas',
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

    return compact('admin', 'santri', 'anggota');
}

test('admin membuat keputusan kelulusan baru dan actor dicatat', function () {
    $fixture = createGraduationDecisionFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('kelulusan-sik.store'), [
            'anggota_periode_sik_id' => $fixture['anggota']->id,
            'status' => 'Lulus',
            'keterangan' => 'Keputusan admin baru.',
        ])
        ->assertRedirect(route('kelulusan-sik.index'))
        ->assertSessionHasNoErrors();

    $kelulusan = KelulusanSik::query()->where('anggota_periode_sik_id', $fixture['anggota']->id)->firstOrFail();

    $this->assertDatabaseHas('riwayat_keputusan_kelulusan', [
        'kelulusan_sik_id' => $kelulusan->id,
        'actor_user_id' => $fixture['admin']->id,
        'status_sebelumnya' => null,
        'status_sesudahnya' => 'Lulus',
        'alasan' => 'Keputusan admin baru.',
    ]);
});

test('perubahan status kelulusan oleh admin menambah keputusan baru', function () {
    $fixture = createGraduationDecisionFixture();
    $kelulusan = KelulusanSik::query()->create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'status' => 'Lulus',
    ]);

    $this->actingAs($fixture['admin'])
        ->put(route('kelulusan-sik.update', $kelulusan), [
            'anggota_periode_sik_id' => $fixture['anggota']->id,
            'status' => 'Tidak Lulus',
            'keterangan' => 'Koreksi keputusan.',
        ])
        ->assertRedirect(route('kelulusan-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('riwayat_keputusan_kelulusan', [
        'kelulusan_sik_id' => $kelulusan->id,
        'actor_user_id' => $fixture['admin']->id,
        'status_sebelumnya' => 'Lulus',
        'status_sesudahnya' => 'Tidak Lulus',
        'alasan' => 'Koreksi keputusan.',
    ]);

    expect(RiwayatKeputusanKelulusan::query()->where('kelulusan_sik_id', $kelulusan->id)->count())->toBe(1);
});

test('keputusan admin tidak lulus menghalangi sertifikat walaupun status tersimpan lulus', function () {
    Storage::fake('local');
    $fixture = createGraduationDecisionFixture();
    $kelulusan = KelulusanSik::query()->create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'status' => 'Lulus',
    ]);
    RiwayatKeputusanKelulusan::query()->create([
        'kelulusan_sik_id' => $kelulusan->id,
        'actor_user_id' => $fixture['admin']->id,
        'status_sesudahnya' => 'Tidak Lulus',
        'alasan' => 'Pembatalan keputusan.',
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('sertifikat-sik.store'), [
            'kelulusan_sik_id' => $kelulusan->id,
            'nomor_sertifikat' => 'KDP-BATAL-'.$kelulusan->id,
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

test('form sertifikat tidak menawarkan kelulusan dengan keputusan admin tidak lulus', function () {
    $fixture = createGraduationDecisionFixture();
    $kelulusan = KelulusanSik::query()->create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'status' => 'Lulus',
    ]);
    RiwayatKeputusanKelulusan::query()->create([
        'kelulusan_sik_id' => $kelulusan->id,
        'actor_user_id' => $fixture['admin']->id,
        'status_sesudahnya' => 'Tidak Lulus',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('sertifikat-sik.create'))
        ->assertOk()
        ->assertViewHas('kelulusan', function ($kelulusanList) use ($kelulusan): bool {
            return ! $kelulusanList->contains('id', $kelulusan->id);
        });
});
