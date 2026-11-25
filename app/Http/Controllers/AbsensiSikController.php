<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\RekapKehadiranSik;
use App\Models\RiwayatAbsensi;
use App\Models\User;
use App\Services\AttendanceAuthorizationService;
use App\Services\AttendanceHistoryService;
use App\Services\AttendanceRecapService;
use App\Services\InstructorKelompokService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AbsensiSikController extends Controller
{
    public function __construct(
        private readonly AttendanceRecapService $attendanceRecapService,
        private readonly AttendanceAuthorizationService $attendanceAuthorizationService,
        private readonly InstructorKelompokService $instructorKelompokService,
        private readonly AttendanceHistoryService $attendanceHistoryService
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'periode_sik_id' => ['nullable', 'integer', 'exists:periode_sik,id'],
        ]);
        $selectedPeriodeId = filled($filters['periode_sik_id'] ?? null)
            ? (int) $filters['periode_sik_id']
            : null;

        $isAdmin = $request->user()->role === UserRole::Admin;
        $instruktur = $isAdmin ? null : $request->user()->infrastrukturSik;
        abort_if(! $isAdmin && ! ($instruktur instanceof InfrastrukturSik), 403);

        $periodes = PeriodeSik::query()
            ->whereHas('kelompok', fn ($query) => $query->where('status', 'Aktif'))
            ->orderByDesc('tahun')
            ->orderBy('nama_periode')
            ->get();

        $availableGroups = $this->instructorKelompokService->activeKelompoks($selectedPeriodeId);
        $selectedGroupIds = $this->instructorKelompokService->resolveSelectedGroupIds(
            $request->query('kelompok_id', []),
            $availableGroups->pluck('id')
        );

        $scopeActiveGroup = function ($query) use ($selectedPeriodeId): void {
            $query->where('status', 'Aktif');

            if ($selectedPeriodeId !== null) {
                $query->where('periode_sik_id', $selectedPeriodeId);
            }
        };

        $absensiQuery = AbsensiSik::with([
            'pertemuan.kegiatan.periode',
            'pertemuan.kegiatan.kelompok.periode',
            'pertemuan.kelompokHost.periode',
            'anggotaPeriode.periode',
            'anggotaPeriode.kelompok.periode',
            'anggotaPeriode.santri',
        ])
            ->whereHas('anggotaPeriode.kelompok', $scopeActiveGroup)
            ->latest();

        if ($selectedPeriodeId !== null) {
            $absensiQuery->whereHas(
                'anggotaPeriode',
                fn ($query) => $query->where('periode_sik_id', $selectedPeriodeId)
            );
        }

        $pertemuanQuery = Pertemuan::with(['kegiatan.periode', 'kegiatan.kelompok', 'kelompokHost'])
            ->where('status', '!=', 'Dibatalkan')
            ->where(function ($query) use ($scopeActiveGroup, $isAdmin, $instruktur): void {
                $query->where(function ($regular) use ($scopeActiveGroup): void {
                    $regular->whereHas('kelompokHost', $scopeActiveGroup)
                        ->orWhereHas('kegiatan.kelompok', $scopeActiveGroup);
                })->orWhere(function ($shared) use ($isAdmin, $instruktur): void {
                    $shared->whereHas(
                        'kegiatan',
                        fn ($kegiatanQuery) => $this->attendanceAuthorizationService->scopeSharedActivity($kegiatanQuery)
                    );

                    if (! $isAdmin) {
                        $shared->where('instruktur_pengganti_id', $instruktur?->id);
                    }
                });
            })
            ->orderByDesc('tanggal');

        if ($selectedPeriodeId !== null) {
            $pertemuanQuery->where(function ($query) use ($selectedPeriodeId, $isAdmin, $instruktur): void {
                $query->where(function ($regular) use ($selectedPeriodeId): void {
                    $regular->whereHas(
                        'kelompokHost',
                        fn ($groupQuery) => $groupQuery->where('periode_sik_id', $selectedPeriodeId)
                    )->orWhereHas(
                        'kegiatan.kelompok',
                        fn ($groupQuery) => $groupQuery->where('periode_sik_id', $selectedPeriodeId)
                    );
                })->orWhere(function ($shared) use ($selectedPeriodeId, $isAdmin, $instruktur): void {
                    $shared->whereHas(
                        'kegiatan',
                        fn ($kegiatanQuery) => $this->attendanceAuthorizationService
                            ->scopeSharedActivity($kegiatanQuery)
                            ->where('periode_sik_id', $selectedPeriodeId)
                    );

                    if (! $isAdmin) {
                        $shared->where('instruktur_pengganti_id', $instruktur?->id);
                    }
                });
            });
        }

        $anggotaQuery = AnggotaPeriodeSik::with(['santri', 'kelompok.periode', 'periode'])
            ->where('status', 'Aktif')
            ->whereHas('kelompok', $scopeActiveGroup)
            ->orderBy('id');

        if ($selectedPeriodeId !== null) {
            $anggotaQuery->where('periode_sik_id', $selectedPeriodeId);
        }

        $search = trim((string) $request->query('q', ''));
        $foundSantri = collect();

        if ($search !== '') {
            $searchSantri = function ($query) use ($search): void {
                $query->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('nomor_induk', 'like', '%'.$search.'%');
            };

            $absensiQuery->whereHas('anggotaPeriode.santri', $searchSantri);
            $anggotaQuery->whereHas('santri', $searchSantri);

            $foundSantriQuery = AnggotaPeriodeSik::query()
                ->with(['santri:id,nomor_induk,nama', 'kelompok:id,nama_kelompok,periode_sik_id', 'periode:id,nama_periode'])
                ->where('status', 'Aktif')
                ->whereHas('kelompok', $scopeActiveGroup)
                ->whereHas('santri', $searchSantri)
                ->whereDoesntHave('absensi')
                ->orderBy('id');

            if ($selectedPeriodeId !== null) {
                $foundSantriQuery->where('periode_sik_id', $selectedPeriodeId);
            }

            if ($selectedGroupIds !== []) {
                $foundSantriQuery->whereIn('kelompok_id', $selectedGroupIds);
            }

            $foundSantri = $foundSantriQuery->limit(25)->get();
        }

        if ($selectedGroupIds !== []) {
            $absensiQuery->whereHas(
                'anggotaPeriode.kelompok',
                fn ($query) => $query->whereIn('id', $selectedGroupIds)
            );
            $anggotaQuery->whereIn('kelompok_id', $selectedGroupIds);
            $pertemuanQuery->where(function ($query) use ($selectedGroupIds): void {
                $query->whereIn('kelompok_host_id', $selectedGroupIds)
                    ->orWhereHas(
                        'kegiatan',
                        fn ($kegiatanQuery) => $kegiatanQuery->whereIn('kelompok_id', $selectedGroupIds)
                    )
                    ->orWhereHas(
                        'kegiatan',
                        fn ($kegiatanQuery) => $this->attendanceAuthorizationService->scopeSharedActivity($kegiatanQuery)
                    );
            });
        }

        $pertemuan = $pertemuanQuery->get();
        $completablePertemuanIds = $isAdmin
            ? $pertemuan->modelKeys()
            : $this->completablePertemuanIds($pertemuan, $instruktur);

        return view('absensi-sik.index', [
            'absensi' => $absensiQuery->paginate(15)->withQueryString(),
            'pertemuan' => $pertemuan,
            'anggota' => $anggotaQuery->get(),
            'kelompoks' => $availableGroups,
            'periodes' => $periodes,
            'selectedPeriodeId' => $selectedPeriodeId,
            'search' => $search,
            'foundSantri' => $foundSantri,
            'selectedGroupIds' => $selectedGroupIds,
            'completablePertemuanIds' => $completablePertemuanIds,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function riwayatSantri(Request $request): View
    {
        $santri = $request->user()->santri;
        abort_unless($santri !== null, 403, 'Data santri tidak ditemukan untuk akun ini.');

        $absensi = AbsensiSik::with([
            'pertemuan.kegiatan',
            'anggotaPeriode.periode',
            'anggotaPeriode.kelompok',
            'buktiKetidakhadiran.pemeriksa',
        ])
            ->whereHas('anggotaPeriode', fn ($query) => $query->where('santri_id', $santri->id))
            ->latest()
            ->paginate(10);

        $rekap = RekapKehadiranSik::with(['anggotaPeriode.periode', 'kegiatan'])
            ->whereHas('anggotaPeriode', fn ($query) => $query->where('santri_id', $santri->id))
            ->latest()
            ->get();

        $this->attendanceRecapService->prepareDisplay($rekap);

        return view('santris.absensi-saya', [
            'santri' => $santri,
            'absensi' => $absensi,
            'totalAbsensi' => $absensi->total(),
            'rekap' => $rekap,
            'minimumPercentage' => $this->attendanceRecapService->minimumPercentage(),
            'noMeetingsStatus' => AttendanceRecapService::STATUS_NO_MEETINGS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'anggota_periode_sik_id' => ['required', 'exists:anggota_periode_sik,id'],
            'pertemuan_id' => ['required', 'exists:pertemuan,id'],
            'waktu_masuk' => ['nullable', 'date_format:H:i'],
            'waktu_pulang' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::in(['Hadir', 'Izin', 'Sakit', 'Alpha', 'Tidak Lengkap'])],
            'keterangan' => ['nullable', 'string'],
        ]);

        $anggota = AnggotaPeriodeSik::with('kelompok')->findOrFail($validated['anggota_periode_sik_id']);
        $pertemuan = Pertemuan::with(['kegiatan.kelompok', 'kelompokHost'])->findOrFail($validated['pertemuan_id']);
        $instruktur = $this->currentInstructor($request);
        $this->ensureSharedPertemuanVisibleToInstructor($pertemuan, $instruktur);
        $this->attendanceAuthorizationService->authorize($anggota, $pertemuan, $instruktur);

        try {
            DB::transaction(function () use ($validated, $anggota, $pertemuan, $instruktur): void {
                AbsensiSik::create([
                    ...$validated,
                    'metode_absensi' => 'Manual',
                    'dicatat_oleh' => $instruktur?->id,
                ]);

                $this->syncRekap($anggota, $pertemuan);
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateAbsensiConstraintViolation($exception)) {
                return back()->withInput()->withErrors([
                    'pertemuan_id' => 'Absensi untuk santri dan pertemuan ini sudah tercatat.',
                ]);
            }

            Log::warning('Gagal menyimpan absensi SIK manual.', [
                'anggota_periode_sik_id' => $validated['anggota_periode_sik_id'] ?? null,
                'pertemuan_id' => $validated['pertemuan_id'] ?? null,
                'sql_state' => $exception->getCode(),
                'driver_code' => $exception->errorInfo[1] ?? null,
                'constraint' => $this->extractQueryExceptionConstraint($exception),
            ]);

            return back()->withInput()->withErrors([
                'pertemuan_id' => 'Absensi gagal disimpan karena terjadi kendala sistem. Silakan coba lagi atau hubungi administrator.',
            ]);
        }

        return redirect()->route('absensi-sik.index')
            ->with('success', 'Absensi manual berhasil disimpan.');
    }

    public function update(Request $request, AbsensiSik $absensiSik): RedirectResponse
    {
        $validated = $request->validate([
            'waktu_masuk' => ['nullable', 'date_format:H:i'],
            'waktu_pulang' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::in(['Hadir', 'Izin', 'Sakit', 'Alpha', 'Tidak Lengkap'])],
            'keterangan' => ['nullable', 'string'],
            'alasan_perubahan' => ['nullable', 'string', 'max:1000'],
        ]);

        $absensiSik->load(['anggotaPeriode.kelompok', 'pertemuan.kegiatan.kelompok', 'pertemuan.kelompokHost']);
        $instruktur = $this->currentInstructor($request);
        $this->ensureSharedPertemuanVisibleToInstructor($absensiSik->pertemuan, $instruktur);
        $this->attendanceAuthorizationService->authorize($absensiSik->anggotaPeriode, $absensiSik->pertemuan, $instruktur);

        $buktiMenunggu = $absensiSik->buktiKetidakhadiran()
            ->whereIn('status_verifikasi', ['Menunggu', 'Diterima'])
            ->latest()
            ->first();

        if ($buktiMenunggu !== null && $validated['status'] !== $buktiMenunggu->jenis_bukti) {
            throw ValidationException::withMessages([
                'status' => 'Status absensi harus sesuai dengan bukti yang sedang menunggu atau sudah diterima.',
            ]);
        }

        $statusSebelumnya = $absensiSik->status;
        $alasan = $validated['alasan_perubahan'] ?? null;
        unset($validated['alasan_perubahan']);
        $actor = $request->user() instanceof User ? $request->user() : null;

        DB::transaction(function () use (
            $absensiSik,
            $validated,
            $statusSebelumnya,
            $alasan,
            $instruktur,
            $actor
        ): void {
            if ($statusSebelumnya !== $validated['status'] && $instruktur instanceof InfrastrukturSik) {
                RiwayatAbsensi::create([
                    'absensi_sik_id' => $absensiSik->id,
                    'instruktur_id' => $instruktur->id,
                    'status_sebelumnya' => $statusSebelumnya,
                    'status_sesudahnya' => $validated['status'],
                    'alasan_perubahan' => $alasan,
                ]);
            }

            $this->attendanceHistoryService->recordAbsensiStatusChange(
                (int) $absensiSik->id,
                (int) $absensiSik->pertemuan_id,
                $statusSebelumnya,
                $validated['status'],
                $actor,
                $instruktur,
                $alasan
            );

            $absensiSik->update($validated);
            $this->syncRekap($absensiSik->anggotaPeriode, $absensiSik->pertemuan);
        });

        return redirect()->route('absensi-sik.index')
            ->with('success', 'Absensi berhasil diperbarui.');
    }

    /**
     * Tandai pertemuan selesai secara eksplisit. Status tidak berubah otomatis
     * hanya karena tanggal sudah lampau atau absensi sudah diisi.
     */
    public function tandaiPertemuanTerlaksana(Request $request, Pertemuan $pertemuan): RedirectResponse
    {
        $pertemuan->load(['kegiatan.kelompok', 'kelompokHost']);
        $instruktur = $this->currentInstructor($request);
        $this->ensureSharedPertemuanVisibleToInstructor($pertemuan, $instruktur);
        $this->attendanceAuthorizationService->authorizePertemuanAccess($pertemuan, $instruktur);

        if ($pertemuan->status === 'Terlaksana') {
            return redirect()->route('absensi-sik.index')
                ->with('success', 'Pertemuan sudah berstatus Terlaksana.');
        }

        $statusSebelumnya = (string) $pertemuan->status;
        $actor = $request->user() instanceof User ? $request->user() : null;

        DB::transaction(function () use ($pertemuan, $statusSebelumnya, $instruktur, $actor): void {
            $pertemuan->update(['status' => 'Terlaksana']);

            $this->attendanceHistoryService->recordPertemuanStatusChange(
                (int) $pertemuan->id,
                $statusSebelumnya,
                'Terlaksana',
                $actor,
                $instruktur,
                'Pertemuan ditandai selesai melalui modul absensi.'
            );

            $this->attendanceRecapService->syncForPertemuan($pertemuan);
        });

        return redirect()->route('absensi-sik.index')
            ->with('success', 'Pertemuan ditandai Terlaksana. Rekap kehadiran telah disinkronkan.');
    }

    /**
     * Ubah tanggal pertemuan tanpa menyentuh catatan absensi. Absensi tetap
     * terhubung lewat pertemuan_id, sehingga status, identitas santri, dan
     * kegiatan/kelompok tidak berubah. Untuk pertemuan reguler, semua
     * instruktur aktif boleh mengubah tanggal; Tandai Selesai tetap dibatasi
     * terpisah. Untuk pertemuan shared, hanya Admin atau instruktur pengganti
     * yang ditetapkan yang boleh mengubah tanggal.
     */
    public function ubahTanggalPertemuan(Request $request, Pertemuan $pertemuan): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'tanggal_baru' => ['required', 'date', 'date_format:Y-m-d'],
            'konfirmasi' => ['nullable', 'accepted'],
            'kelompok_id' => ['nullable', 'integer', 'exists:kelompok,id'],
            'periode_sik_id' => ['nullable', 'integer', 'exists:periode_sik,id'],
        ]);

        $pertemuan->load(['kegiatan.periode', 'kegiatan.kelompok.periode', 'kelompokHost.periode']);
        $instruktur = $this->currentInstructor($request);
        $this->ensureSharedPertemuanVisibleToInstructor($pertemuan, $instruktur);
        $this->attendanceAuthorizationService->authorizePertemuanDateChange(
            $pertemuan,
            $instruktur,
            filled($validated['kelompok_id'] ?? null) ? (int) $validated['kelompok_id'] : null,
            filled($validated['periode_sik_id'] ?? null) ? (int) $validated['periode_sik_id'] : null
        );

        if ($pertemuan->absensi()->exists() && ! $request->boolean('konfirmasi')) {
            throw ValidationException::withMessages([
                'konfirmasi' => 'Konfirmasi diperlukan untuk mengubah tanggal pertemuan yang sudah memiliki catatan absensi.',
            ]);
        }

        $tanggalLama = $pertemuan->tanggal->toDateString();

        if ($tanggalLama === $validated['tanggal_baru']) {
            return redirect()->route('absensi-sik.index')
                ->with('success', 'Tanggal pertemuan tidak berubah.');
        }

        $actor = $request->user() instanceof User ? $request->user() : null;

        DB::transaction(function () use (
            $pertemuan,
            $validated,
            $tanggalLama,
            $instruktur,
            $actor
        ): void {
            // Hanya pertemuan yang diubah. Catatan absensi tetap terhubung
            // melalui pertemuan_id tanpa perubahan status atau identitas.
            $pertemuan->update(['tanggal' => $validated['tanggal_baru']]);

            $this->attendanceHistoryService->recordPertemuanDateChange(
                (int) $pertemuan->id,
                $tanggalLama,
                $validated['tanggal_baru'],
                $actor,
                $instruktur,
                'Tanggal pertemuan diubah melalui modul absensi.'
            );
        });

        return redirect()->route('absensi-sik.index')
            ->with('success', 'Tanggal pertemuan berhasil diubah dari '.$tanggalLama.' menjadi '.$validated['tanggal_baru'].'.');
    }

    /**
     * Daftar pertemuan yang boleh ditandai selesai. Izin Ubah Tanggal sengaja
     * tidak memakai daftar ini karena terbuka untuk semua instruktur aktif.
     *
     * @param  Collection<int, Pertemuan>  $pertemuan
     * @return array<int, int>
     */
    private function completablePertemuanIds(Collection $pertemuan, ?InfrastrukturSik $instruktur): array
    {
        if (! $instruktur instanceof InfrastrukturSik) {
            return [];
        }

        $replacementGroupIds = Pertemuan::where('instruktur_pengganti_id', $instruktur->id)
            ->whereNotNull('kelompok_host_id')
            ->pluck('kelompok_host_id');
        $manageableGroupIds = $this->instructorKelompokService->ownedGroupIds($instruktur)
            ->merge($replacementGroupIds)
            ->unique()
            ->values();

        return $pertemuan
            ->filter(function (Pertemuan $meeting) use ($instruktur, $manageableGroupIds): bool {
                if ((int) $meeting->instruktur_pengganti_id === (int) $instruktur->id) {
                    return true;
                }

                $groupIds = array_filter([
                    $meeting->kelompok_host_id,
                    $meeting->kegiatan?->kelompok_id,
                ], static fn ($id): bool => $id !== null);

                foreach ($groupIds as $groupId) {
                    if ($manageableGroupIds->contains((int) $groupId)) {
                        return true;
                    }
                }

                return false;
            })
            ->modelKeys();
    }

    private function currentInstructor(Request $request): ?InfrastrukturSik
    {
        $user = $request->user();
        $role = $user instanceof User ? $user->resolvedRole() : null;

        if ($role === UserRole::Admin) {
            return null;
        }

        abort_unless($role === UserRole::InfrastrukturSik, 403);

        $instruktur = $request->user()->infrastrukturSik;
        abort_unless($instruktur instanceof InfrastrukturSik, 403);

        return $instruktur;
    }

    /**
     * Guard visibilitas shared untuk jalur Absensi SIK. Admin dilewati.
     * Instruktur hanya boleh mengakses pertemuan shared yang ditugaskan
     * sebagai instruktur_pengganti_id. Kegiatan reguler tetap divalidasi
     * oleh authorize()/authorizePertemuan* sebagai sumber keputusan akhir.
     */
    private function ensureSharedPertemuanVisibleToInstructor(
        Pertemuan $pertemuan,
        ?InfrastrukturSik $instruktur
    ): void {
        if (! $instruktur instanceof InfrastrukturSik) {
            return;
        }

        $kegiatan = $pertemuan->kegiatan;

        if (! $kegiatan instanceof Kegiatan || ! $this->attendanceAuthorizationService->isSharedActivity($kegiatan)) {
            return;
        }

        $this->attendanceAuthorizationService->assertSharedActivityShape($kegiatan, $pertemuan);
        $this->attendanceAuthorizationService->assertSharedPertemuanAssignedToInstructor($pertemuan, $instruktur);
    }

    private function syncRekap(AnggotaPeriodeSik $anggota, Pertemuan $pertemuan): void
    {
        $this->attendanceRecapService->sync(
            (int) $anggota->id,
            (int) $pertemuan->kegiatan_id
        );
    }

    private function isDuplicateAbsensiConstraintViolation(QueryException $exception): bool
    {
        if ((string) $exception->getCode() !== '23000') {
            return false;
        }

        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        if ($driverCode !== 1062) {
            return false;
        }

        $constraint = $this->extractQueryExceptionConstraint($exception);
        if ($constraint === null) {
            return false;
        }

        return $constraint === 'absensi_anggota_pertemuan_unique'
            || str_ends_with($constraint, '.absensi_anggota_pertemuan_unique');
    }

    private function extractQueryExceptionConstraint(QueryException $exception): ?string
    {
        $message = $exception->getMessage();

        if (preg_match("/for key '([^']+)'/", $message, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/for key "([^"]+)"/', $message, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/UNIQUE constraint failed: ([^\s]+)/', $message, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
