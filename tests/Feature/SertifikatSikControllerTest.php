<?php

use App\Enums\UserRole;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kelompok;
use App\Models\KelulusanSik;
use App\Models\PeriodeSik;
use App\Models\Santri;
use App\Models\SertifikatSik;
use App\Models\User;

function createSertifikatPresentationFixture(): array
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Sertifikat',
        'status' => 'Aktif',
    ]);
    $period = PeriodeSik::query()->create([
        'nama_periode' => 'ganjil_sertifikat_2026',
        'tahun' => 2026,
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $group = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Kelompok Sertifikat',
        'status' => 'Aktif',
    ]);
    $santri = Santri::query()->create([
        'nomor_induk' => 'SIK-SERT-001',
        'nama' => 'Santri Sertifikat',
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $membership = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $period->id,
        'kelompok_id' => $group->id,
        'status' => 'Aktif',
    ]);
    $graduation = KelulusanSik::query()->create([
        'anggota_periode_sik_id' => $membership->id,
        'status' => 'Lulus',
    ]);
    $certificate = SertifikatSik::query()->create([
        'kelulusan_sik_id' => $graduation->id,
        'nomor_sertifikat' => 'SRT-TEST-001',
        'file_sertifikat' => 'sertifikat-sik/test.pdf',
        'tanggal_terbit' => '2026-11-01',
    ]);

    return compact('admin', 'certificate');
}

test('sertifikat management index uses the spaced page and table layout', function () {
    $fixture = createSertifikatPresentationFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('sertifikat-sik.index'))
        ->assertOk()
        ->assertSee('sertifikat-page', false)
        ->assertSee('sertifikat-page-header', false)
        ->assertSee('sertifikat-table-card', false)
        ->assertSee('+ Terbitkan Sertifikat')
        ->assertSee('SRT-TEST-001')
        ->assertSee('Santri Sertifikat');
});

test('sertifikat create and edit pages use the consistent form layout', function () {
    $fixture = createSertifikatPresentationFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('sertifikat-sik.create'))
        ->assertOk()
        ->assertSee('sertifikat-form-shell', false)
        ->assertSee('sertifikat-form-actions', false)
        ->assertSee('Terbitkan Sertifikat');

    $this->actingAs($fixture['admin'])
        ->get(route('sertifikat-sik.edit', $fixture['certificate']))
        ->assertOk()
        ->assertSee('sertifikat-form-shell', false)
        ->assertSee('sertifikat-form-actions', false)
        ->assertSee('Edit Sertifikat');
});

test('admin dashboard certificate card separates the count, description, and action', function () {
    $fixture = createSertifikatPresentationFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('dashboard.admin'))
        ->assertOk()
        ->assertSee('admin-certificate-panel', false)
        ->assertSee('admin-certificate-count-value', false)
        ->assertSee('admin-certificate-description', false)
        ->assertSee('Buka Pengelolaan Sertifikat');
});
