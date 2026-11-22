<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\InfrastrukturSik;
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

        if ($userRole === UserRole::Santri && $user?->santri?->status !== 'Aktif') {
            abort(Response::HTTP_FORBIDDEN);
        }

        if ($userRole === UserRole::KaLabMutu && $user?->kaLabMutu?->status === 'Tidak Aktif') {
            abort(Response::HTTP_FORBIDDEN);
        }

        $instruktur = $userRole === UserRole::InfrastrukturSik
            ? $user?->infrastrukturSik
            : null;

        $isReadOnlyRequest = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        abort_if(
            $instruktur instanceof InfrastrukturSik
                && ! $instruktur->isActive()
                && ! $isReadOnlyRequest,
            Response::HTTP_FORBIDDEN,
            EnsureInstructorReadOnlyOnWrite::MESSAGE
        );

        return $next($request);
    }
}
