<?php

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function createRabuModuleFixture(array $overrides = []): array
{
    $suffix = fake()->unique()->numerify('######');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instrukturUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instruktur = InfrastrukturSik::create([
        'user_id' => $instrukturUser->id,
        'nama' => 'Instruktur Modul '.$suffix,
        'status' => 'Aktif',
    ]);
    $instrukturSainsUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instrukturSains = InfrastrukturSik::create([
        'user_id' => $instrukturSainsUser->id,
        'nama' => 'Instruktur Sains Modul '.$suffix,
        'status' => 'Aktif',
    ]);
    $periode = PeriodeSik::create([
        'nama_periode' => 'modul_'.$suffix,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompok = Kelompok::create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instruktur->id,
        'nama_kelompok' => 'Sistem Informasi Modul '.$suffix,
        'status' => 'Aktif',
    ]);
    $kelompokSains = Kelompok::create([
        'periode_sik_id' => $periode->id,
        'instruktur_id' => $instrukturSains->id,
        'nama_kelompok' => 'Sains Modul '.$suffix,
        'status' => 'Aktif',
    ]);
    $kegiatan = Kegiatan::create([
        'periode_sik_id' => $periode->id,
        'kelompok_id' => null,
        'nama_kegiatan' => 'Rabu Mengaji Modul '.$suffix,
        'jenis_kegiatan' => 'Aula',
        'status' => 'Aktif',
    ]);
    $santri = Santri::create([
        'nomor_induk' => 'RABU-'.$suffix,
        'nama' => 'Santri Modul '.$suffix,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $anggota = AnggotaPeriodeSik::create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompok->id,
        'status' => 'Aktif',
    ]);
    $santriSains = Santri::create([
        'nomor_induk' => 'SAINS-'.$suffix,
        'nama' => 'Santri Sains Modul '.$suffix,
        'jenis_kelamin' => 'Perempuan',
        'status' => 'Aktif',
    ]);
    $anggotaSains = AnggotaPeriodeSik::create([
        'santri_id' => $santriSains->id,
        'periode_sik_id' => $periode->id,
        'kelompok_id' => $kelompokSains->id,
        'status' => 'Aktif',
    ]);

    return array_merge(compact(
        'admin',
        'instrukturUser',
        'instruktur',
        'instrukturSainsUser',
        'instrukturSains',
        'periode',
        'kelompok',
        'kelompokSains',
        'kegiatan',
        'santri',
        'anggota',
        'santriSains',
        'anggotaSains'
    ), $overrides);
}

function createRabuModulePertemuan(
    array $fixture,
    string $status = 'Terlaksana',
    int $pertemuanKe = 1,
    ?Kegiatan $kegiatan = null
): Pertemuan {
    $kegiatan ??= $fixture['kegiatan'];

    return Pertemuan::create([
        'kegiatan_id' => $kegiatan->id,
        'pertemuan_ke' => $pertemuanKe,
        'tanggal' => '2026-06-15',
        'instruktur_pengganti_id' => $fixture['instruktur']->id,
        'kelompok_host_id' => $kegiatan->kelompok_id,
        'status' => $status,
    ]);
}

function createRabuModuleKegiatanReguler(array $fixture, string $namaKegiatan): Kegiatan
{
    return Kegiatan::create([
        'periode_sik_id' => $fixture['periode']->id,
        'kelompok_id' => $fixture['kelompok']->id,
        'nama_kegiatan' => $namaKegiatan,
        'jenis_kegiatan' => 'Aula',
        'status' => 'Aktif',
    ]);
}

test('dropdown tambah kegiatan menampilkan kelompok aktif sesuai periode', function () {
    $fixture = createRabuModuleFixture();

    Kelompok::create([
        'periode_sik_id' => $fixture['periode']->id,
        'instruktur_id' => $fixture['instruktur']->id,
        'nama_kelompok' => 'Kelompok Nonaktif Modul',
        'status' => 'Tidak Aktif',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('kegiatan.create'))
        ->assertOk()
        ->assertSee($fixture['kelompok']->nama_kelompok)
        ->assertSee($fixture['periode']->nama_periode)
        ->assertSee('data-periode="'.$fixture['periode']->id.'"', false)
        ->assertDontSee('Kelompok Nonaktif Modul');
});

test('kelompok tersimpan ketika kegiatan aula reguler dibuat', function () {
    $fixture = createRabuModuleFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('kegiatan.store'), [
            'nama_kegiatan' => 'Teknologi Modul Baru',
            'jenis_kegiatan' => 'Aula',
            'periode_sik_id' => $fixture['periode']->id,
            'kelompok_id' => $fixture['kelompok']->id,
            'status' => 'Aktif',
        ])
        ->assertRedirect(route('kegiatan.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('kegiatan', [
        'nama_kegiatan' => 'Teknologi Modul Baru',
        'jenis_kegiatan' => 'Aula',
        'kelompok_id' => $fixture['kelompok']->id,
    ]);
});

