<?php

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\BuktiKetidakhadiran;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createAttendanceEvidenceFixture(): array
{
    $santriUser = User::factory()->create(['role' => UserRole::Santri]);
    $santri = Santri::create([
        'user_id' => $santriUser->id,
        'nomor_induk' => 'SIK-TEST-001',
        'nama' => 'Santri Penguji',
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);

    $instrukturUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instruktur = InfrastrukturSik::create([
        'user_id' => $instrukturUser->id,
        'nama' => 'Instruktur Penguji',
        'status' => 'Aktif',
    ]);

    $periode = PeriodeSik::create([
        'nama_periode' => 'Periode Test',
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompok = Kelompok::create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instruktur->id,
        'nama_kelompok' => 'Kelompok Test',
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
        'nama_kegiatan' => 'Kegiatan Test',
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);
    $pertemuan = Pertemuan::create([
        'kegiatan_id' => $kegiatan->id,
        'pertemuan_ke' => 1,
        'tanggal' => '2026-11-08',
        'status' => 'Terlaksana',
    ]);
    $absensi = AbsensiSik::create([
        'anggota_periode_sik_id' => $anggota->id,
        'pertemuan_id' => $pertemuan->id,
        'metode_absensi' => 'Manual',
        'status' => 'Izin',
    ]);

    return compact('santriUser', 'instrukturUser', 'instruktur', 'absensi');
}

test('a santri can submit their own evidence and the assigned instructor can verify it', function () {
    Storage::fake('local');
    $fixture = createAttendanceEvidenceFixture();

    $this->actingAs($fixture['santriUser'])
        ->post(route('absensi-saya.bukti.store', $fixture['absensi']), [
            'jenis_bukti' => 'Izin',
            'file_bukti' => UploadedFile::fake()->createWithContent(
                'surat-izin.pdf',
                "%PDF-1.4\nBukti izin test\n"
            ),
        ])
        ->assertRedirect();

    $bukti = BuktiKetidakhadiran::firstOrFail();
    expect($bukti->status_verifikasi)->toBe('Menunggu');
    Storage::disk('local')->assertExists($bukti->file_bukti);

    $this->actingAs($fixture['instrukturUser'])
        ->get(route('verifikasi-izin.index'))
        ->assertOk()
        ->assertSee('Santri Penguji');

    $this->patch(route('verifikasi-izin.update', $bukti), [
        'status_verifikasi' => 'Diterima',
        'catatan_pemeriksaan' => 'Bukti sesuai.',
    ])->assertRedirect();

    $this->assertDatabaseHas('bukti_ketidakhadiran', [
        'id' => $bukti->id,
        'status_verifikasi' => 'Diterima',
        'diperiksa_oleh' => $fixture['instruktur']->id,
    ]);
});

test('an instructor cannot review evidence for another instructor group', function () {
    $fixture = createAttendanceEvidenceFixture();
    $otherUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    InfrastrukturSik::create([
        'user_id' => $otherUser->id,
        'nama' => 'Instruktur Lain',
        'status' => 'Aktif',
    ]);
    $bukti = BuktiKetidakhadiran::create([
        'absensi_sik_id' => $fixture['absensi']->id,
        'jenis_bukti' => 'Izin',
        'file_bukti' => 'bukti-ketidakhadiran/test.pdf',
        'status_verifikasi' => 'Menunggu',
    ]);

    $this->actingAs($otherUser)
        ->patch(route('verifikasi-izin.update', $bukti), [
            'status_verifikasi' => 'Diterima',
            'catatan_pemeriksaan' => 'Bukti sesuai.',
        ])
        ->assertForbidden();
});
