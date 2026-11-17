<?php

use App\Enums\UserRole;
use App\Models\InfrastrukturSik;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\User;

function createDosenKelompokColumnFixture(): array
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $period = PeriodeSik::query()->create([
        'nama_periode' => 'ganjil_kelompok_2026',
        'tahun' => 2026,
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);

    $singleUser = User::factory()->create([
        'name' => 'Dosen Satu Kelompok',
        'role' => UserRole::InfrastrukturSik,
    ]);
    $singleProfile = InfrastrukturSik::query()->create([
        'user_id' => $singleUser->id,
        'nama' => $singleUser->name,
        'nip' => 'NIP-KEL-001',
        'program_studi' => 'Program Studi Lama',
        'status' => 'Aktif',
    ]);

    $multipleUser = User::factory()->create([
        'name' => 'Dosen Banyak Kelompok',
        'role' => UserRole::InfrastrukturSik,
    ]);
    $multipleProfile = InfrastrukturSik::query()->create([
        'user_id' => $multipleUser->id,
        'nama' => $multipleUser->name,
        'nip' => 'NIP-KEL-002',
        'program_studi' => 'Program Studi Lain',
        'status' => 'Aktif',
    ]);

    $withoutGroupUser = User::factory()->create([
        'name' => 'Dosen Tanpa Kelompok',
        'role' => UserRole::InfrastrukturSik,
    ]);
    InfrastrukturSik::query()->create([
        'user_id' => $withoutGroupUser->id,
        'nama' => $withoutGroupUser->name,
        'nip' => 'NIP-KEL-003',
        'status' => 'Aktif',
    ]);

    Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $singleProfile->id,
        'nama_kelompok' => 'Kelompok Tunggal',
        'status' => 'Aktif',
    ]);

    foreach (['Kelompok Unik A', 'Kelompok Unik B'] as $namaKelompok) {
        Kelompok::query()->create([
            'periode_sik_id' => $period->id,
            'instruktur_id' => $multipleProfile->id,
            'nama_kelompok' => $namaKelompok,
            'status' => 'Aktif',
        ]);
    }

    return compact('admin', 'withoutGroupUser');
}

test('an admin can create a staff account with a matching role profile', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Instruktur Baru',
            'email' => 'instruktur@example.test',
            'nip' => 'NIP-TEST-002',
            'program_studi' => 'Sistem Informasi',
            'role' => UserRole::InfrastrukturSik->value,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('account_created');

    $this->assertDatabaseHas('users', [
        'name' => 'Instruktur Baru',
        'email' => 'instruktur@example.test',
        'role' => UserRole::InfrastrukturSik->value,
        'must_change_password' => true,
    ]);
    $this->assertDatabaseHas('infrastruktur_siks', [
        'nama' => 'Instruktur Baru',
        'email' => 'instruktur@example.test',
        'status' => 'Aktif',
    ]);
});

test('an admin can deactivate an instructor and the instructor loses role access', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructor = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    InfrastrukturSik::create([
        'user_id' => $instructor->id,
        'nama' => $instructor->name,
        'status' => 'Aktif',
    ]);

    $this->actingAs($admin)
        ->put(route('users.update', $instructor), [
            'name' => $instructor->name,
            'email' => $instructor->email,
            'status' => 'Tidak Aktif',
        ])
        ->assertRedirect(route('users.index'));

    $this->actingAs($instructor)
        ->get(route('dashboard.infrastruktur_sik'))
        ->assertForbidden();
});

test('non-admin users cannot manage staff accounts', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertForbidden();
})->with([
    'santri' => [UserRole::Santri],
    'infrastruktur SIK' => [UserRole::InfrastrukturSik],
    'Ka-Lab Mutu' => [UserRole::KaLabMutu],
]);

test('data dosen renders kelompok from the instructor relation without using program studi', function () {
    $fixture = createDosenKelompokColumnFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee('<th>Kelompok</th>', false)
        ->assertSee('Kelompok Tunggal')
        ->assertSee('Kelompok Unik A')
        ->assertSee('Kelompok Unik B')
        ->assertSee('Belum memiliki kelompok')
        ->assertDontSee('Program Studi Lama')
        ->assertDontSee('Program Studi Lain')
        ->assertSee('Detail Riwayat')
        ->assertSee('Edit')
        ->assertSee('Hapus');
});

test('searching data dosen matches kelompok names from the instructor relation', function () {
    $fixture = createDosenKelompokColumnFixture();

    $this->actingAs($fixture['admin'])
        ->get(route('users.index', ['search' => 'Kelompok Unik B']))
        ->assertOk()
        ->assertSee('Dosen Banyak Kelompok')
        ->assertDontSee('Dosen Satu Kelompok')
        ->assertDontSee('Dosen Tanpa Kelompok');
});
