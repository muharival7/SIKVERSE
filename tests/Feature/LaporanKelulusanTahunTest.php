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
use App\Models\User;

function createLaporanTahunFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $kaLab = User::factory()->create(['role' => UserRole::KaLabMutu]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instrukturUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instruktur = InfrastrukturSik::query()->create([
        'user_id' => $instrukturUser->id,
        'nama' => 'Instruktur Laporan '.$suffix,
        'status' => 'Aktif',
    ]);
    $santriUser = User::factory()->create(['role' => UserRole::Santri]);

    $data = [];

    foreach ([2025, 2026] as $tahun) {
        $periode = PeriodeSik::query()->create([
            'nama_periode' => 'Periode Laporan '.$tahun.' '.$suffix,
            'tahun' => $tahun,
            'tanggal_mulai' => $tahun.'-01-01',
            'tanggal_selesai' => $tahun.'-12-31',
            'status' => 'Aktif',
        ]);
        $kelompok = Kelompok::query()->create([
            'periode_sik_id' => $periode->id,
            'instruktur_id' => $instruktur->id,
            'nama_kelompok' => 'Kelompok Laporan '.$tahun.' '.$suffix,
            'status' => 'Aktif',
        ]);
        $santri = Santri::query()->create([
            'nomor_induk' => 'LPT-'.$tahun.'-'.$suffix,
            'nama' => 'Santri Laporan '.$tahun.' '.$suffix,
            'jenis_kelamin' => 'Laki-laki',
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
            'nama_kegiatan' => 'Kegiatan Laporan '.$tahun.' '.$suffix,
            'jenis_kegiatan' => 'Kelas',
            'status' => 'Aktif',
        ]);

        for ($index = 1; $index <= 8; $index++) {
            $pertemuan = Pertemuan::query()->create([
                'kegiatan_id' => $kegiatan->id,
                'pertemuan_ke' => $index,
                'tanggal' => $tahun.'-02-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'status' => 'Terlaksana',
            ]);

            AbsensiSik::query()->create([
                'anggota_periode_sik_id' => $anggota->id,
                'pertemuan_id' => $pertemuan->id,
                'metode_absensi' => 'Manual',
                'status' => 'Hadir',
            ]);
        }

        $kelulusan = KelulusanSik::query()->create([
            'anggota_periode_sik_id' => $anggota->id,
            'status' => 'Lulus',
            'tanggal_kelulusan' => $tahun.'-12-01',
            'keterangan' => 'Catatan kelulusan '.$tahun,
        ]);

        $data[$tahun] = compact('periode', 'kelompok', 'santri', 'anggota', 'kegiatan', 'kelulusan');
    }

    return compact('suffix', 'kaLab', 'admin', 'instrukturUser', 'instruktur', 'santriUser', 'data');
}

test('ka lab mutu melihat tombol tahun dan tautan detail pada laporan kelulusan', function () {
    $fixture = createLaporanTahunFixture();

    $this->actingAs($fixture['kaLab'])
        ->get(route('laporan-sik'))
        ->assertOk()
        ->assertSee('Tahun 2025')
        ->assertSee('Tahun 2026')
        ->assertSee(route('laporan-sik.tahun', 2025))
        ->assertSee(route('laporan-sik.tahun', 2026))
        ->assertSee('Lihat Detail');
});

test('halaman detail tahun menampilkan metrik dan tabel santri tahun tersebut', function () {
    $fixture = createLaporanTahunFixture();

    $this->actingAs($fixture['kaLab'])
        ->get(route('laporan-sik.tahun', 2026))
        ->assertOk()
        ->assertSee('Rincian Kelulusan Tahun 2026')
        ->assertSee('Total Peserta')
        ->assertSee('Persentase Lulus')
        ->assertSee($fixture['data'][2026]['santri']->nama)
        ->assertSee($fixture['data'][2026]['santri']->nomor_induk)
        ->assertSee('Kembali ke Laporan');
});

test('halaman detail tahun tidak menampilkan santri dari tahun lain', function () {
    $fixture = createLaporanTahunFixture();

    $this->actingAs($fixture['kaLab'])
        ->get(route('laporan-sik.tahun', 2025))
        ->assertOk()
        ->assertSee($fixture['data'][2025]['santri']->nama)
        ->assertDontSee($fixture['data'][2026]['santri']->nama);
});

test('tahun yang tidak memiliki data kelulusan ditolak dengan 404', function () {
    $fixture = createLaporanTahunFixture();

    $this->actingAs($fixture['kaLab'])
        ->get(route('laporan-sik.tahun', 1999))
        ->assertNotFound();
});

test('halaman laporan tahunan hanya dapat diakses ka lab mutu', function (string $role) {
    $fixture = createLaporanTahunFixture();

    $user = match ($role) {
        'admin' => $fixture['admin'],
        'instruktur' => $fixture['instrukturUser'],
        default => $fixture['santriUser'],
    };

    $this->actingAs($user)
        ->get(route('laporan-sik'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('laporan-sik.tahun', 2026))
        ->assertForbidden();
})->with([
    'admin' => ['admin'],
    'instruktur' => ['instruktur'],
    'santri' => ['santri'],
]);
