<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Models\Santri;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim((string) $this->input('login'));

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $email = Str::lower($login);
        } else {
            $santri = Santri::where('nomor_induk', $login)
                ->whereNotNull('user_id')
                ->whereHas('user', function ($query): void {
                    $query->where('role', UserRole::Santri->value);
                })
                ->with('user')
                ->first();

            $email = $santri?->user?->email;
        }

        $credentials = [
            'email' => $email,
            'password' => $this->input('password'),
        ];

        if (empty($credentials['email']) || ! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        $isInactive = match ($this->user()?->role) {
            UserRole::Santri => $this->user()?->santri?->status !== 'Aktif',
            UserRole::InfrastrukturSik => $this->user()?->infrastrukturSik?->status === 'Tidak Aktif',
            UserRole::KaLabMutu => $this->user()?->kaLabMutu?->status === 'Tidak Aktif',
            default => false,
        };

        if ($isInactive) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower((string) $this->input('login')).'|'.$this->ip()
        );
    }
}
