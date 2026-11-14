<?php

use App\Enums\UserRole;
use App\Models\InfrastrukturSik;
use App\Models\User;

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
