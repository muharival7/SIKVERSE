<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'nomor_induk' => ['required', 'string', 'max:255', 'regex:/^[0-9]+$/', 'unique:santris,nomor_induk'],
            'program_studi' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'nomor_induk.regex' => 'Nomor Induk/NIM hanya boleh berisi angka 0-9.',
        ]);

        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->string('name')->trim()->toString(),
                'email' => $request->string('email')->lower()->toString(),
                'password' => Hash::make($request->input('password')),
            ]);

            $user->santri()->create([
                'nomor_induk' => $request->string('nomor_induk')->trim()->toString(),
                'nama' => $user->name,
                'jenis_kelamin' => $request->input('jenis_kelamin'),
                'program_studi' => $request->string('program_studi')->trim()->toString(),
                'status' => 'Aktif',
            ]);

            return $user;
        });

        if (config('sik.auth.require_email_verification')) {
            event(new Registered($user));
        }

        Auth::login($user);

        return redirect()->route(
            config('sik.auth.require_email_verification') ? 'verification.notice' : 'dashboard'
        );
    }
}
