<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AnggotaPeriodeSik;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SantriController extends Controller
{
    public function index(): View
    {
        $santris = Santri::with('fingerprint')->latest()->paginate(10);

        return view('santris.index', compact('santris'));
    }

    public function create(): View
    {
        return view('santris.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'unique:santris,nomor_induk'],
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                Rule::requiredIf(config('sik.auth.require_email_verification')),
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'jenis_kelamin' => ['nullable', Rule::in(['Laki-laki', 'Perempuan'])],
            'program_studi' => ['nullable', 'string', 'max:255'],
            'fingerprint_id' => ['nullable', 'string', 'max:255', 'unique:fingerprints,fingerprint_id'],
            'status' => ['required', Rule::in(['Aktif', 'Tidak Aktif'])],
        ]);

        $temporaryPassword = Str::random(12);
        $fingerprintId = $validated['fingerprint_id'] ?? null;
        $accountEmail = $validated['email'] ?? strtolower($validated['nomor_induk']).'@sik.test';
        unset($validated['email']);
        unset($validated['fingerprint_id']);

        $user = DB::transaction(function () use ($validated, $temporaryPassword, $fingerprintId, $accountEmail): User {
            $user = new User;

            $user->name = $validated['nama'];
            $user->email = $accountEmail;
            $user->email_verified_at = config('sik.auth.require_email_verification') ? null : now();
            $user->password = $temporaryPassword;
            $user->role = UserRole::Santri;
            $user->must_change_password = true;

            $user->save();

            $validated['user_id'] = $user->id;

            $santri = Santri::create($validated);

            if (filled($fingerprintId)) {
                $santri->fingerprint()->create([
                    'fingerprint_id' => $fingerprintId,
                    'tanggal_pendaftaran' => now()->toDateString(),
                    'status' => 'Aktif',
                ]);
            }

            return $user;
        });

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('santris.index')
            ->with('success', 'Data santri dan akun login berhasil dibuat.')
            ->with('account_created', [
                'nomor_induk' => $validated['nomor_induk'],
            'email' => $accountEmail,
                'password' => $temporaryPassword,
            ]);
    }

    public function edit(Santri $santri): View
    {
        $santri->load('fingerprint');

        return view('santris.edit', compact('santri'));
    }

    public function show(Santri $santri): View
    {
        $santri->load(['user', 'fingerprint']);

        return view('santris.show', compact('santri'));
    }

    public function update(Request $request, Santri $santri): RedirectResponse
    {
        $santri->load(['user', 'fingerprint']);

        $validated = $request->validate([
            'nomor_induk' => [
                'required',
                Rule::unique('santris')->ignore($santri->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                Rule::requiredIf(config('sik.auth.require_email_verification')),
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($santri->user_id),
            ],
            'jenis_kelamin' => ['nullable', Rule::in(['Laki-laki', 'Perempuan'])],
            'program_studi' => ['nullable', 'string', 'max:255'],
            'fingerprint_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('fingerprints', 'fingerprint_id')->ignore($santri->fingerprint?->id),
            ],
            'status' => ['required', Rule::in(['Aktif', 'Tidak Aktif'])],
        ]);

        $fingerprintId = $validated['fingerprint_id'] ?? null;
        $accountEmail = $validated['email'] ?? $santri->user?->email;
        unset($validated['fingerprint_id']);
        unset($validated['email']);

        DB::transaction(function () use ($santri, $validated, $fingerprintId, $accountEmail): void {
            $santri->update($validated);

            if ($santri->user !== null) {
                $santri->user->name = $validated['nama'];

                if ($accountEmail !== null && $santri->user->email !== $accountEmail) {
                    $santri->user->email = $accountEmail;
                    $santri->user->email_verified_at = config('sik.auth.require_email_verification') ? null : now();
                }

                $santri->user->save();
            }

            if (filled($fingerprintId)) {
                $fingerprint = $santri->fingerprint()->firstOrNew();
                $fingerprint->fingerprint_id = $fingerprintId;
                $fingerprint->tanggal_pendaftaran ??= now()->toDateString();
                $fingerprint->status ??= 'Aktif';
                $fingerprint->save();
            }
        });

        if ($santri->user !== null && ! $santri->user->hasVerifiedEmail()) {
            $santri->user->sendEmailVerificationNotification();
        }

        return redirect()->route('santris.index')
            ->with('success', 'Data santri berhasil diperbarui.');
    }

    public function destroy(Santri $santri): RedirectResponse
    {
        $hasHistory = $santri->absensi()->exists()
            || $santri->nilai()->exists()
            || $santri->kelulusan()->exists()
            || $santri->sertifikat()->exists()
            || $santri->rekapKehadiran()->exists()
            || AnggotaPeriodeSik::where('santri_id', $santri->id)->exists();

        if ($hasHistory) {
            return back()->with(
                'error',
                'Data santri tidak dapat dihapus karena masih memiliki riwayat akademik atau kehadiran.'
            );
        }

        try {
            DB::transaction(function () use ($santri): void {
                $santri->user?->delete();
                $santri->delete();
            });
        } catch (QueryException) {
            return back()->with(
                'error',
                'Data santri tidak dapat dihapus karena masih memiliki data terkait.'
            );
        }

        return redirect()->route('santris.index')
            ->with('success', 'Data santri berhasil dihapus.');
    }
}
