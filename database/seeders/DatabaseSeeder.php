<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $email = config('sik.bootstrap_admin.email');
        $password = config('sik.bootstrap_admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return;
        }

        $admin = User::firstOrNew(['email' => $email]);

        if ($admin->exists) {
            if ($admin->role !== UserRole::Admin) {
                throw new RuntimeException(
                    'The configured bootstrap administrator email already belongs to a non-admin account.'
                );
            }

            return;
        }

        $admin->name = config('sik.bootstrap_admin.name', 'Admin SIK');
        $admin->password = $password;
        $admin->email_verified_at = now();
        $admin->role = UserRole::Admin;
        $admin->save();
    }
}
