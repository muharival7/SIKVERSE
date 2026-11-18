<?php

use App\Enums\UserRole;
use App\Models\AbsensiDosen;
use App\Models\InfrastrukturSik;
use App\Models\PeriodeSik;
use App\Models\User;

function createDosenAttendanceFixture(): array
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Dosen Rekap Satu',
        'nip' => 'NIP-REKAP-001',
        'program_studi' => 'Sistem Informasi',
        'email' => $instructorUser->email,
        'status' => 'Aktif',
    ]);
    $otherUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $otherInstructor = InfrastrukturSik::query()->create([
        'user_id' => $otherUser->id,
        'nama' => 'Dosen Rekap Dua',
        'nip' => 'NIP-REKAP-002',
        'email' => $otherUser->email,
        'status' => 'Aktif',
    ]);
    $period = PeriodeSik::query()->create([
        'nama_periode' => 'ganjil_2026_rekap',
        'tahun' => 2026,
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);

    return compact('admin', 'instructorUser', 'instructor', 'otherInstructor', 'period');
}

function createDosenAttendance(InfrastrukturSik $instructor, User $recorder, string $date, string $status, ?string $start = null, ?string $end = null): AbsensiDosen
{
    return AbsensiDosen::query()->create([
        'infrastruktur_sik_id' => $instructor->id,
        'tanggal' => $date,
        'waktu_masuk' => $start,
        'waktu_pulang' => $end,
        'status' => $status,
        'dicatat_oleh' => $recorder->id,
    ]);
}

test('admin can filter the database recap by period and instructor with a correct attendance percentage', function () {
    $fixture = createDosenAttendanceFixture();
    createDosenAttendance($fixture['instructor'], $fixture['admin'], '2026-10-01', 'Hadir', '08:00', '16:00');
    createDosenAttendance($fixture['instructor'], $fixture['admin'], '2026-10-08', 'Alpha');
    createDosenAttendance($fixture['instructor'], $fixture['admin'], '2026-08-20', 'Hadir');
    createDosenAttendance($fixture['otherInstructor'], $fixture['admin'], '2026-10-01', 'Hadir');

    $response = $this->actingAs($fixture['admin'])
        ->get(route('rekap-absensi-dosen.index', [
            'periode_sik_id' => $fixture['period']->id,
            'infrastruktur_sik_id' => $fixture['instructor']->id,
        ]));

    $response->assertOk()
        ->assertSee('Dosen Rekap Satu')
        ->assertDontSee('<td class="fw-semibold">Dosen Rekap Dua</td>', false)
        ->assertSee('50.0%')
        ->assertSee('08:00')
        ->assertSee('16:00');
});

test('admin can view an instructor history and export the filtered database rows', function () {
    $fixture = createDosenAttendanceFixture();
    createDosenAttendance($fixture['instructor'], $fixture['admin'], '2026-10-01', 'Hadir', '08:15', '15:45');

    $this->actingAs($fixture['admin'])
        ->get(route('rekap-absensi-dosen.show', [
            'infrastrukturSik' => $fixture['instructor']->id,
            'periode_sik_id' => $fixture['period']->id,
        ]))
        ->assertOk()
        ->assertSee('Detail riwayat kehadiran')
        ->assertSee('08:15')
        ->assertSee('15:45')
        ->assertSee('100.0%');

    $response = $this->get(route('rekap-absensi-dosen.export', [
        'periode_sik_id' => $fixture['period']->id,
        'infrastruktur_sik_id' => $fixture['instructor']->id,
    ]));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())
        ->toContain('Dosen Rekap Satu')
        ->toContain('NIP-REKAP-001')
        ->toContain('08:15')
        ->not->toContain('Dosen Rekap Dua');
});

test('empty instructor recap shows zero counts and a clear no-data message', function () {
    $fixture = createDosenAttendanceFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('rekap-absensi-dosen.index'))
        ->assertOk()
        ->assertSee('Dosen Rekap Satu')
        ->assertSee('Belum ada data kehadiran dosen untuk filter ini.')
        ->assertSee('0.0%');
});

test('the admin data-dosen page keeps attendance data without the header menu and exposes detail buttons', function () {
    $fixture = createDosenAttendanceFixture();
    createDosenAttendance($fixture['instructor'], $fixture['admin'], '2026-10-01', 'Hadir');

    $response = $this->actingAs($fixture['admin'])
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee('Rekap Kehadiran Dosen')
        ->assertSee('1 dari 1 catatan hadir')
        ->assertSee('Detail Riwayat');

    expect($response->getContent())->toContain('href="'.route('rekap-absensi-dosen.index').'"');

    $this->actingAs($fixture['instructorUser'])
        ->get(route('rekap-absensi-dosen.index'))
        ->assertForbidden();
});