test('kegiatan rabu mengaji tersimpan tanpa kelompok', function () {
    $fixture = createRabuModuleFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('kegiatan.store'), [
            'nama_kegiatan' => 'Rabu Mengaji Baru',
            'jenis_kegiatan' => 'Aula',
            'periode_sik_id' => $fixture['periode']->id,
            'kelompok_id' => $fixture['kelompok']->id,
            'status' => 'Aktif',
        ])
        ->assertSessionHasErrors('kelompok_id');

    $this->actingAs($fixture['admin'])
        ->post(route('kegiatan.store'), [
            'nama_kegiatan' => 'Rabu Mengaji Baru',
            'jenis_kegiatan' => 'Aula',
            'periode_sik_id' => $fixture['periode']->id,
            'status' => 'Aktif',
        ])
        ->assertRedirect(route('kegiatan.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('kegiatan', [
        'nama_kegiatan' => 'Rabu Mengaji Baru',
        'kelompok_id' => null,
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('kegiatan.index'))
        ->assertOk()
        ->assertSee('Semua Kelompok');
});

test('form edit kegiatan menampilkan kelompok yang sudah tersimpan', function () {
    $fixture = createRabuModuleFixture();
    $kegiatanReguler = createRabuModuleKegiatanReguler($fixture, 'Teknologi Modul Edit');

    $response = $this->actingAs($fixture['admin'])
        ->get(route('kegiatan.edit', $kegiatanReguler))
        ->assertOk()
        ->assertSee($fixture['kelompok']->nama_kelompok);

    $response->assertSee(
        'value="'.$fixture['kelompok']->id.'"',
        false
    );

    $this->assertMatchesRegularExpression(
        '/<option value="'.$fixture['kelompok']->id.'"\s+data-periode="'.$fixture['periode']->id.'"\s+selected>/',
        $response->getContent()
    );
});

