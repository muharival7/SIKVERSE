<?php

use App\Enums\UserRole;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Santri;
use App\Models\User;

function createLatestMembershipFixture(): array
{
    $suffix = fake()->unique()->numerify('######');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $santri = Santri::query()->create([
        'nomor_induk' => 'LMB-'.$suffix,
        'nama' => 'Santri Keanggotaan '.$suffix,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);

    $createPeriode = function (string $nama) use ($suffix): PeriodeSik {
        return PeriodeSik::query()->create([
            'nama_periode' => $nama.' '.$suffix,
            'tahun' => 2026,
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'status' => 'Aktif',
        ]);
    };

    $instruktur = InfrastrukturSik::query()->create([
        'user_id' => User::factory()->create(['role' => UserRole::InfrastrukturSik])->id,
        'nama' => 'Instruktur Keanggotaan '.$suffix,
        'status' => 'Aktif',
    ]);
    $periodeKelompok = $createPeriode('Periode Kelompok');
    $kelompok = Kelompok::query()->create([
        'periode_sik_id' => $periodeKelompok->id,
        'instruktur_id' => $instruktur->id,
        'nama_kelompok' => 'Kelompok Keanggotaan '.$suffix,
        'status' => 'Aktif',
    ]);

    $periodeBaru = $createPeriode('Periode Baru');
    $periodeLama = $createPeriode('Periode Lama');
    $periodeKosong = $createPeriode('Periode Tanpa Tanggal');

    $baru = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $periodeBaru->id,
        'kelompok_id' => $kelompok->id,
        'tanggal_masuk' => '2026-06-01',
        'status' => 'Aktif',
    ]);
    $lama = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $periodeLama->id,
        'kelompok_id' => $kelompok->id,
        'tanggal_masuk' => '2025-06-01',
        'status' => 'Aktif',
    ]);
    $kosong = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri->id,
        'periode_sik_id' => $periodeKosong->id,
        'kelompok_id' => $kelompok->id,
        'tanggal_masuk' => null,
        'status' => 'Aktif',
    ]);

    return compact('admin', 'santri', 'baru', 'lama', 'kosong', 'periodeBaru', 'periodeLama', 'periodeKosong');
}

test('keanggotaan terbaru memakai tanggal masuk terbesar sebelum id', function () {
    $fixture = createLatestMembershipFixture();

    expect($fixture['santri']->anggotaPeriodeTerbaru->id)->toBe($fixture['baru']->id);
});

test('keanggotaan terbaru fallback ke id terbesar saat tanggal masuk kosong', function () {
    $fixture = createLatestMembershipFixture();

    foreach ([$fixture['baru'], $fixture['lama'], $fixture['kosong']] as $membership) {
        $membership->update(['tanggal_masuk' => null]);
    }

    expect($fixture['santri']->fresh()->anggotaPeriodeTerbaru->id)->toBe($fixture['kosong']->id);
});

test('halaman detail santri mengurutkan keanggotaan memakai tanggal masuk lalu id', function () {
    $fixture = createLatestMembershipFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('santris.show', $fixture['santri']))
        ->assertOk()
        ->assertSeeInOrder([
            $fixture['periodeBaru']->nama_periode,
            $fixture['periodeLama']->nama_periode,
            $fixture['periodeKosong']->nama_periode,
        ]);
});
