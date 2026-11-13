<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VerifyEmailCodeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);
        $user = $request->user();
        $cacheKey = $user->verificationCodeCacheKey();
        $hashedCode = Cache::get($cacheKey);

        if (! is_string($hashedCode) || ! Hash::check($validated['code'], $hashedCode)) {
            throw ValidationException::withMessages([
                'code' => 'Kode salah atau sudah kedaluwarsa. Minta kode baru lalu coba lagi.',
            ]);
        }

        Cache::forget($cacheKey);

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
