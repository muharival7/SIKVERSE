<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AbsensiDosen;
use App\Models\AbsensiSik;
use App\Models\BuktiKetidakhadiran;
use App\Models\InfrastrukturSik;
use App\Models\RiwayatAbsensi;
use App\Models\User;
use App\Services\AttendanceRecapService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(private readonly AttendanceRecapService $attendanceRecapService) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['Aktif', 'Tidak Aktif'])],
        ]);

        $userQuery = User::query()
            ->with(['infrastrukturSik.kelompok.anggota.rekapKehadiran'])
            ->where('role', UserRole::InfrastrukturSik->value);

        if (filled($filters['search'] ?? null)) {
            $search = trim($filters['search']);
            $userQuery->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('infrastrukturSik', function ($profileQuery) use ($search): void {
                        $profileQuery->where('nip', 'like', "%{$search}%")
                            ->orWhere('program_studi', 'like', "%{$search}%")
                            ->orWhereHas('kelompok', fn ($groupQuery) => $groupQuery->where('nama_kelompok', 'like', "%{$search}%"));
                    });
            });
        }

        if (filled($filters['status'] ?? null)) {
            $userQuery->whereHas('infrastrukturSik', fn ($query) => $query->where('status', $filters['status']));
        }

        $users = $userQuery->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(function (User $user): User {
                $kelompokNames = $user->infrastrukturSik?->kelompok
                    ->pluck('nama_kelompok')
                    ->filter()
                    ->sort()
                    ->values()
                    ->all() ?? [];

                $recaps = $user->infrastrukturSik?->kelompok
                    ->flatMap(fn ($group) => $group->anggota->flatMap->rekapKehadiran)
                    ?? collect();

                $this->attendanceRecapService->prepareDisplay($recaps);
                $recapsTersedia = $recaps->filter(fn ($recap): bool => (bool) $recap->tersedia);

                $user->setAttribute('dosen_kelompok', $kelompokNames);
                $user->setAttribute('recap_count', $recaps->count());
                $user->setAttribute('average_present', $recapsTersedia->isNotEmpty()
                    ? round((float) $recapsTersedia->avg('jumlah_hadir'))
                    : null);
                $user->setAttribute('recap_meeting_count', $recapsTersedia->isNotEmpty()
                    ? round((float) $recapsTersedia->avg('total_pertemuan'))
                    : 0);
                $user->setAttribute('average_percentage', $this->attendanceRecapService->averagePercentage($recaps));

                return $user;
            });

        $totalDosen = InfrastrukturSik::query()->count();
        $dosenAktif = InfrastrukturSik::query()->where('status', 'Aktif')->count();

        $absensiDosenAvailable = Schema::hasTable('absensi_dosen');
        $profileIds = $users->getCollection()
            ->map(fn (User $user): ?int => $user->infrastrukturSik?->id)
            ->filter()
            ->values();
        $attendanceSummaries = $absensiDosenAvailable && $profileIds->isNotEmpty()
            ? AbsensiDosen::query()
                ->whereIn('infrastruktur_sik_id', $profileIds)
                ->selectRaw('infrastruktur_sik_id, COUNT(*) as total_pertemuan')
                ->selectRaw("SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as total_hadir")
                ->groupBy('infrastruktur_sik_id')
                ->get()
                ->keyBy('infrastruktur_sik_id')
            : collect();

        $users->getCollection()->each(function (User $user) use ($attendanceSummaries): void {
            $summary = $attendanceSummaries->get($user->infrastrukturSik?->id);
            $meetings = (int) ($summary?->total_pertemuan ?? 0);
            $present = (int) ($summary?->total_hadir ?? 0);

            $user->setAttribute('dosen_attendance_count', $meetings);
            $user->setAttribute('dosen_attendance_present', $present);
            $user->setAttribute('dosen_attendance_percentage', $meetings > 0 ? round(($present / $meetings) * 100, 1) : null);
        });

        return view('users.index', compact('users', 'totalDosen', 'dosenAktif', 'filters', 'absensiDosenAvailable'));
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
            'nip' => ['required', 'string', 'max:50', 'regex:/^[0-9]+$/', 'unique:infrastruktur_siks,nip'],
            'program_studi' => ['required', 'string', 'max:255'],
        ], [
            'nip.regex' => 'NIP hanya boleh berisi angka 0-9.',
            'nip.unique' => 'NIP sudah terdaftar.',
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
            'nip' => [
                'nullable',
                'string',
                'max:50',
                function (string $attribute, mixed $value, $fail) use ($user): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if ((string) $value === (string) $user->infrastrukturSik?->nip) {
                        return;
                    }

                    if (preg_match('/^[0-9]+$/', (string) $value) !== 1) {
                        $fail('NIP hanya boleh berisi angka 0-9.');
                    }
                },
                Rule::unique('infrastruktur_siks', 'nip')->ignore($user->infrastrukturSik?->id),
            ],
            'program_studi' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'status' => ['nullable', Rule::in(['Aktif', 'Tidak Aktif'])],
        ], [
            'nip.unique' => 'NIP sudah terdaftar.',
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
