<?php

test('public registration screen is not available', function () {
    $this->get('/register')->assertNotFound();
});

test('public registration cannot create an account', function () {
    config()->set('sik.auth.require_email_verification', false);

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'nomor_induk' => '900001',
        'program_studi' => 'Sistem Informasi',
        'jenis_kelamin' => 'Perempuan',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});
