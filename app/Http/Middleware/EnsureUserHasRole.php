<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
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
        $userRole = $user?->role;

        abort_unless(
            $userRole instanceof UserRole &&
            in_array($userRole->value, $roles, true),
            Response::HTTP_FORBIDDEN
        );

        $profileStatus = match ($userRole) {
            UserRole::InfrastrukturSik => $user?->infrastrukturSik?->status,
            UserRole::KaLabMutu => $user?->kaLabMutu?->status,
            default => null,
        };

        abort_if($profileStatus === 'Tidak Aktif', Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
