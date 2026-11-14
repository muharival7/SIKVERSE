<?php

use App\Enums\UserRole;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\RekapKehadiranSik;
use App\Models\Santri;
use App\Models\User;

test('admin can export the attendance recap for the selected period as an Excel-compatible CSV', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Uji',
        'nip' => 'NIP-EXPORT-001',
        'program_studi' => 'Sistem Informasi',
        'email' => $instructorUser->email,
        'status' => 'Aktif',
    ]);

    $periods = [];
    foreach ([['ganjil_2026', 2026, 'Santri Ganjil'], ['genap_2027', 2027, 'Santri Genap']] as [$periodName, $year, $studentName]) {
        $period = PeriodeSik::query()->create([
            'nama_periode' => $periodName,
            'tahun' => $year,
            'tanggal_mulai' => "{$year}-01-01",
            'tanggal_selesai' => "{$year}-06-30",
            'status' => 'Aktif',
        ]);
        $periods[] = $period;

        $group = Kelompok::query()->create([
            'periode_sik_id' => $period->id,
            'instruktur_id' => $instructor->id,
            'nama_kelompok' => 'Teknologi',
            'status' => 'Aktif',
        ]);
        $santri = Santri::query()->create([
            'nomor_induk' => $periodName,
            'nama' => $studentName,
            'jenis_kelamin' => 'Perempuan',
            'status' => 'Aktif',
        ]);
        $membership = AnggotaPeriodeSik::query()->create([
            'santri_id' => $santri->id,
            'periode_sik_id' => $period->id,
            'kelompok_id' => $group->id,
            'status' => 'Aktif',
        ]);
        $activity = Kegiatan::query()->create([
            'periode_sik_id' => $period->id,
            'kelompok_id' => $group->id,
            'nama_kegiatan' => 'Kegiatan '.$periodName,
            'jenis_kegiatan' => 'Kelas',
            'status' => 'Aktif',
        ]);
        RekapKehadiranSik::query()->create([
            'anggota_periode_sik_id' => $membership->id,
            'kegiatan_id' => $activity->id,
            'total_pertemuan' => 16,
            'jumlah_hadir' => 14,
            'jumlah_izin' => 1,
            'jumlah_sakit' => 1,
            'jumlah_alpha' => 0,
            'jumlah_tidak_lengkap' => 0,
            'persentase_kehadiran' => 87.5,
            'status_kehadiran' => 'Memenuhi',
        ]);
    }

    $query = ['periode_sik_id' => $periods[0]->id];

    $this->actingAs($admin)
        ->get(route('rekap-kehadiran-sik.index', $query))
        ->assertOk()
        ->assertSee('Santri Ganjil')
        ->assertDontSee('Santri Genap');

    $response = $this->get(route('rekap-kehadiran-sik.export', $query));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())
        ->toContain('ganjil_2026')
        ->toContain('Santri Ganjil')
        ->not->toContain('genap_2027')
        ->not->toContain('Santri Genap');
});

test('santri cannot access the attendance recap export', function () {
    $santri = User::factory()->create(['role' => UserRole::Santri]);

    $this->actingAs($santri)
        ->get(route('rekap-kehadiran-sik.export'))
        ->assertForbidden();
});
