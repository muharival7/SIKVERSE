<?php

use App\Enums\UserRole;
use App\Models\Santri;
use App\Models\User;

test('admin can open consistent santri create and edit forms', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $santri = Santri::query()->create([
        'nomor_induk' => 'SIK-FORM-001',
        'nama' => 'Santri Uji',
        'jenis_kelamin' => 'Perempuan',
        'status' => 'Aktif',
    ]);

    $this->actingAs($admin)
        ->get(route('santris.create'))
        ->assertOk()
        ->assertSee('Formulir Identitas Santri')
        ->assertSee('Nomor Induk / NIM');

    $this->get(route('santris.edit', $santri))
        ->assertOk()
        ->assertSee('Edit Data Santri')
        ->assertSee('Santri Uji')
        ->assertDontSee('Tempat Lahir')
        ->assertDontSee('Tanggal Lahir');
});
