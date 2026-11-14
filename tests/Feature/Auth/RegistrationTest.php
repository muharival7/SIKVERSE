<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
    $response->assertSee('name="email"', false)
        ->assertSee('name="nomor_induk"', false)
        ->assertSee('name="program_studi"', false)
        ->assertSee('name="jenis_kelamin"', false)
        ->assertSee('data-password-toggle', false)
        ->assertSee('aria-controls="password_confirmation"', false);
});

test('new users can register', function () {
    config()->set('sik.auth.require_email_verification', false);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'nomor_induk' => 'TEST-REG-001',
        'program_studi' => 'Sistem Informasi',
        'jenis_kelamin' => 'Perempuan',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
