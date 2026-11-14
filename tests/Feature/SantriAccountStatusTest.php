<?php

use App\Enums\UserRole;
use App\Models\Santri;
use App\Models\User;

test('deactivating a santri blocks future login and protected santri pages', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $santriUser = User::factory()->create(['role' => UserRole::Santri]);
    $santri = Santri::create([
        'user_id' => $santriUser->id,
        'nomor_induk' => 'SIK-2026-003',
        'nama' => $santriUser->name,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);

    $this->actingAs($admin)
        ->put(route('santris.update', $santri), [
            'nomor_induk' => $santri->nomor_induk,
            'nama' => $santri->nama,
            'email' => $santriUser->email,
            'jenis_kelamin' => $santri->jenis_kelamin,
            'program_studi' => '',
            'status' => 'Tidak Aktif',
        ])
        ->assertRedirect(route('santris.index'));

    $this->assertDatabaseHas('santris', [
        'id' => $santri->id,
        'user_id' => $santriUser->id,
        'status' => 'Tidak Aktif',
    ]);

    $this->actingAs($santriUser)
        ->get(route('dashboard.santri'))
        ->assertForbidden();

    auth()->logout();

    $this->from('/login')->post('/login', [
        'login' => $santri->nomor_induk,
        'password' => 'password',
    ])->assertRedirect('/login')->assertSessionHasErrors('login');

    $this->assertGuest();
});
