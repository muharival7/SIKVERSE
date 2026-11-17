<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();
        $userRole = $user instanceof User ? $user->resolvedRole() : null;

        abort_if(
            $userRole === null,
            Response::HTTP_FORBIDDEN,
            'Peran pengguna tidak dikenali. Hubungi administrator.'
        );

        abort_unless(
            in_array($userRole->value, $roles, true),
            Response::HTTP_FORBIDDEN
        );

        $profileStatus = match ($userRole) {
            UserRole::Santri => $user?->santri?->status,
            UserRole::InfrastrukturSik => $user?->infrastrukturSik?->status,
            UserRole::KaLabMutu => $user?->kaLabMutu?->status,
            default => null,
        };

        $hasInactiveOrMissingProfile = $userRole === UserRole::Santri
            ? $profileStatus !== 'Aktif'
            : $profileStatus === 'Tidak Aktif';

        abort_if($hasInactiveOrMissingProfile, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
