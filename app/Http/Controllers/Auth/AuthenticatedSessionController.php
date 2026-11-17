<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Menampilkan halaman login.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Memproses login dan mengarahkan pengguna
     * sesuai dengan role masing-masing.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Proses autentikasi
        $request->authenticate();

        // Regenerasi session untuk keamanan
        $request->session()->regenerate();

        // Mengambil user yang berhasil login
        $user = $request->user();

        abort_unless($user instanceof User, 403, 'Peran pengguna tidak dikenali. Hubungi administrator.');

        $role = $user->resolvedRole();

        abort_if($role === null, 403, 'Peran pengguna tidak dikenali. Hubungi administrator.');

        // Redirect langsung ke dashboard sesuai role
        return redirect()->route($role->dashboardRoute());
    }

    /**
     * Logout pengguna.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        // Menghapus session
        $request->session()->invalidate();

        // Membuat token CSRF baru
        $request->session()->regenerateToken();

        // Kembali ke halaman login
        return redirect()->route('login');
    }
}
