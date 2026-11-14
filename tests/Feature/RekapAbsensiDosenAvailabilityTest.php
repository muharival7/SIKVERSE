<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('admin pages remain usable when the optional dosen attendance table is missing', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Schema::dropIfExists('absensi_dosen');

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee('Tabel rekap absensi dosen belum tersedia pada database ini');

    $this->get(route('rekap-absensi-dosen.index'))
        ->assertOk()
        ->assertSee('Tabel absensi dosen belum tersedia pada database ini');

    $this->from(route('users.index'))
        ->post(route('rekap-absensi-dosen.store'))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('error');
});
