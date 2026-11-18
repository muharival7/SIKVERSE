<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Pertemuan;
use App\Models\RekapKehadiranSik;
use App\Models\User;
use App\Services\AttendanceAuthorizationService;
use App\Services\AttendanceRecapService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RabuMengajiController extends Controller
{
    public function __construct(
        private readonly AttendanceRecapService $attendanceRecapService,
        private readonly AttendanceAuthorizationService $attendanceAuthorizationService
    ) {}

    /** Daftar kehadiran Rabu Mengaji memakai kegiatan, pertemuan, dan absensi_sik yang sudah ada. */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tanggal' => ['nullable', 'date'],
            'kegiatan_id' => ['nullable', 'integer'],
        ]);

        $kegiatan = $this->sharedActivities()->orderBy('nama_kegiatan')->get();
        $query = $this->scopedAbsensiQuery($user);
        $this->applyFilters($query, $filters);

        $summary = (clone $query)
            ->whereHas('pertemuan', fn (Builder $query): mixed => $query->where('status', 'Terlaksana'))
            ->whereIn('absensi_sik.status', ['Hadir', 'Izin', 'Sakit', 'Alpha'])
            ->selectRaw('COUNT(*) as total_pertemuan')
            ->selectRaw("SUM(CASE WHEN absensi_sik.status = 'Hadir' THEN 1 ELSE 0 END) as total_hadir")
            ->selectRaw("SUM(CASE WHEN absensi_sik.status = 'Izin' THEN 1 ELSE 0 END) as total_izin")
            ->selectRaw("SUM(CASE WHEN absensi_sik.status = 'Sakit' THEN 1 ELSE 0 END) as total_sakit")
            ->selectRaw("SUM(CASE WHEN absensi_sik.status = 'Alpha' THEN 1 ELSE 0 END) as total_alpha")
            ->first();

        $absensi = (clone $query)
            ->with([
                'anggotaPeriode.santri',
                'anggotaPeriode.kelompok',
                'pertemuan.kegiatan',
            ])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('rabu-mengaji.index', compact('absensi', 'kegiatan', 'filters', 'summary'))
            ->with([
                'minimumPercentage' => $this->attendanceRecapService->minimumPercentage(),
            ]);
    }

    /** Rincian kehadiran satu catatan Rabu Mengaji beserta riwayat dan rekapnya. */
    public function show(Request $request, AbsensiSik $absensiSik): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $catatan = $this->scopedAbsensiQuery($user)
            ->with([
                'anggotaPeriode.santri',
                'anggotaPeriode.kelompok',
                'anggotaPeriode.periode',
                'pertemuan.kegiatan',
            ])
            ->findOrFail($absensiSik->id);

        $kegiatan = $catatan->pertemuan?->kegiatan;
        abort_unless(
            $kegiatan instanceof Kegiatan && $this->attendanceAuthorizationService->isSharedActivity($kegiatan),
            404
        );

        $riwayat = AbsensiSik::query()
            ->with('pertemuan')
            ->where('anggota_periode_sik_id', $catatan->anggota_periode_sik_id)
            ->whereHas('pertemuan', fn (Builder $query): mixed => $query->where('kegiatan_id', $kegiatan->id))
            ->get()
            ->sortBy(fn (AbsensiSik $item): string => sprintf(
                '%s-%04d',
                $item->pertemuan?->tanggal?->format('Y-m-d') ?? '0000-00-00',
                (int) ($item->pertemuan?->pertemuan_ke ?? 0)
            ))
            ->values();

        $rekap = RekapKehadiranSik::query()
            ->where('anggota_periode_sik_id', $catatan->anggota_periode_sik_id)
            ->where('kegiatan_id', $kegiatan->id)
            ->first();

        if ($rekap instanceof RekapKehadiranSik) {
            $this->attendanceRecapService->prepareDisplay([$rekap]);
        }

        $minimumPercentage = $this->attendanceRecapService->minimumPercentage();

        return view('rabu-mengaji.show', compact(
            'catatan',
            'kegiatan',
            'riwayat',
            'rekap',
            'minimumPercentage'
        ));
    }

    public function edit(Request $request, Pertemuan $pertemuan): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $kegiatan = $pertemuan->kegiatan;

        if (! $kegiatan instanceof Kegiatan || ! $this->attendanceAuthorizationService->isSharedActivity($kegiatan)) {
            abort(404, 'Kegiatan bukan Rabu Mengaji.');
        }

        $instruktur = $this->resolveInstruktur($user);
        $this->attendanceAuthorizationService->authorizePertemuanAccess($pertemuan, $instruktur);

        $anggotaList = $this->eligibleAnggotaQuery($kegiatan)
            ->orderBy('kelompok_id')
            ->orderBy('id')
            ->get();

        $existingAbsensi = AbsensiSik::where('pertemuan_id', $pertemuan->id)
            ->pluck('status', 'anggota_periode_sik_id')
            ->toArray();

        return view('rabu-mengaji.input', compact(
            'pertemuan',
            'kegiatan',
            'anggotaList',
            'existingAbsensi'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'pertemuan_id' => ['required', 'exists:pertemuan,id'],
            'status' => ['required', 'array'],
            'status.*' => ['required', Rule::in(['Hadir', 'Izin', 'Sakit', 'Alpha'])],
            'keterangan' => ['nullable', 'array'],
            'keterangan.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $pertemuan = Pertemuan::with(['kegiatan', 'kelompokHost'])->findOrFail($validated['pertemuan_id']);
        $kegiatan = $pertemuan->kegiatan;

        if (! $kegiatan instanceof Kegiatan || ! $this->attendanceAuthorizationService->isSharedActivity($kegiatan)) {
            abort(404, 'Kegiatan bukan Rabu Mengaji.');
        }

        $instruktur = $this->resolveInstruktur($user);
        $this->attendanceAuthorizationService->authorizePertemuanAccess($pertemuan, $instruktur);

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($validated, $pertemuan, $instruktur, &$created, &$skipped): void {
            foreach ($validated['status'] as $anggotaId => $status) {
                $anggotaId = (int) $anggotaId;

                $anggota = AnggotaPeriodeSik::with(['kelompok', 'periode'])->find($anggotaId);
                if (! $anggota instanceof AnggotaPeriodeSik) {
                    $skipped++;

                    continue;
                }

                $this->attendanceAuthorizationService->authorize($anggota, $pertemuan, $instruktur);

                $existing = AbsensiSik::where('anggota_periode_sik_id', $anggota->id)
                    ->where('pertemuan_id', $pertemuan->id)
                    ->first();

                if ($existing instanceof AbsensiSik) {
                    $skipped++;

                    continue;
                }

                $absensi = AbsensiSik::create([
                    'anggota_periode_sik_id' => $anggota->id,
                    'pertemuan_id' => $pertemuan->id,
                    'status' => $status,
                    'keterangan' => $validated['keterangan'][$anggotaId] ?? null,
                    'metode_absensi' => 'Manual',
                    'waktu_masuk' => null,
                    'waktu_pulang' => null,
                    'dicatat_oleh' => $instruktur?->id,
                ]);

                $this->attendanceRecapService->syncForAbsensi($absensi);

                $created++;
            }
        });

        if ($created > 0) {
            return redirect()->route('rabu-mengaji.index')
                ->with('success', "Kehadiran berhasil dicatat untuk {$created} santri.".($skipped > 0 ? " {$skipped} data dilewati (sudah ada atau tidak valid)." : ''));
        }

        return back()->withInput()->withErrors([
            'pertemuan_id' => 'Tidak ada kehadiran yang berhasil dicatat. Semua data sudah ada atau tidak valid.',
        ]);
    }

    /** @return Builder<Kegiatan> */
    private function sharedActivities(): Builder
    {
        return $this->attendanceAuthorizationService->scopeSharedActivity(Kegiatan::query());
    }

    /** @return Builder<AnggotaPeriodeSik> */
    private function eligibleAnggotaQuery(Kegiatan $kegiatan): Builder
    {
        return AnggotaPeriodeSik::with(['santri', 'kelompok', 'periode'])
            ->where('status', 'Aktif')
            ->where('periode_sik_id', $kegiatan->periode_sik_id)
            ->whereHas(
                'kelompok',
                fn (Builder $query): mixed => $query
                    ->where('status', 'Aktif')
                    ->where('periode_sik_id', $kegiatan->periode_sik_id)
            );
    }

    private function resolveInstruktur(User $user): ?InfrastrukturSik
    {
        if ($user->role === UserRole::Admin) {
            return null;
        }

        $instruktur = $user->infrastrukturSik;
        abort_unless($instruktur instanceof InfrastrukturSik, 403, 'Data instruktur tidak ditemukan.');

        return $instruktur;
    }

    /** @return Builder<AbsensiSik> */
    private function scopedAbsensiQuery(User $user): Builder
    {
        $query = AbsensiSik::query()
            ->whereHas(
                'pertemuan.kegiatan',
                fn (Builder $query): mixed => $this->attendanceAuthorizationService->scopeSharedActivity($query)
            );

        $instruktur = $this->resolveInstruktur($user);

        if ($instruktur instanceof InfrastrukturSik) {
            abort_unless(
                $this->attendanceAuthorizationService->isActiveInstructor($instruktur),
                403,
                'Instruktur tidak aktif tidak berwenang melihat kehadiran.'
            );
        }

        return $query;
    }

    /**
     * @param  array<string, string|null>  $filters
     * @param  Builder<AbsensiSik>  $query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (filled($filters['search'] ?? null)) {
            $search = '%'.mb_strtolower(trim((string) $filters['search'])).'%';
            $query->whereHas(
                'anggotaPeriode.santri',
                fn (Builder $query): mixed => $query->whereRaw('LOWER(nama) LIKE ?', [$search])
            );
        }

        if (filled($filters['tanggal'] ?? null)) {
            $query->whereHas(
                'pertemuan',
                fn (Builder $query): mixed => $query->whereDate('tanggal', (string) $filters['tanggal'])
            );
        }

        if (filled($filters['kegiatan_id'] ?? null)) {
            $query->whereHas(
                'pertemuan',
                fn (Builder $query): mixed => $query->where('kegiatan_id', (int) $filters['kegiatan_id'])
            );
        }
    }
}