test('admin dapat mengganti kelompok kegiatan dan perubahan tampil di daftar', function () {
    $fixture = createRabuModuleFixture();
    $kegiatanReguler = createRabuModuleKegiatanReguler($fixture, 'Teknologi Modul Ganti');

    $kelompokBaru = Kelompok::create([
        'periode_sik_id' => $fixture['periode']->id,
        'instruktur_id' => $fixture['instruktur']->id,
        'nama_kelompok' => 'Kelompok Pengganti Modul',
        'status' => 'Aktif',
    ]);

    $this->actingAs($fixture['admin'])
        ->put(route('kegiatan.update', $kegiatanReguler), [
            'nama_kegiatan' => $kegiatanReguler->nama_kegiatan,
            'jenis_kegiatan' => 'Aula',
            'periode_sik_id' => $fixture['periode']->id,
            'kelompok_id' => $kelompokBaru->id,
            'status' => 'Aktif',
        ])
        ->assertRedirect(route('kegiatan.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('kegiatan', [
        'id' => $kegiatanReguler->id,
        'kelompok_id' => $kelompokBaru->id,
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('kegiatan.index'))
        ->assertOk()
        ->assertSee('Kelompok Pengganti Modul')
        ->assertDontSee($fixture['kelompok']->nama_kelompok);
});

test('perubahan kelompok kegiatan menyamakan kelompok host pertemuan sebelum ada absensi', function () {
    $fixture = createRabuModuleFixture();
    $kegiatanReguler = createRabuModuleKegiatanReguler($fixture, 'Teknologi Modul Host');
    $pertemuan = createRabuModulePertemuan($fixture, 'Terlaksana', 1, $kegiatanReguler);

    $kelompokBaru = Kelompok::create([
        'periode_sik_id' => $fixture['periode']->id,
        'instruktur_id' => $fixture['instruktur']->id,
        'nama_kelompok' => 'Kelompok Host Tersinkron',
        'status' => 'Aktif',
    ]);

    $this->actingAs($fixture['admin'])
        ->put(route('kegiatan.update', $kegiatanReguler), [
            'nama_kegiatan' => $kegiatanReguler->nama_kegiatan,
            'jenis_kegiatan' => 'Aula',
            'periode_sik_id' => $fixture['periode']->id,
            'kelompok_id' => $kelompokBaru->id,
            'status' => 'Aktif',
        ])
        ->assertRedirect(route('kegiatan.index'));

    $this->assertDatabaseHas('pertemuan', [
        'id' => $pertemuan->id,
        'kelompok_host_id' => $kelompokBaru->id,
    ]);
});

test('validasi kegiatan menolak kelompok dari periode lain', function () {
    $fixture = createRabuModuleFixture();

    $periodeLain = PeriodeSik::create([
        'nama_periode' => 'lain_'.$fixture['periode']->id,
        'tahun' => 2026,
        'tanggal_mulai' => '2026-01-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $kelompokLain = Kelompok::create([
        'periode_sik_id' => $periodeLain->id,
        'instruktur_id' => $fixture['instruktur']->id,
        'nama_kelompok' => 'Kelompok Periode Lain',
        'status' => 'Aktif',
    ]);
    $jumlahSebelum = Kegiatan::count();

    $this->actingAs($fixture['admin'])
        ->post(route('kegiatan.store'), [
            'nama_kegiatan' => 'Kegiatan Periode Salah',
            'jenis_kegiatan' => 'Aula',
            'periode_sik_id' => $fixture['periode']->id,
            'kelompok_id' => $kelompokLain->id,
            'status' => 'Aktif',
        ])
        ->assertSessionHasErrors('kelompok_id');

    $this->assertSame($jumlahSebelum, Kegiatan::count());
});

test('validasi kegiatan menolak kelompok nonaktif', function () {
    $fixture = createRabuModuleFixture();

    $kelompokNonaktif = Kelompok::create([
        'periode_sik_id' => $fixture['periode']->id,
        'instruktur_id' => $fixture['instruktur']->id,
        'nama_kelompok' => 'Kelompok Mati Modul',
        'status' => 'Tidak Aktif',
    ]);
    $jumlahSebelum = Kegiatan::count();

    $this->actingAs($fixture['admin'])
        ->post(route('kegiatan.store'), [
            'nama_kegiatan' => 'Kegiatan Kelompok Mati',
            'jenis_kegiatan' => 'Aula',
            'periode_sik_id' => $fixture['periode']->id,
            'kelompok_id' => $kelompokNonaktif->id,
            'status' => 'Aktif',
        ])
        ->assertSessionHasErrors('kelompok_id');

    $this->assertSame($jumlahSebelum, Kegiatan::count());
});

test('pertemuan rabu mengaji tersimpan tanpa kelompok host', function () {
    $fixture = createRabuModuleFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('pertemuan.store'), [
            'kegiatan_id' => $fixture['kegiatan']->id,
            'pertemuan_ke' => 1,
            'tanggal' => '2026-06-15',
            'status' => 'Terlaksana',
        ])
        ->assertRedirect(route('pertemuan.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('pertemuan', [
        'kegiatan_id' => $fixture['kegiatan']->id,
        'pertemuan_ke' => 1,
        'kelompok_host_id' => null,
        'status' => 'Terlaksana',
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('pertemuan.store'), [
            'kegiatan_id' => $fixture['kegiatan']->id,
            'pertemuan_ke' => 2,
            'tanggal' => '2026-06-22',
            'kelompok_host_id' => $fixture['kelompok']->id,
            'status' => 'Terjadwal',
        ])
        ->assertSessionHasErrors('kelompok_host_id');

    $this->assertDatabaseCount('pertemuan', 1);
});

test('pertemuan yang dibuat dapat ditemukan oleh halaman rabu mengaji', function () {
    $fixture = createRabuModuleFixture();
    createRabuModulePertemuan($fixture);

    $pertemuan = Pertemuan::query()->firstOrFail();

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.input', $pertemuan))
        ->assertOk()
        ->assertSee($fixture['santri']->nama)
        ->assertSee($fixture['santri']->nomor_induk);
});

test('daftar santri input menampilkan anggota aktif dari semua kelompok pada periode kegiatan', function () {
    $fixture = createRabuModuleFixture();
    $pertemuan = createRabuModulePertemuan($fixture);

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.input', $pertemuan))
        ->assertOk()
        ->assertSee('Semua Kelompok')
        ->assertSee('Kelompok Asal')
        ->assertSee($fixture['santri']->nama)
        ->assertSee($fixture['santri']->nomor_induk)
        ->assertSee($fixture['kelompok']->nama_kelompok)
        ->assertSee($fixture['santriSains']->nama)
        ->assertSee($fixture['santriSains']->nomor_induk)
        ->assertSee($fixture['kelompokSains']->nama_kelompok);
});

test('input absensi tersimpan ke absensi_sik dan tampil pada daftar kehadiran', function () {
    $fixture = createRabuModuleFixture();
    $pertemuan = createRabuModulePertemuan($fixture);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.store'), [
            'pertemuan_id' => $pertemuan->id,
            'status' => [
                $fixture['anggota']->id => 'Hadir',
                $fixture['anggotaSains']->id => 'Hadir',
            ],
            'keterangan' => [
                $fixture['anggota']->id => 'Hadir tepat waktu',
                $fixture['anggotaSains']->id => 'Hadir tepat waktu',
            ],
        ])
        ->assertRedirect(route('rabu-mengaji.index'));

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $pertemuan->id,
        'status' => 'Hadir',
        'keterangan' => 'Hadir tepat waktu',
    ]);
    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggotaSains']->id,
        'pertemuan_id' => $pertemuan->id,
        'status' => 'Hadir',
        'keterangan' => 'Hadir tepat waktu',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index'))
        ->assertOk()
        ->assertSee($fixture['santri']->nama)
        ->assertSee($fixture['santri']->nomor_induk)
        ->assertSee($fixture['kelompok']->nama_kelompok)
        ->assertSee($fixture['santriSains']->nama)
        ->assertSee($fixture['santriSains']->nomor_induk)
        ->assertSee($fixture['kelompokSains']->nama_kelompok)
        ->assertSee('15/06/2026')
        ->assertSee($fixture['kegiatan']->nama_kegiatan)
        ->assertSee('Hadir')
        ->assertSee('100,0%')
        ->assertViewHas('summary', function ($summary): bool {
            return (int) $summary->total_pertemuan === 2
                && (int) $summary->total_hadir === 2
                && (int) $summary->total_izin === 0
                && (int) $summary->total_sakit === 0
                && (int) $summary->total_alpha === 0;
        });
});

