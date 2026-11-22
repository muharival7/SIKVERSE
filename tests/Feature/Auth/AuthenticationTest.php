<?php

use App\Enums\UserRole;
use App\Models\InfrastrukturSik;
use App\Models\Santri;
use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('name="login"', false)
        ->assertSee('class="sik-login-control-wrap"', false)
        ->assertSee('data-password-toggle', false)
        ->assertSee('Masukkan username')
        ->assertDontSee('name="role"', false);
});

test('invalid credentials show the login error below the input and mark the field invalid', function () {
    $user = User::factory()->create();

    $this->followingRedirects()->from('/login')->post('/login', [
        'login' => $user->email,
        'password' => 'wrong-password',
    ])->assertOk()
        ->assertSee('These credentials do not match our records.')
        ->assertSee('class="sik-login-control is-invalid"', false)
        ->assertSee('id="login-error" role="alert"', false)
        ->assertSee('data-password-toggle', false);

    $this->assertGuest();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

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

test('inactive santri cannot sign in with their student number', function () {
    $user = User::factory()->create(['role' => UserRole::Santri]);
    Santri::create([
        'user_id' => $user->id,
        'nomor_induk' => 'SIK-2026-002',
        'nama' => $user->name,
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Tidak Aktif',
    ]);

    $this->from('/login')->post('/login', [
        'login' => 'SIK-2026-002',
        'password' => 'password',
    ])->assertRedirect('/login')->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('inactive infrastructure accounts can sign in with read-only access', function () {
    $user = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    InfrastrukturSik::create([
        'user_id' => $user->id,
        'nama' => $user->name,
        'status' => 'Tidak Aktif',
    ]);

    $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard.infrastruktur_sik', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('unverified synthetic accounts remain unverified when local email verification is disabled', function () {
    config()->set('sik.auth.require_email_verification', false);

    $user = User::factory()->unverified()->create([
        'email' => 'instruktur@sik.test',
        'role' => UserRole::InfrastrukturSik,
    ]);

    expect($user->hasVerifiedEmail())->toBeFalse();
});

test('users can logout and are returned to the login page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('login', absolute: false));
});

test('guests are redirected to login when opening a protected dashboard', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login', absolute: false));

    $this->get(route('dashboard.admin'))
        ->assertRedirect(route('login', absolute: false));
});

test('a logged out user cannot reopen a protected page by revisiting its URL', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)->get(route('dashboard.admin'))->assertOk();

    $this->post('/logout')->assertRedirect(route('login', absolute: false));

    $this->get(route('dashboard.admin'))
        ->assertRedirect(route('login', absolute: false));
    $this->assertGuest();
});

test('authenticated pages are sent with cache headers that prevent browser back navigation', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($user)->get(route('dashboard.admin'));

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('every role dashboard has one fixed logout control that ends the session', function (UserRole $role, string $dashboardRoute) {
    $user = User::factory()->create(['role' => $role]);

    if ($role === UserRole::Santri) {
        Santri::query()->create([
            'user_id' => $user->id,
            'nomor_induk' => 'SIK-LOGOUT-'.fake()->unique()->randomNumber(5),
            'nama' => $user->name,
            'jenis_kelamin' => 'Laki-laki',
            'status' => 'Aktif',
        ]);
    }

    $response = $this->actingAs($user)
        ->get(route($dashboardRoute))
        ->assertOk()
        ->assertSee('sik-fixed-logout', false)
        ->assertSee('action="'.route('logout').'"', false)
        ->assertSee('method="POST"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('<span>Keluar</span>', false);

    expect(substr_count($response->getContent(), 'action="'.route('logout').'"'))->toBe(1)
        ->and(substr_count($response->getContent(), '<span>Keluar</span>'))->toBe(1);

    $this->post(route('logout'))
        ->assertRedirect(route('login', absolute: false));

    $this->assertGuest();
})->with([
    'admin' => [UserRole::Admin, 'dashboard.admin'],
    'santri' => [UserRole::Santri, 'dashboard.santri'],
    'instruktur' => [UserRole::InfrastrukturSik, 'dashboard.infrastruktur_sik'],
    'ka-lab mutu' => [UserRole::KaLabMutu, 'dashboard.ka_lab_mutu'],
]);
