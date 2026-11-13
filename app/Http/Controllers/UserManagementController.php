<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\BuktiKetidakhadiran;
use App\Models\InfrastrukturSik;
use App\Models\RiwayatAbsensi;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['infrastrukturSik.kelompok.anggota.rekapKehadiran'])
            ->where('role', UserRole::InfrastrukturSik->value)
            ->latest()
            ->paginate(10)
            ->through(function (User $user): User {
                $recaps = $user->infrastrukturSik?->kelompok
                    ->flatMap(fn ($group) => $group->anggota->flatMap->rekapKehadiran)
                    ?? collect();
                $totalMeetings = (int) config('sik.attendance.meeting_count', 16);
                $averagePresent = $recaps->isNotEmpty()
                    ? round((float) $recaps->avg('jumlah_hadir'))
                    : null;

                $user->setAttribute('recap_count', $recaps->count());
                $user->setAttribute('average_present', $averagePresent);
                $user->setAttribute('recap_meeting_count', $totalMeetings);
                $user->setAttribute('average_percentage', $recaps->isNotEmpty()
                    ? round(($averagePresent / max(1, $totalMeetings)) * 100, 1)
                    : null);

                return $user;
            });

        $totalDosen = User::query()->where('role', UserRole::InfrastrukturSik->value)->count();
        $dosenAktif = User::query()->where('role', UserRole::InfrastrukturSik->value)
            ->whereHas('infrastrukturSik', fn ($query) => $query->where('status', 'Aktif'))
            ->count();

        return view('users.index', compact('users', 'totalDosen', 'dosenAktif'));
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'nip' => ['required', 'string', 'max:50', 'unique:infrastruktur_siks,nip'],
            'program_studi' => ['required', 'string', 'max:255'],
        ]);

        $temporaryPassword = Str::random(14);
        $user = DB::transaction(function () use ($validated, $temporaryPassword): User {
            $user = new User;
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->email_verified_at = config('sik.auth.require_email_verification') ? null : now();
            $user->password = $temporaryPassword;
            $user->must_change_password = true;
            $user->role = UserRole::InfrastrukturSik;
            $user->save();

            $this->syncRoleProfile($user, 'Aktif', $validated);

            return $user;
        });

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('users.index')
            ->with('success', 'Data dosen berhasil dibuat. Sampaikan kredensial sementara ini kepada pemilik akun.')
            ->with('account_created', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $temporaryPassword,
            ]);
    }

    public function edit(User $user): View
    {
        abort_unless($user->role === UserRole::InfrastrukturSik, 404);

        $user->load('infrastrukturSik');
        $status = $user->infrastrukturSik?->status;

        return view('users.edit', compact('user', 'status'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === UserRole::InfrastrukturSik, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'nip' => ['nullable', 'string', 'max:50', Rule::unique('infrastruktur_siks', 'nip')->ignore($user->infrastrukturSik?->id)],
            'program_studi' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'status' => ['nullable', Rule::in(['Aktif', 'Tidak Aktif'])],
        ]);

        $emailChanged = $user->email !== $validated['email'];

        DB::transaction(function () use ($user, $validated, $emailChanged): void {
            $user->name = $validated['name'];
            $user->email = $validated['email'];

            if ($emailChanged) {
                $user->email_verified_at = config('sik.auth.require_email_verification') ? null : now();
            }

            if (filled($validated['password'] ?? null)) {
                $user->password = $validated['password'];
                $user->must_change_password = true;
            }

            $user->save();
            $this->syncRoleProfile($user, $validated['status'] ?? null, $validated);
        });

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('users.index')
            ->with('success', 'Data akun berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === UserRole::InfrastrukturSik, 404);

        if ((int) $user->id === (int) $request->user()->id) {
            return back()->with('error', 'Akun yang sedang digunakan tidak dapat dihapus.');
        }

        $instruktur = $user->infrastrukturSik;

        if ($instruktur instanceof InfrastrukturSik && $this->hasOperationalData($instruktur)) {
            return back()->with(
                'error',
                'Akun instruktur tidak dapat dihapus karena masih terkait data operasional; nonaktifkan statusnya sebagai gantinya.'
            );
        }

        try {
            $user->delete();
        } catch (QueryException) {
            return back()->with('error', 'Akun tidak dapat dihapus karena masih memiliki data terkait.');
        }

        return redirect()->route('users.index')->with('success', 'Akun berhasil dihapus.');
    }

    /** @param array<string, mixed> $details */
    private function syncRoleProfile(User $user, ?string $status = null, array $details = []): void
    {
        match ($user->role) {
            UserRole::Admin => $user->admin()->updateOrCreate([], ['nama' => $user->name]),
            UserRole::InfrastrukturSik => $user->infrastrukturSik()->updateOrCreate([], $this->profileData($user, $status, $details)),
            UserRole::KaLabMutu => $user->kaLabMutu()->updateOrCreate([], $this->profileData($user, $status)),
            UserRole::Santri => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function profileData(User $user, ?string $status, array $details = []): array
    {
        $data = [
            'nama' => $user->name,
            'email' => $user->email,
        ];

        if ($status !== null) {
            $data['status'] = $status;
        }

        foreach (['nip', 'program_studi'] as $field) {
            if (array_key_exists($field, $details)) {
                $data[$field] = $details[$field];
            }
        }

        return $data;
    }

    private function hasOperationalData(InfrastrukturSik $instruktur): bool
    {
        return $instruktur->jadwal()->exists()
            || $instruktur->nilai()->exists()
            || $instruktur->kelompok()->exists()
            || $instruktur->pertemuanPengganti()->exists()
            || AbsensiSik::where('dicatat_oleh', $instruktur->id)->exists()
            || RiwayatAbsensi::where('instruktur_id', $instruktur->id)->exists()
            || BuktiKetidakhadiran::where('diperiksa_oleh', $instruktur->id)->exists();
    }
}