test('statistik tidak menghitung absensi pada pertemuan yang tidak terlaksana', function () {
    $fixture = createRabuModuleFixture();
    $terlaksana = createRabuModulePertemuan($fixture, 'Terlaksana', 1);
    $terjadwal = createRabuModulePertemuan($fixture, 'Terjadwal', 2);

    AbsensiSik::create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $terlaksana->id,
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
    ]);
    AbsensiSik::create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $terjadwal->id,
        'metode_absensi' => 'Manual',
        'status' => 'Alpha',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index'))
        ->assertOk()
        ->assertViewHas('summary', function ($summary): bool {
            return (int) $summary->total_pertemuan === 1
                && (int) $summary->total_hadir === 1
                && (int) $summary->total_alpha === 0;
        });
});

test('filter nama dan tanggal berfungsi pada daftar kehadiran rabu mengaji', function () {
    $fixture = createRabuModuleFixture();
    $pertemuan = createRabuModulePertemuan($fixture);

    $santriLain = Santri::create([
        'nomor_induk' => 'FILTER-'.fake()->unique()->numerify('######'),
        'nama' => 'Santri Filter Lain',
        'jenis_kelamin' => 'Perempuan',
        'status' => 'Aktif',
    ]);
    $anggotaLain = AnggotaPeriodeSik::create([
        'santri_id' => $santriLain->id,
        'periode_sik_id' => $fixture['periode']->id,
        'kelompok_id' => $fixture['kelompok']->id,
        'status' => 'Aktif',
    ]);

    foreach ([$fixture['anggota']->id => 'Hadir', $anggotaLain->id => 'Izin'] as $anggotaId => $status) {
        AbsensiSik::create([
            'anggota_periode_sik_id' => $anggotaId,
            'pertemuan_id' => $pertemuan->id,
            'metode_absensi' => 'Manual',
            'status' => $status,
        ]);
    }

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index', ['search' => $fixture['santri']->nama]))
        ->assertOk()
        ->assertSee($fixture['santri']->nama)
        ->assertDontSee($santriLain->nama);

    $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.index', ['tanggal' => '2026-06-16']))
        ->assertOk()
        ->assertSee('Belum ada kehadiran Rabu Mengaji untuk filter terpilih.');
});

