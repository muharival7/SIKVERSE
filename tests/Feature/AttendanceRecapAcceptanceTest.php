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
use App\Models\RekapKehadiranSik;
use App\Models\Santri;
use App\Models\SertifikatSik;
use App\Models\User;

function makeAcceptanceBaseFixture(): array
{
    $suffix = fake()->unique()->numerify('######');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Penerimaan '.$suffix,
        'status' => 'Aktif',
    ]);
    $otherInstructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $otherInstructor = InfrastrukturSik::query()->create([
        'user_id' => $otherInstructorUser->id,
        'nama' => 'Instruktur Lain '.$suffix,
        'status' => 'Aktif',
    ]);

    $period = PeriodeSik::query()->create([
        'nama_periode' => 'Periode Penerimaan '.$suffix,
        'tahun' => now()->year,
        'tanggal_mulai' => now()->startOfYear()->toDateString(),
        'tanggal_selesai' => now()->endOfYear()->toDateString(),
        'status' => 'Aktif',
    ]);
    $groupA = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok A '.$suffix,
        'status' => 'Aktif',
    ]);
    $groupB = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok B '.$suffix,
        'status' => 'Aktif',
    ]);
    $otherGroup = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $otherInstructor->id,
        'nama_kelompok' => 'Kelompok Instruktur Lain '.$suffix,
        'status' => 'Aktif',
    ]);

    return compact(
        'suffix',
        'admin',
        'instructorUser',
        'instructor',
        'otherInstructorUser',
        'otherInstructor',
        'period',
        'groupA',
        'groupB',
        'otherGroup'
    );
}

function createAcceptanceMember(array $fixture, Kelompok $group, string $label, string $status = 'Aktif'): AnggotaPeriodeSik
{
    $santri = Santri::query()->create([
        'nomor_induk' => 'ACC-'.$fixture['suffix'].'-'.$label,
        'nama' => 'Santri '.$label.' '.$fixture['suffix'],
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);

    return AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $fixture['period']->id,
        'kelompok_id' => $group->id,
        'status' => $status,
    ]);
}

function createAcceptanceActivity(array $fixture, Kelompok $group): Kegiatan
{
    return Kegiatan::query()->create([
        'periode_sik_id' => $fixture['period']->id,
        'kelompok_id' => $group->id,
        'nama_kegiatan' => 'Kegiatan '.$group->nama_kelompok,
        'jenis_kegiatan' => 'Kelas',
        'status' => 'Aktif',
    ]);
}

function createAcceptanceAttendance(AnggotaPeriodeSik $member, Kegiatan $activity, array $statuses): void
{
    foreach ($statuses as $index => $status) {
        $meeting = Pertemuan::query()->create([
            'kegiatan_id' => $activity->id,
            'pertemuan_ke' => $index + 1,
            'tanggal' => now()->startOfYear()->addDays($index)->toDateString(),
            'status' => 'Terlaksana',
        ]);

        AbsensiSik::query()->create([
            'anggota_periode_sik_id' => $member->id,
            'pertemuan_id' => $meeting->id,
            'metode_absensi' => 'Manual',
            'status' => $status,
        ]);
    }
}

function makeAcceptanceAttendanceFixture(array $statuses): array
{
    $fixture = makeAcceptanceBaseFixture();
    $member = createAcceptanceMember($fixture, $fixture['groupA'], 'Absensi');
    $activity = createAcceptanceActivity($fixture, $fixture['groupA']);
    createAcceptanceAttendance($member, $activity, $statuses);

    return [...$fixture, 'member' => $member, 'activity' => $activity];
}

test('rekap instruktur menampilkan semua anggota aktif meski belum memiliki absensi atau rekap', function () {
    $fixture = makeAcceptanceBaseFixture();
    $tanpaAbsensi = createAcceptanceMember($fixture, $fixture['groupA'], 'Tanpa Absensi');
    $tanpaKegiatan = createAcceptanceMember($fixture, $fixture['groupB'], 'Tanpa Kegiatan');
    createAcceptanceMember($fixture, $fixture['groupA'], 'Nonaktif', 'Tidak Aktif');

    $activity = createAcceptanceActivity($fixture, $fixture['groupA']);
    Pertemuan::query()->create([
        'kegiatan_id' => $activity->id,
        'pertemuan_ke' => 1,
        'tanggal' => now()->startOfYear()->toDateString(),
        'status' => 'Terlaksana',
    ]);

    expect(RekapKehadiranSik::query()->count())->toBe(0);

    $this->actingAs($fixture['instructorUser'])
        ->get(route('rekap-kehadiran-sik.index'))
        ->assertOk()
        ->assertSee($tanpaAbsensi->santri->nama)
        ->assertSee($tanpaKegiatan->santri->nama)
        ->assertSee('Belum Ada Data Kehadiran')
        ->assertSee('Belum Ada Pertemuan Terlaksana')
        ->assertDontSee('Santri Nonaktif '.$fixture['suffix']);
});

