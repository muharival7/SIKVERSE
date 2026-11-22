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
use App\Models\SertifikatSik;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** @return array<string, mixed> */
function createSertifikatKeputusanFixture(): array
{
    $suffix = fake()->unique()->numerify('######');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $santriUser = User::factory()->create(['role' => UserRole::Santri]);
    $santri = Santri::query()->create([
        'user_id' => $santriUser->id,
        'nomor_induk' => 'SKB-'.$suffix,
        'nama' => 'Santri Sertifikat Keputusan '.$suffix,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $instruktur = InfrastrukturSik::query()->create([
        'user_id' => User::factory()->create(['role' => UserRole::InfrastrukturSik])->id,
        'nama' => 'Instruktur Sertifikat Keputusan '.$suffix,
        'status' => 'Aktif',
    ]);
    $periode = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Sertifikat Keputusan '.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompok = Kelompok::query()->create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instruktur->id,
        'nama_kelompok' => 'Kelompok Sertifikat Keputusan '.$suffix,
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
        'nama_kegiatan' => 'Kegiatan Sertifikat Keputusan '.$suffix,
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

    $kelulusan = KelulusanSik::query()->create([
        'anggota_periode_sik_id' => $anggota->id,
        'status' => 'Lulus',
    ]);

    return compact('suffix', 'admin', 'santriUser', 'santri', 'anggota', 'kelulusan', 'periode', 'kelompok');
}

/** @param array<string, mixed> $fixture */
function recordSertifikatAdminDecision(
    array $fixture,
    string $status,
    ?string $createdAt = null
): RiwayatKeputusanKelulusan {
    $decision = RiwayatKeputusanKelulusan::query()->create([
        'kelulusan_sik_id' => $fixture['kelulusan']->id,
        'actor_user_id' => $fixture['admin']->id,
        'status_sebelumnya' => null,
        'status_sesudahnya' => $status,
        'alasan' => 'Keputusan pengujian sertifikat.',
    ]);

    if ($createdAt !== null) {
        $decision->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }

    return $decision;
}

/** @param array<string, mixed> $fixture
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sertifikatKeputusanPayload(array $fixture, array $overrides = []): array
{
    return array_merge([
        'kelulusan_sik_id' => $fixture['kelulusan']->id,
        'nomor_sertifikat' => 'SKB-CERT-'.$fixture['suffix'],
        'file_sertifikat' => UploadedFile::fake()->createWithContent(
            'sertifikat.pdf',
            "%PDF-1.4\nSertifikat keputusan admin\n"
        ),
    ], $overrides);
}

/** @param array<string, mixed> $fixture
 * @return array<string, mixed>
 */
function createFrozenSertifikatFixture(array $fixture): array
{
    $filePath = 'sertifikat-sik/frozen-'.$fixture['suffix'].'.pdf';
    Storage::disk('local')->put($filePath, "%PDF-1.4\nSertifikat lama beku\n");

    $certificate = SertifikatSik::query()->create([
        'kelulusan_sik_id' => $fixture['kelulusan']->id,
        'nomor_sertifikat' => 'SKB-FROZEN-'.$fixture['suffix'],
        'file_sertifikat' => $filePath,
        'tanggal_terbit' => '2026-11-01',
        'keterangan' => 'Sertifikat historis.',
    ]);

    $alternativeSantri = Santri::query()->create([
        'nomor_induk' => 'SKB-ALT-'.$fixture['suffix'],
        'nama' => 'Santri Alternatif '.$fixture['suffix'],
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $alternativeAnggota = AnggotaPeriodeSik::query()->create([
        'santri_id' => $alternativeSantri->id,
        'periode_sik_id' => $fixture['periode']->id,
        'kelompok_id' => $fixture['kelompok']->id,
        'status' => 'Aktif',
    ]);
    $alternativeGraduation = KelulusanSik::query()->create([
        'anggota_periode_sik_id' => $alternativeAnggota->id,
        'status' => 'Lulus',
    ]);

    return compact('certificate', 'filePath', 'alternativeGraduation');
}

test('belum ada keputusan Admin membuat create dan store sertifikat ditolak', function () {
    Storage::fake('local');
    $fixture = createSertifikatKeputusanFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('sertifikat-sik.create'))
        ->assertOk()
        ->assertViewHas('kelulusan', fn ($kelulusanList): bool => ! $kelulusanList->contains('id', $fixture['kelulusan']->id));

    $this->post(route('sertifikat-sik.store'), sertifikatKeputusanPayload($fixture))
        ->assertSessionHasErrors('kelulusan_sik_id');

    $this->assertDatabaseMissing('sertifikat_sik', [
        'kelulusan_sik_id' => $fixture['kelulusan']->id,
    ]);
});

test('keputusan Admin terbaru tidak lulus membuat create dan store sertifikat ditolak', function () {
    Storage::fake('local');
    $fixture = createSertifikatKeputusanFixture();
    recordSertifikatAdminDecision($fixture, 'Lulus', now()->subDay()->toDateTimeString());
    recordSertifikatAdminDecision($fixture, 'Tidak Lulus', now()->toDateTimeString());

    $this->actingAs($fixture['admin'])
        ->get(route('sertifikat-sik.create'))
        ->assertOk()
        ->assertViewHas('kelulusan', fn ($kelulusanList): bool => ! $kelulusanList->contains('id', $fixture['kelulusan']->id));

    $this->post(route('sertifikat-sik.store'), sertifikatKeputusanPayload($fixture))
        ->assertSessionHasErrors('kelulusan_sik_id');

    $this->assertDatabaseMissing('sertifikat_sik', [
        'kelulusan_sik_id' => $fixture['kelulusan']->id,
    ]);
});

test('keputusan Admin terbaru lulus mengizinkan create dan store sertifikat', function () {
    Storage::fake('local');
    $fixture = createSertifikatKeputusanFixture();
    recordSertifikatAdminDecision($fixture, 'Lulus');

    $this->actingAs($fixture['admin'])
        ->get(route('sertifikat-sik.create'))
        ->assertOk()
        ->assertViewHas('kelulusan', fn ($kelulusanList): bool => $kelulusanList->contains('id', $fixture['kelulusan']->id));

    $this->post(route('sertifikat-sik.store'), sertifikatKeputusanPayload($fixture))
        ->assertRedirect(route('sertifikat-sik.index'))
        ->assertSessionHasNoErrors();

    $certificate = SertifikatSik::query()->where('kelulusan_sik_id', $fixture['kelulusan']->id)->firstOrFail();
    Storage::disk('local')->assertExists($certificate->file_sertifikat);
});

test('pada timestamp sama ID terbesar menjadi acuan sehingga keputusan tidak lulus terbaru ditolak', function () {
    Storage::fake('local');
    $fixture = createSertifikatKeputusanFixture();
    $sameTimestamp = now()->toDateTimeString();
    recordSertifikatAdminDecision($fixture, 'Lulus', $sameTimestamp);
    recordSertifikatAdminDecision($fixture, 'Tidak Lulus', $sameTimestamp);

    $this->actingAs($fixture['admin'])
        ->post(route('sertifikat-sik.store'), sertifikatKeputusanPayload($fixture))
        ->assertSessionHasErrors('kelulusan_sik_id');

    $this->assertDatabaseMissing('sertifikat_sik', [
        'kelulusan_sik_id' => $fixture['kelulusan']->id,
    ]);
});

test('pada timestamp sama ID terbesar menjadi acuan sehingga keputusan lulus terbaru diterima', function () {
    Storage::fake('local');
    $fixture = createSertifikatKeputusanFixture();
    $sameTimestamp = now()->toDateTimeString();
    recordSertifikatAdminDecision($fixture, 'Tidak Lulus', $sameTimestamp);
    recordSertifikatAdminDecision($fixture, 'Lulus', $sameTimestamp);

    $this->actingAs($fixture['admin'])
        ->post(route('sertifikat-sik.store'), sertifikatKeputusanPayload($fixture))
        ->assertRedirect(route('sertifikat-sik.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('sertifikat_sik', [
        'kelulusan_sik_id' => $fixture['kelulusan']->id,
    ]);
});

test('update nomor sertifikat lama ditolak dan data lama tidak berubah', function () {
    Storage::fake('local');
    $fixture = createSertifikatKeputusanFixture();
    $frozen = createFrozenSertifikatFixture($fixture);

    $this->actingAs($fixture['admin'])
        ->put(route('sertifikat-sik.update', $frozen['certificate']), [
            'kelulusan_sik_id' => $fixture['kelulusan']->id,
            'nomor_sertifikat' => 'SKB-DIUBAH-'.$fixture['suffix'],
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('sertifikat_sik', [
        'id' => $frozen['certificate']->id,
        'nomor_sertifikat' => 'SKB-FROZEN-'.$fixture['suffix'],
        'file_sertifikat' => $frozen['filePath'],
        'kelulusan_sik_id' => $fixture['kelulusan']->id,
    ]);
});

test('penggantian PDF sertifikat lama ditolak dan berkas baru tidak disimpan', function () {
    Storage::fake('local');
    $fixture = createSertifikatKeputusanFixture();
    $frozen = createFrozenSertifikatFixture($fixture);

    $this->actingAs($fixture['admin'])
        ->put(route('sertifikat-sik.update', $frozen['certificate']), [
            'kelulusan_sik_id' => $fixture['kelulusan']->id,
            'nomor_sertifikat' => 'SKB-FROZEN-'.$fixture['suffix'],
            'file_sertifikat' => UploadedFile::fake()->createWithContent(
                'pengganti.pdf',
                "%PDF-1.4\nBerkas pengganti\n"
            ),
        ])
        ->assertForbidden();

    Storage::disk('local')->assertExists($frozen['filePath']);
    Storage::disk('local')->assertMissing('sertifikat-sik/pengganti.pdf');
    $this->assertDatabaseHas('sertifikat_sik', [
        'id' => $frozen['certificate']->id,
        'file_sertifikat' => $frozen['filePath'],
    ]);
});

test('pengubahan relasi kelulusan sertifikat lama ditolak', function () {
    Storage::fake('local');
    $fixture = createSertifikatKeputusanFixture();
    $frozen = createFrozenSertifikatFixture($fixture);

    $this->actingAs($fixture['admin'])
        ->put(route('sertifikat-sik.update', $frozen['certificate']), [
            'kelulusan_sik_id' => $frozen['alternativeGraduation']->id,
            'nomor_sertifikat' => 'SKB-FROZEN-'.$fixture['suffix'],
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('sertifikat_sik', [
        'id' => $frozen['certificate']->id,
        'kelulusan_sik_id' => $fixture['kelulusan']->id,
    ]);
});

test('delete sertifikat lama ditolak tetapi data dan file fisik tetap tersedia', function () {
    Storage::fake('local');
    $fixture = createSertifikatKeputusanFixture();
    $frozen = createFrozenSertifikatFixture($fixture);

    $this->actingAs($fixture['admin'])
        ->delete(route('sertifikat-sik.destroy', $frozen['certificate']))
        ->assertForbidden();

    $this->assertDatabaseHas('sertifikat_sik', [
        'id' => $frozen['certificate']->id,
        'file_sertifikat' => $frozen['filePath'],
    ]);
    Storage::disk('local')->assertExists($frozen['filePath']);
});

test('sertifikat lama tetap dapat dilihat dan diunduh oleh admin maupun santri', function () {
    Storage::fake('local');
    $fixture = createSertifikatKeputusanFixture();
    $frozen = createFrozenSertifikatFixture($fixture);

    $this->actingAs($fixture['admin'])
        ->get(route('sertifikat-sik.preview', $frozen['certificate']))
        ->assertOk();

    $this->actingAs($fixture['admin'])
        ->get(route('sertifikat-sik.download', $frozen['certificate']))
        ->assertOk();

    $this->actingAs($fixture['santriUser'])
        ->get(route('sertifikat-sik.download', $frozen['certificate']))
        ->assertOk();

    $this->actingAs($fixture['santriUser'])
        ->get(route('sertifikat-saya'))
        ->assertOk()
        ->assertSee('SKB-FROZEN-'.$fixture['suffix']);

    Storage::disk('local')->assertExists($frozen['filePath']);
});