test('pertemuan dibatalkan ditolak saat input kehadiran', function () {
    $fixture = createRabuModuleFixture();
    $pertemuan = createRabuModulePertemuan($fixture, 'Dibatalkan');

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.store'), [
            'pertemuan_id' => $pertemuan->id,
            'status' => [$fixture['anggota']->id => 'Hadir'],
        ])
        ->assertSessionHasErrors('pertemuan_id');

    $this->assertDatabaseCount('absensi_sik', 0);
});

test('absensi ganda tidak dibuat dan status lama tidak ditimpa', function () {
    $fixture = createRabuModuleFixture();
    $pertemuan = createRabuModulePertemuan($fixture);

    AbsensiSik::create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $pertemuan->id,
        'metode_absensi' => 'Manual',
        'status' => 'Alpha',
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.store'), [
            'pertemuan_id' => $pertemuan->id,
            'status' => [$fixture['anggota']->id => 'Hadir'],
        ])
        ->assertSessionHasErrors('pertemuan_id');

    $this->assertDatabaseCount('absensi_sik', 1);
    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $pertemuan->id,
        'status' => 'Alpha',
    ]);
});

test('instruktur aktif dapat membuka input kehadiran lintas kelompok', function () {
    $fixture = createRabuModuleFixture();
    $pertemuan = createRabuModulePertemuan($fixture);

    $this->actingAs($fixture['instrukturUser'])
        ->get(route('rabu-mengaji.input', $pertemuan))
        ->assertOk()
        ->assertSee($fixture['santriSains']->nama);

    $this->actingAs($fixture['instrukturSainsUser'])
        ->get(route('rabu-mengaji.input', $pertemuan))
        ->assertOk()
        ->assertSee($fixture['santri']->nama);
});

test('instruktur tidak aktif ditolak membuka input kehadiran rabu mengaji', function () {
    $fixture = createRabuModuleFixture();
    $pertemuan = createRabuModulePertemuan($fixture);

    $instrukturNonaktifUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    InfrastrukturSik::create([
        'user_id' => $instrukturNonaktifUser->id,
        'nama' => 'Instruktur Nonaktif Modul',
        'status' => 'Tidak Aktif',
    ]);

    $this->actingAs($instrukturNonaktifUser)
        ->get(route('rabu-mengaji.input', $pertemuan))
        ->assertForbidden();
});

test('santri tidak dapat membuka halaman input kehadiran rabu mengaji', function () {
    $fixture = createRabuModuleFixture();
    $pertemuan = createRabuModulePertemuan($fixture);
    $santriUser = User::factory()->create(['role' => UserRole::Santri]);

    $this->actingAs($santriUser)
        ->get(route('rabu-mengaji.input', $pertemuan))
        ->assertForbidden();
});

test('kelompok kegiatan tidak dapat diganti setelah absensi tercatat', function () {
    $fixture = createRabuModuleFixture();
    $kegiatanReguler = createRabuModuleKegiatanReguler($fixture, 'Teknologi Modul Terkunci');
    $pertemuan = createRabuModulePertemuan($fixture, 'Terlaksana', 1, $kegiatanReguler);

    AbsensiSik::create([
        'anggota_periode_sik_id' => $fixture['anggota']->id,
        'pertemuan_id' => $pertemuan->id,
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
    ]);

    $kelompokBaru = Kelompok::create([
        'periode_sik_id' => $fixture['periode']->id,
        'instruktur_id' => $fixture['instruktur']->id,
        'nama_kelompok' => 'Kelompok Terlarang Modul',
        'status' => 'Aktif',
    ]);

    $this->actingAs($fixture['admin'])
        ->put(route('kegiatan.update', $kegiatanReguler), [
            'nama_kegiatan' => $kegiatanReguler->nama_kegiatan,
            'jenis_kegiatan' => 'Aula',
            'periode_sik_id' => $fixture['periode']->id,
            'kelompok_id' => $kelompokBaru->id,
            'status' => 'Aktif',
        ])
        ->assertSessionHasErrors('periode_sik_id');

    $this->assertDatabaseHas('kegiatan', [
        'id' => $kegiatanReguler->id,
        'kelompok_id' => $fixture['kelompok']->id,
    ]);

    $this->assertSame(0, DB::table('pertemuan')
        ->where('id', $pertemuan->id)
        ->where('kelompok_host_id', $kelompokBaru->id)
        ->count());
});