test('satu instruktur dapat mengampu beberapa kelompok tanpa menampilkan kelompok instruktur lain', function () {
    $fixture = makeAcceptanceBaseFixture();
    $memberA = createAcceptanceMember($fixture, $fixture['groupA'], 'Kelompok A');
    $memberB = createAcceptanceMember($fixture, $fixture['groupB'], 'Kelompok B');
    $otherMember = createAcceptanceMember($fixture, $fixture['otherGroup'], 'Instruktur Lain');

    $this->actingAs($fixture['instructorUser'])
        ->get(route('rekap-kehadiran-sik.index'))
        ->assertOk()
        ->assertSee($memberA->santri->nama)
        ->assertSee($memberB->santri->nama)
        ->assertSee($fixture['groupA']->nama_kelompok)
        ->assertSee($fixture['groupB']->nama_kelompok)
        ->assertDontSee($otherMember->santri->nama)
        ->assertDontSee($fixture['otherGroup']->nama_kelompok);
});

test('detail rekap menghitung ulang angka dan mengabaikan baris cache yang sudah usang', function () {
    $statuses = array_merge(array_fill(0, 14, 'Hadir'), ['Izin', 'Sakit']);
    $fixture = makeAcceptanceAttendanceFixture($statuses);
    $staleRecap = RekapKehadiranSik::query()->create([
        'anggota_periode_sik_id' => $fixture['member']->id,
        'kegiatan_id' => $fixture['activity']->id,
        'total_pertemuan' => 20,
        'jumlah_hadir' => 20,
        'jumlah_izin' => 0,
        'jumlah_sakit' => 0,
        'jumlah_alpha' => 0,
        'jumlah_tidak_lengkap' => 0,
        'persentase_kehadiran' => 100,
        'status_kehadiran' => 'Memenuhi',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('rekap-kehadiran-sik.show', $staleRecap))
        ->assertOk()
        ->assertSee('87,5%')
        ->assertSee('Memenuhi')
        ->assertViewHas('item', fn (RekapKehadiranSik $item): bool => $item->total_pertemuan === 16
            && $item->jumlah_hadir === 14
            && $item->jumlah_izin === 1
            && $item->jumlah_sakit === 1);

    $this->assertDatabaseHas('rekap_kehadiran_sik', [
        'id' => $staleRecap->id,
        'total_pertemuan' => 20,
        'jumlah_hadir' => 20,
        'persentase_kehadiran' => 100,
        'status_kehadiran' => 'Memenuhi',
    ]);
});

test('pertemuan Terjadwal tetap tampil sebagai riwayat tetapi tidak menambah total Terlaksana', function () {
    $fixture = makeAcceptanceAttendanceFixture(['Hadir']);
    $scheduled = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => now()->startOfYear()->addDay()->toDateString(),
        'status' => 'Terjadwal',
    ]);
    AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $fixture['member']->id,
        'pertemuan_id' => $scheduled->id,
        'metode_absensi' => 'Manual',
        'status' => 'Alpha',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('rekap-kehadiran-sik.detail', [
            'anggota_periode_sik_id' => $fixture['member']->id,
            'kegiatan_id' => $fixture['activity']->id,
        ]))
        ->assertOk()
        ->assertSee('Terjadwal')
        ->assertSee('Alpha')
        ->assertViewHas('item', fn (RekapKehadiranSik $item): bool => $item->total_pertemuan === 1
            && $item->jumlah_alpha === 0);
});

test('filter periode dan kelompok terbawa pada rekap, export, dan tautan kembali detail', function () {
    $fixture = makeAcceptanceBaseFixture();
    $memberA = createAcceptanceMember($fixture, $fixture['groupA'], 'Filter A');
    $memberB = createAcceptanceMember($fixture, $fixture['groupB'], 'Filter B');
    $filters = [
        'periode_sik_id' => $fixture['period']->id,
        'kelompok_id' => $fixture['groupB']->id,
    ];

    $indexResponse = $this->actingAs($fixture['admin'])
        ->get(route('rekap-kehadiran-sik.index', $filters))
        ->assertOk()
        ->assertSee($memberB->santri->nama)
        ->assertDontSee($memberA->santri->nama);

    expect($indexResponse->getContent())
        ->toContain(e(route('rekap-kehadiran-sik.export', $filters)))
        ->toContain(e(route('rekap-kehadiran-sik.detail', [
            'anggota_periode_sik_id' => $memberB->id,
            'periode_sik_id' => $fixture['period']->id,
            'kelompok_id' => $fixture['groupB']->id,
        ])));

    $csv = $this->get(route('rekap-kehadiran-sik.export', $filters))->streamedContent();
    expect($csv)
        ->toContain($memberB->santri->nama)
        ->not->toContain($memberA->santri->nama);

    $this->get(route('rekap-kehadiran-sik.detail', [
        'anggota_periode_sik_id' => $memberB->id,
        ...$filters,
    ]))
        ->assertOk()
        ->assertSee(e(route('rekap-kehadiran-sik.index', $filters)), false);
});

