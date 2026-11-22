<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\InfrastrukturSik;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstructorReadOnlyOnWrite
{
    public const MESSAGE = 'Akun Dosen tidak aktif hanya memiliki akses baca-saja.';

    private const READ_ONLY_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), self::READ_ONLY_METHODS, true)) {
            return $next($request);
        }

        // Logout hanya mengakhiri sesi dan tidak mengubah data akademik.
        if ($request->routeIs('logout')) {
            return $next($request);
        }

        $user = Auth::user();

        if (! $user instanceof User || $user->resolvedRole() !== UserRole::InfrastrukturSik) {
            return $next($request);
        }

        $instruktur = $user->infrastrukturSik;

        if ($instruktur instanceof InfrastrukturSik && $instruktur->isActive()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => self::MESSAGE], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, self::MESSAGE);
    }
}
