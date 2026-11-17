<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\CustomResetPassword;
use App\Notifications\VerifyEmailCodeNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

#[Fillable(['name', 'email', 'password', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'santri',
    ];

    public function santri(): HasOne
    {
        return $this->hasOne(Santri::class);
    }

    public function admin(): HasOne
    {
        return $this->hasOne(Admin::class);
    }

    public function infrastrukturSik(): HasOne
    {
        return $this->hasOne(InfrastrukturSik::class);
    }

    public function kaLabMutu(): HasOne
    {
        return $this->hasOne(KaLabMutu::class);
    }

    public function absensiDosenDicatat(): HasMany
    {
        return $this->hasMany(AbsensiDosen::class, 'dicatat_oleh');
    }

    public function sendEmailVerificationNotification(): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->verificationCodeCacheKey(), Hash::make($code), now()->addMinutes(10));
        $this->notify(new VerifyEmailCodeNotification($code));
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomResetPassword($token));
    }

    public function verificationCodeCacheKey(): string
    {
        return 'email-verification-code:'.$this->getKey();
    }

    /**
     * Role yang dikenali, atau null bila nilai di database tidak valid.
     */
    public function resolvedRole(): ?UserRole
    {
        $rawRole = $this->getAttributes()['role'] ?? $this->getRawOriginal('role');

        if ($rawRole instanceof UserRole) {
            return $rawRole;
        }

        return is_string($rawRole) ? UserRole::tryFrom($rawRole) : null;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'must_change_password' => 'boolean',
        ];
    }
}
