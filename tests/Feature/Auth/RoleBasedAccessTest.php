<?php

use App\Enums\UserRole;
use App\Models\Santri;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

function makeRoleDashboardUser(UserRole $role): User
{
    $user = User::factory()->create(['role' => $role]);

    if ($role === UserRole::Santri) {
        Santri::query()->create([
            'user_id' => $user->id,
            'nomor_induk' => 'TEST-'.fake()->unique()->numerify('########'),
            'nama' => $user->name,
            'jenis_kelamin' => 'Laki-laki',
            'program_studi' => 'Sistem Informasi',
            'status' => 'Aktif',
        ]);
    }

    return $user;
}

test('dashboard entry redirects each user to the dashboard for their role', function (UserRole $role) {
    $user = makeRoleDashboardUser($role);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($role->dashboardRoute()));
})->with([
    'admin' => [UserRole::Admin],
    'santri' => [UserRole::Santri],
    'infrastruktur SIK' => [UserRole::InfrastrukturSik],
    'Ka-Lab Mutu' => [UserRole::KaLabMutu],
]);

test('each user can open the dashboard assigned to their role', function (UserRole $role) {
    $user = makeRoleDashboardUser($role);

    $this->actingAs($user)
        ->get(route($role->dashboardRoute()))
        ->assertOk()
        ->assertSee($role->label());
})->with([
    'admin' => [UserRole::Admin],
    'santri' => [UserRole::Santri],
    'infrastruktur SIK' => [UserRole::InfrastrukturSik],
    'Ka-Lab Mutu' => [UserRole::KaLabMutu],
]);

test('users without the admin role cannot open the admin dashboard', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('dashboard.admin'))
        ->assertForbidden();
})->with([
    'santri' => [UserRole::Santri],
    'infrastruktur SIK' => [UserRole::InfrastrukturSik],
    'Ka-Lab Mutu' => [UserRole::KaLabMutu],
]);

test('public registration always creates a santri account', function () {
    config()->set('sik.auth.require_email_verification', false);

    $this->post('/register', [
        'name' => 'Santri Baru',
        'email' => 'santri@example.test',
        'nomor_induk' => '900002',
        'program_studi' => 'Sistem Informasi',
        'jenis_kelamin' => 'Laki-laki',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => UserRole::Admin->value,
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => 'santri@example.test',
        'role' => UserRole::Santri->value,
    ]);
});

test('users cannot open dashboards assigned to other roles', function () {
    foreach (UserRole::cases() as $userRole) {
        $user = makeRoleDashboardUser($userRole);

        foreach (UserRole::cases() as $dashboardRole) {
            if ($dashboardRole === $userRole) {
                continue;
            }

            $this->actingAs($user)
                ->get(route($dashboardRole->dashboardRoute()))
                ->assertForbidden();
        }
    }
});

test('login redirects every role to its own dashboard', function () {
    foreach (UserRole::cases() as $role) {
        $user = makeRoleDashboardUser($role);

        $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route($role->dashboardRoute(), absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
    }
});

test('database seeding creates the configured administrator without duplicating it', function () {
    config([
        'sik.bootstrap_admin.name' => 'Admin SIK',
        'sik.bootstrap_admin.email' => 'admin@example.test',
        'sik.bootstrap_admin.password' => 'Strong-Test-Password-2026',
    ]);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseHas('users', [
        'name' => 'Admin SIK',
        'email' => 'admin@example.test',
        'role' => UserRole::Admin->value,
    ]);
    $this->assertDatabaseCount('users', 1);
});

test('database seeding refuses to promote an existing non-admin account', function () {
    User::factory()->create([
        'email' => 'admin@example.test',
        'role' => UserRole::Santri,
    ]);

    config([
        'sik.bootstrap_admin.name' => 'Admin SIK',
        'sik.bootstrap_admin.email' => 'admin@example.test',
        'sik.bootstrap_admin.password' => 'Strong-Test-Password-2026',
    ]);

    expect(fn () => app(DatabaseSeeder::class)->run())
        ->toThrow(RuntimeException::class);

    $this->assertDatabaseHas('users', [
        'email' => 'admin@example.test',
        'role' => UserRole::Santri->value,
    ]);
});

function makeUnknownRoleUser(): User
{
    $user = User::factory()->create();

    DB::table('users')
        ->where('id', $user->id)
        ->update(['role' => 'unknown_role']);

    return $user->fresh();
}

test('login refuses an account whose role is not recognised', function () {
    $user = makeUnknownRoleUser();

    $this->from('/login')->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/login')
        ->assertSessionHasErrors(['login' => 'Peran pengguna tidak dikenali. Hubungi administrator.']);

    $this->assertGuest();
});

test('an account with an unknown role cannot open the dashboard entry or any role dashboard', function () {
    $user = makeUnknownRoleUser();

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();

    foreach (UserRole::cases() as $role) {
        $this->actingAs($user)
            ->get(route($role->dashboardRoute()))
            ->assertForbidden();
    }
});

test('an account with an unknown role sees safe navigation without privileged menu links', function () {
    $user = makeUnknownRoleUser();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Data Santri')
        ->assertDontSee('Data Dosen')
        ->assertDontSee('Rekap Absensi')
        ->assertDontSee('Rekap Absensi Dosen')
        ->assertDontSee('Rabu Mengaji');
});

test('admin navigation only shows admin menu links', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)
        ->get(route('dashboard.admin'))
        ->assertOk()
        ->assertSee('Data Santri')
        ->assertSee('Data Dosen')
        ->assertSee('Rekap Absensi');
});

test('instructor navigation only shows instructor menu links', function () {
    $user = makeRoleDashboardUser(UserRole::InfrastrukturSik);

    $this->actingAs($user)
        ->get(route('dashboard.infrastruktur_sik'))
        ->assertOk()
        ->assertSee('Mahasiswa')
        ->assertSee('Absen')
        ->assertSee('Nilai')
        ->assertSee('Kelompok Saya')
        ->assertDontSee('Data Santri')
        ->assertDontSee('Data Dosen')
        ->assertDontSee('Rekap Absensi Dosen')
        ->assertSee('Rabu Mengaji');
});

test('admin navigation shows rekap absensi dosen menu', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)
        ->get(route('dashboard.admin'))
        ->assertOk()
        ->assertSee('Rekap Absensi Dosen')
        ->assertSee('Rabu Mengaji');
});

test('santri and ka lab mutu navigation does not show admin menu links', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Data Santri')
        ->assertDontSee('Data Dosen')
        ->assertDontSee('Rekap Absensi Dosen')
        ->assertDontSee('Rabu Mengaji');
})->with([
    'santri' => [UserRole::Santri],
    'ka lab mutu' => [UserRole::KaLabMutu],
]);
