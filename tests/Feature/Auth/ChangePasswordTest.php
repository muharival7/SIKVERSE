<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function makeForcedChangeUser(UserRole $role = UserRole::Admin): User
{
    return User::factory()->create([
        'role' => $role,
        'must_change_password' => true,
    ]);
}

test('a user required to change password can open the change password form', function () {
    $user = makeForcedChangeUser();

    $this->actingAs($user)
        ->get(route('password.change'))
        ->assertOk()
        ->assertSee('Ganti Password SIKVERSE');
});

test('a user required to change password is redirected away from the dashboard', function () {
    $user = makeForcedChangeUser();

    $this->actingAs($user)
        ->get(route('dashboard.admin'))
        ->assertRedirect(route('password.change'));
});

test('changing the password clears the requirement and redirects to the dashboard', function () {
    $user = makeForcedChangeUser();

    $response = $this->actingAs($user)->post(route('change-password.update'), [
        'current_password' => 'password',
        'password' => 'new-sik-password',
        'password_confirmation' => 'new-sik-password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHas('success', 'Password berhasil diganti.');

    expect($user->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check('new-sik-password', $user->fresh()->password))->toBeTrue();
});

test('the legacy password change route also clears the requirement', function () {
    $user = makeForcedChangeUser();

    $this->actingAs($user)->post(route('password.custom-update'), [
        'current_password' => 'password',
        'password' => 'legacy-sik-password',
        'password_confirmation' => 'legacy-sik-password',
    ])->assertRedirect(route('dashboard', absolute: false));

    expect($user->fresh()->must_change_password)->toBeFalse();
});

test('an incorrect current password keeps the change requirement active', function () {
    $user = makeForcedChangeUser();

    $this->actingAs($user)->post(route('change-password.update'), [
        'current_password' => 'wrong-password',
        'password' => 'new-sik-password',
        'password_confirmation' => 'new-sik-password',
    ])->assertSessionHasErrors('current_password');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('a mismatched confirmation keeps the change requirement active', function () {
    $user = makeForcedChangeUser();

    $this->actingAs($user)->post(route('change-password.update'), [
        'current_password' => 'password',
        'password' => 'new-sik-password',
        'password_confirmation' => 'different-password',
    ])->assertSessionHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('after changing the password the user can continue to the role dashboard and sign in again', function () {
    $user = makeForcedChangeUser();

    $this->actingAs($user)->post(route('change-password.update'), [
        'current_password' => 'password',
        'password' => 'new-sik-password',
        'password_confirmation' => 'new-sik-password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user->fresh())
        ->get(route('dashboard.admin'))
        ->assertOk();

    $this->post('/logout');

    $this->post('/login', [
        'login' => $user->fresh()->email,
        'password' => 'new-sik-password',
    ])->assertRedirect(route('dashboard.admin', absolute: false));

    $this->assertAuthenticated();
});

test('changing the password through the profile route clears the requirement', function () {
    $user = makeForcedChangeUser();

    $response = $this->actingAs($user)
        ->from('/profile')
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'profile-sik-password',
            'password_confirmation' => 'profile-sik-password',
        ]);

    $response->assertRedirect('/profile')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'password-updated');

    expect($user->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check('profile-sik-password', $user->fresh()->password))->toBeTrue();
});

test('the new password set through the profile route can be used for login and opens the role dashboard', function () {
    $user = makeForcedChangeUser();

    $this->actingAs($user)->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'profile-sik-password',
        'password_confirmation' => 'profile-sik-password',
    ])->assertRedirect();

    $this->post('/logout');

    $this->post('/login', [
        'login' => $user->fresh()->email,
        'password' => 'profile-sik-password',
    ])->assertRedirect(route('dashboard.admin', absolute: false));

    $this->assertAuthenticated();

    $this->get(route('dashboard.admin'))->assertOk();
});

test('an incorrect current password on the profile route keeps the requirement active', function () {
    $user = makeForcedChangeUser();

    $this->actingAs($user)
        ->from('/profile')
        ->put(route('password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'profile-sik-password',
            'password_confirmation' => 'profile-sik-password',
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

    expect($user->fresh()->must_change_password)->toBeTrue();
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('invalid new password input on the profile route keeps the requirement active', function () {
    $user = makeForcedChangeUser();

    $this->actingAs($user)
        ->from('/profile')
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');

    expect($user->fresh()->must_change_password)->toBeTrue();
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
