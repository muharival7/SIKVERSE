<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\AbsensiSik;
use App\Models\BuktiKetidakhadiran;
use App\Models\InfrastrukturSik;
use App\Models\RiwayatAbsensi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $emailChanged = false;

        if ($user->isDirty('email')) {
            $emailChanged = true;
            $user->email_verified_at = config('sik.auth.require_email_verification') ? null : now();
        }

        $user->save();

        if ($emailChanged && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $instruktur = $user->infrastrukturSik;

        if ($instruktur instanceof InfrastrukturSik) {
            $hasOperationalData = $instruktur->jadwal()->exists()
                || $instruktur->nilai()->exists()
                || $instruktur->kelompok()->exists()
                || $instruktur->pertemuanPengganti()->exists()
                || AbsensiSik::where('dicatat_oleh', $instruktur->id)->exists()
                || RiwayatAbsensi::where('instruktur_id', $instruktur->id)->exists()
                || BuktiKetidakhadiran::where('diperiksa_oleh', $instruktur->id)->exists();

            if ($hasOperationalData) {
                return back()->withErrors([
                    'password' => 'Akun instruktur tidak dapat dihapus karena masih terkait dengan data operasional.',
                ], 'userDeletion');
            }
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
