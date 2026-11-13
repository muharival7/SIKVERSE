<?php

use App\Enums\UserRole;
use App\Models\InfrastrukturSik;
use App\Models\Santri;
use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('name="login"', false)
        ->assertDontSee('name="role"', false);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route($user->role->dashboardRoute(), absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('login role comes from the account even if a stale role field is submitted', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->post('/login', [
        'login' => $user->email,
        'role' => UserRole::Santri->value,
        'password' => 'password',
    ])->assertRedirect(route('dashboard.admin', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('santri can sign in with their student number', function () {
    $user = User::factory()->create(['role' => UserRole::Santri]);
    Santri::create([
        'user_id' => $user->id,
        'nomor_induk' => 'SIK-2026-001',
        'nama' => $user->name,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);

    $this->post('/login', [
        'login' => 'SIK-2026-001',
        'password' => 'password',
    ])->assertRedirect(route('dashboard.santri', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('inactive infrastructure accounts cannot sign in', function () {
    $user = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    InfrastrukturSik::create([
        'user_id' => $user->id,
        'nama' => $user->name,
        'status' => 'Tidak Aktif',
    ]);

    $this->from('/login')->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/login')->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('synthetic sik test accounts do not need a deliverable verification email in local tests', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'instruktur@sik.test',
        'role' => UserRole::InfrastrukturSik,
    ]);

    expect($user->hasVerifiedEmail())->toBeTrue();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