test('ringkasan, tabel, dan export memakai format persentase yang sama', function () {
    $fixture = makeAcceptanceBaseFixture();
    $statuses = array_merge(array_fill(0, 14, 'Hadir'), ['Izin', 'Sakit']);
    $firstMember = createAcceptanceMember($fixture, $fixture['groupA'], 'Persentase 1');
    $secondMember = createAcceptanceMember($fixture, $fixture['groupA'], 'Persentase 2');
    createAcceptanceAttendance($firstMember, createAcceptanceActivity($fixture, $fixture['groupA']), $statuses);
    createAcceptanceAttendance($secondMember, createAcceptanceActivity($fixture, $fixture['groupA']), $statuses);

    $filters = ['kelompok_id' => $fixture['groupA']->id];
    $indexResponse = $this->actingAs($fixture['admin'])
        ->get(route('rekap-kehadiran-sik.index', $filters))
        ->assertOk();

    expect(substr_count($indexResponse->getContent(), '87,5%'))->toBeGreaterThanOrEqual(3);

    $csv = $this->get(route('rekap-kehadiran-sik.export', $filters))->streamedContent();
    expect(substr_count($csv, '87,5%'))->toBe(2);
});

test('status evaluasi konsisten di dashboard santri, dashboard admin, dan laporan tanpa mengubah catatan lama', function () {
    $fixture = makeAcceptanceAttendanceFixture(array_merge(array_fill(0, 13, 'Hadir'), array_fill(0, 3, 'Alpha')));
    $santriUser = User::factory()->create(['role' => UserRole::Santri]);
    $fixture['member']->santri->update(['user_id' => $santriUser->id]);
    $kaLab = User::factory()->create(['role' => UserRole::KaLabMutu]);

    $kelulusan = KelulusanSik::query()->create([
        'anggota_periode_sik_id' => $fixture['member']->id,
        'status' => 'Lulus',
        'tanggal_kelulusan' => '2026-11-01',
        'keterangan' => 'Catatan lama yang dipertahankan.',
    ]);
    $sertifikat = SertifikatSik::query()->create([
        'kelulusan_sik_id' => $kelulusan->id,
        'nomor_sertifikat' => 'ACC-LAMA-001',
        'file_sertifikat' => 'sertifikat-sik/acc-lama.pdf',
        'tanggal_terbit' => '2026-11-01',
    ]);

    $this->actingAs($santriUser)
        ->get(route('dashboard.santri'))
        ->assertOk()
        ->assertSee('Belum memenuhi ambang kehadiran')
        ->assertSee('81,25%')
        ->assertSee('Catatan tersimpan: Lulus')
        ->assertSee('Berkas lama tetap tersedia');

    $this->actingAs($fixture['admin'])
        ->get(route('dashboard.admin'))
        ->assertOk()
        ->assertViewHas('adminStats', fn (array $stats): bool => $stats['mahasiswaLulus'] === 0);

    $this->actingAs($kaLab)
        ->get(route('laporan-sik'))
        ->assertOk()
        ->assertSee('Belum memenuhi ambang kehadiran')
        ->assertSee('81,25%')
        ->assertSee('Belum Ditetapkan');

    $this->assertDatabaseHas('kelulusan_sik', [
        'id' => $kelulusan->id,
        'status' => 'Lulus',
        'keterangan' => 'Catatan lama yang dipertahankan.',
    ]);
    $this->assertDatabaseHas('sertifikat_sik', [
        'id' => $sertifikat->id,
        'nomor_sertifikat' => 'ACC-LAMA-001',
        'file_sertifikat' => 'sertifikat-sik/acc-lama.pdf',
    ]);
});

test('santri ditolak dari rekap dan instruktur tidak dapat membuka data kelompok instruktur lain lewat filter', function () {
    $fixture = makeAcceptanceBaseFixture();
    $member = createAcceptanceMember($fixture, $fixture['groupA'], 'Akses');
    $otherMember = createAcceptanceMember($fixture, $fixture['otherGroup'], 'Akses Lain');
    $santriUser = User::factory()->create(['role' => UserRole::Santri]);

    $this->actingAs($santriUser)
        ->get(route('rekap-kehadiran-sik.index'))
        ->assertForbidden();

    $this->actingAs($santriUser)
        ->get(route('rekap-kehadiran-sik.export'))
        ->assertForbidden();

    $this->actingAs($fixture['instructorUser'])
        ->get(route('rekap-kehadiran-sik.index', [
            'instruktur_id' => $fixture['otherInstructor']->id,
        ]))
        ->assertOk()
        ->assertSee($member->santri->nama)
        ->assertDontSee($otherMember->santri->nama);

    $csv = $this->get(route('rekap-kehadiran-sik.export'))->streamedContent();
    expect($csv)
        ->toContain($member->santri->nama)
        ->not->toContain($otherMember->santri->nama);
});
