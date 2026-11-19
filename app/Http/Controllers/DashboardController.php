<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\RekapKehadiranSik;
use App\Models\Santri;
use App\Models\User;
use App\Services\AttendanceRecapService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AttendanceRecapService $attendanceRecapService) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $role = $user instanceof User ? $user->resolvedRole() : null;

        abort_unless($role instanceof UserRole, 403, 'Peran pengguna tidak dikenali. Hubungi administrator.');

        $view = match ($role) {
            UserRole::Admin => 'dashboards.admin',
            UserRole::Santri => 'dashboards.santri',
            UserRole::InfrastrukturSik => 'dashboards.instruktur',
            UserRole::KaLabMutu => 'dashboards.ka-lab-mutu',
        };

        $viewData = [
            'user' => $user,
            'role' => $role,
        ];

        if ($role === UserRole::Santri && $user instanceof User) {
            $viewData = [...$viewData, ...$this->santriDashboardData($user)];
        }

        if ($role === UserRole::Admin) {
            $viewData = [...$viewData, ...$this->adminDashboardData()];
        }

        if ($role === UserRole::InfrastrukturSik && $user instanceof User) {
            $viewData = [...$viewData, ...$this->infrastrukturDashboardData($user)];
        }

        if ($role === UserRole::KaLabMutu) {
            $viewData = [...$viewData, ...$this->kaLabDashboardData()];
        }

        return view($view, $viewData);
    }

    /**
     * @return array<string, mixed>
     */
    private function santriDashboardData(User $user): array
    {
        $santri = $user->santri;
        $keanggotaan = null;

        if ($santri instanceof Santri) {
            $keanggotaan = $santri->anggotaPeriode()
                ->with(['periode', 'kelompok', 'kelulusan.sertifikat'])
                ->where('status', 'Aktif')
                ->latest('id')
                ->first();

            if ($keanggotaan === null) {
                $keanggotaan = $santri->anggotaPeriode()
                    ->with(['periode', 'kelompok', 'kelulusan.sertifikat'])
                    ->latest('id')
                    ->first();
            }
        }

        $attendanceRecords = $keanggotaan instanceof AnggotaPeriodeSik
            ? $keanggotaan->absensi()->with('pertemuan.kegiatan')->get()
            : collect();
        $recapRows = $keanggotaan instanceof AnggotaPeriodeSik
            ? $this->attendanceRecapService->displayRows(collect([$keanggotaan]))
            : collect();

        $this->attendanceRecapService->prepareDisplay($recapRows);
        $terlaksanaRecords = $attendanceRecords->filter(
            fn (AbsensiSik $attendance): bool => $attendance->pertemuan?->status === 'Terlaksana'
        );

        $statusCounts = [
            'Hadir' => $terlaksanaRecords->where('status', 'Hadir')->count(),
            'Izin' => $terlaksanaRecords->where('status', 'Izin')->count(),
            'Sakit' => $terlaksanaRecords->where('status', 'Sakit')->count(),
            'Alpha' => $terlaksanaRecords->where('status', 'Alpha')->count(),
            'Tidak Lengkap' => $terlaksanaRecords->where('status', 'Tidak Lengkap')->count(),
        ];

        $activityRecaps = $recapRows->map(function (RekapKehadiranSik $recap): array {
            $metric = $recap->getAttribute('attendance_metric');
            $persentase = is_array($metric) && $metric['persentase'] !== null
                ? (float) $metric['persentase']
                : null;

            return [
                'nama_kegiatan' => $recap->kegiatan?->nama_kegiatan ?? 'Kegiatan SIK',
                'total_pertemuan' => (int) $recap->total_pertemuan,
                'jumlah_hadir' => (int) $recap->jumlah_hadir,
                'jumlah_izin' => (int) $recap->jumlah_izin,
                'jumlah_sakit' => (int) $recap->jumlah_sakit,
                'jumlah_alpha' => (int) $recap->jumlah_alpha,
                'persentase' => $persentase,
                'persentase_display' => (string) $recap->persentase_display,
                'status' => (string) $recap->status_kehadiran,
                'memenuhi' => (bool) $recap->memenuhi,
            ];
        })->values();

        $attendancePercentage = $this->attendanceRecapService->averagePercentage($recapRows);

        $orderedAttendance = $terlaksanaRecords->sortBy(function (AbsensiSik $attendance): string {
            return $attendance->pertemuan?->tanggal?->format('Y-m-d')
                ?? $attendance->created_at?->format('Y-m-d')
                ?? '';
        })->values();

        $recentAttendance = $orderedAttendance->reverse()->take(5)->values();
        $attendanceTrend = $this->attendanceTrend($orderedAttendance);
        $kelulusan = $keanggotaan instanceof AnggotaPeriodeSik ? $keanggotaan->kelulusan : null;
        $graduationEvaluation = $this->attendanceRecapService->graduationEvaluation($kelulusan, $keanggotaan);

        return [
            'santri' => $santri,
            'keanggotaan' => $keanggotaan,
            'activityRecaps' => $activityRecaps,
            'attendanceCounts' => $statusCounts,
            'attendancePercentage' => $attendancePercentage,
            'attendancePercentageDisplay' => $this->percentageLabelOrDash($attendancePercentage),
            'attendanceRecordCount' => $terlaksanaRecords->count(),
            'recentAttendance' => $recentAttendance,
            'attendanceTrend' => $attendanceTrend,
            'minimumPercentage' => $this->attendanceRecapService->minimumPercentage(),
            'minimumPercentageDisplay' => $this->percentageLabel($this->attendanceRecapService->minimumPercentage()),
            'noMeetingsStatus' => AttendanceRecapService::STATUS_NO_MEETINGS,
            'kelulusan' => $kelulusan,
            'graduationEvaluation' => $graduationEvaluation,
            'sertifikat' => $kelulusan?->sertifikat,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminDashboardData(): array
    {
        $activePeriod = PeriodeSik::query()
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->first();

        $participantsQuery = AnggotaPeriodeSik::query()
            ->with(['santri', 'kelompok', 'periode'])
            ->where('status', 'Aktif');

        if ($activePeriod instanceof PeriodeSik) {
            $participantsQuery->where('periode_sik_id', $activePeriod->id);
        }

        $recaps = $this->attendanceRecapService->displayRows($participantsQuery->orderBy('id')->get());
        $this->attendanceRecapService->prepareDisplay($recaps);
        $recapsTersedia = $recaps->filter(fn (RekapKehadiranSik $recap): bool => (bool) $recap->tersedia);
        $hadirTotal = (int) $recapsTersedia->sum(fn (RekapKehadiranSik $recap): int => (int) $recap->jumlah_hadir);
        $izinTotal = (int) $recapsTersedia->sum(fn (RekapKehadiranSik $recap): int => (int) $recap->jumlah_izin);
        $sakitTotal = (int) $recapsTersedia->sum(fn (RekapKehadiranSik $recap): int => (int) $recap->jumlah_sakit);
        $alphaTotal = (int) $recapsTersedia->sum(fn (RekapKehadiranSik $recap): int => (int) $recap->jumlah_alpha);
        $tidakLengkapTotal = (int) $recapsTersedia->sum(fn (RekapKehadiranSik $recap): int => (int) $recap->jumlah_tidak_lengkap);
        $totalPertemuan = (int) $recapsTersedia->sum(fn (RekapKehadiranSik $recap): int => (int) $recap->total_pertemuan);
        $tidakHadirTotal = $izinTotal + $sakitTotal + $alphaTotal + $tidakLengkapTotal;

        $trendStart = Carbon::now()->subMonths(5)->startOfMonth()->toDateString();
        $attendanceQuery = AbsensiSik::query()
            ->with(['anggotaPeriode.santri', 'anggotaPeriode.kelompok', 'pertemuan.kegiatan'])
            ->whereHas('pertemuan', function ($query) use ($activePeriod, $trendStart): void {
                $query->where('status', 'Terlaksana')
                    ->where('tanggal', '>=', $trendStart);

                if ($activePeriod instanceof PeriodeSik) {
                    $query->whereHas(
                        'kegiatan',
                        fn ($kegiatanQuery) => $kegiatanQuery->where('periode_sik_id', $activePeriod->id)
                    );
                }
            });

        if ($activePeriod instanceof PeriodeSik) {
            $attendanceQuery->whereHas(
                'anggotaPeriode',
                fn ($query) => $query->where('periode_sik_id', $activePeriod->id)
            );
        }

        $orderedAttendance = $attendanceQuery->get()
            ->sortBy(function (AbsensiSik $attendance): string {
                return $attendance->pertemuan?->tanggal?->format('Y-m-d')
                    ?? $attendance->created_at?->format('Y-m-d')
                    ?? '';
            })
            ->values();

        $attendancePercentage = $this->attendanceRecapService->averagePercentage($recapsTersedia);
        $mahasiswaLulus = $this->attendanceRecapService->graduationEvaluations($activePeriod?->id)
            ->filter(fn (array $row): bool => $row['evaluasi']['code'] === AttendanceRecapService::GRADUATION_PASSED)
            ->count();

        return [
            'dashboardPeriod' => $activePeriod,
            'adminStats' => [
                'totalSantri' => Santri::query()->count(),
                'totalDosen' => InfrastrukturSik::query()->count(),
                'pesertaAktif' => $participantsQuery->distinct('santri_id')->count('santri_id'),
                'mahasiswaLulus' => $mahasiswaLulus,
                'rataKehadiran' => $attendancePercentage,
                'rataKehadiranDisplay' => $this->percentageLabelOrDash($attendancePercentage),
                'totalRekap' => $recaps->count(),
                'memenuhiTarget' => $recaps->where('status_kehadiran', AttendanceRecapService::STATUS_MET)->count(),
                'noMeetings' => $recapsTersedia->isEmpty(),
                'noMeetingsStatus' => AttendanceRecapService::STATUS_NO_MEETINGS,
                'minimumPercentageDisplay' => $this->percentageLabel($this->attendanceRecapService->minimumPercentage()),
            ],
            'adminRecentAttendance' => $orderedAttendance->reverse()->take(6)->values(),
            'adminRecentSantris' => Santri::query()
                ->with('anggotaPeriodeTerbaru.periode')
                ->latest()
                ->take(5)
                ->get(),
            'adminAttendanceTrend' => $this->attendanceTrend($orderedAttendance),
            'adminAttendanceDistribution' => [
                'hadir' => $hadirTotal,
                'izin' => $izinTotal,
                'sakit' => $sakitTotal,
                'alpha' => $alphaTotal,
                'tidakLengkap' => $tidakLengkapTotal,
                'tidakHadir' => $tidakHadirTotal,
                'totalPertemuan' => $totalPertemuan,
                'persentase' => $attendancePercentage,
                'persentaseDisplay' => $this->percentageLabelOrDash($attendancePercentage),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function infrastrukturDashboardData(User $user): array
    {
        $infrastruktur = $user->infrastrukturSik;

        if (! $infrastruktur instanceof InfrastrukturSik) {
            return [
                'infrastrukturStats' => [
                    'totalKelompok' => 0,
                    'pesertaAktif' => 0,
                    'rataKehadiran' => null,
                    'rataKehadiranDisplay' => '—',
                    'totalRekap' => 0,
                    'noMeetings' => true,
                    'noMeetingsStatus' => AttendanceRecapService::STATUS_NO_MEETINGS,
                    'minimumPercentageDisplay' => $this->percentageLabel($this->attendanceRecapService->minimumPercentage()),
                ],
                'infrastrukturAttendanceTrend' => collect(),
                'infrastrukturRecentAttendance' => collect(),
            ];
        }

        $groupIds = Kelompok::query()
            ->where('instruktur_id', $infrastruktur->id)
            ->pluck('id');
        $participants = AnggotaPeriodeSik::query()
            ->with(['santri', 'kelompok', 'periode'])
            ->whereIn('kelompok_id', $groupIds)
            ->where('status', 'Aktif')
            ->orderBy('id')
            ->get();
        $recaps = $this->attendanceRecapService->displayRows($participants);
        $this->attendanceRecapService->prepareDisplay($recaps);
        $recapsTersedia = $recaps->filter(fn (RekapKehadiranSik $recap): bool => (bool) $recap->tersedia);

        $trendStart = Carbon::now()->subMonths(5)->startOfMonth()->toDateString();
        $attendanceQuery = AbsensiSik::query()
            ->with(['anggotaPeriode.santri', 'anggotaPeriode.kelompok', 'pertemuan.kegiatan'])
            ->where(function ($query) use ($infrastruktur): void {
                $query->whereHas(
                    'anggotaPeriode.kelompok',
                    fn ($groupQuery) => $groupQuery->where('instruktur_id', $infrastruktur->id)
                )->orWhereHas('pertemuan', function ($meetingQuery) use ($infrastruktur): void {
                    $meetingQuery->where('instruktur_pengganti_id', $infrastruktur->id)
                        ->orWhereHas(
                            'kelompokHost',
                            fn ($groupQuery) => $groupQuery->where('instruktur_id', $infrastruktur->id)
                        );
                });
            })
            ->whereHas('pertemuan', fn ($query) => $query
                ->where('status', 'Terlaksana')
                ->where('tanggal', '>=', $trendStart));

        $orderedAttendance = $attendanceQuery->get()
            ->sortBy(function (AbsensiSik $attendance): string {
                return $attendance->pertemuan?->tanggal?->format('Y-m-d')
                    ?? $attendance->created_at?->format('Y-m-d')
                    ?? '';
            })
            ->values();

        $rataKehadiran = $this->attendanceRecapService->averagePercentage($recapsTersedia);

        return [
            'infrastrukturStats' => [
                'totalKelompok' => $groupIds->count(),
                'pesertaAktif' => $participants->unique('santri_id')->count(),
                'rataKehadiran' => $rataKehadiran,
                'rataKehadiranDisplay' => $this->percentageLabelOrDash($rataKehadiran),
                'totalRekap' => $recaps->count(),
                'noMeetings' => $recapsTersedia->isEmpty(),
                'noMeetingsStatus' => AttendanceRecapService::STATUS_NO_MEETINGS,
                'minimumPercentageDisplay' => $this->percentageLabel($this->attendanceRecapService->minimumPercentage()),
            ],
            'infrastrukturAttendanceTrend' => $this->attendanceTrend($orderedAttendance),
            'infrastrukturRecentAttendance' => $orderedAttendance->reverse()->take(5)->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kaLabDashboardData(): array
    {
        $annualGraduation = $this->attendanceRecapService->graduationAnnualSummary();
        $currentYear = (int) now()->year;
        $currentYearSummary = $annualGraduation->firstWhere('tahun', $currentYear) ?? [
            'tahun' => $currentYear,
            'total' => 0,
            'lulus' => 0,
            'tidak_lulus' => 0,
            'belum_ditetapkan' => 0,
        ];

        return [
            'graduationCurrentYear' => $currentYear,
            'graduationCurrentSummary' => $currentYearSummary,
            'graduationChartData' => $annualGraduation->slice(-5)->values(),
            'minimumPercentageDisplay' => $this->percentageLabel($this->attendanceRecapService->minimumPercentage()),
        ];
    }

    /**
     * @param  Collection<int, AbsensiSik>  $attendanceRecords
     * @return Collection<int, array{label: string, persentase: float, hadir: int, total: int}>
     */
    private function attendanceTrend(Collection $attendanceRecords): Collection
    {
        return $attendanceRecords
            ->groupBy(function (AbsensiSik $attendance): string {
                return $attendance->pertemuan?->tanggal?->format('Y-m')
                    ?? $attendance->created_at?->format('Y-m')
                    ?? 'unknown';
            })
            ->sortKeys()
            ->map(function (Collection $records, string $month): array {
                $hadir = $records->where('status', 'Hadir')->count();
                $label = $month === 'unknown'
                    ? 'Tanpa tanggal'
                    : Carbon::createFromFormat('Y-m', $month)->locale('id')->translatedFormat('M Y');

                return [
                    'label' => $label,
                    'persentase' => round(($hadir / max(1, $records->count())) * 100, 1),
                    'hadir' => $hadir,
                    'total' => $records->count(),
                ];
            })
            ->slice(-6)
            ->values();
    }

    private function percentageLabel(float $percentage): string
    {
        return $this->attendanceRecapService->formatPercentage($percentage).'%';
    }

    private function percentageLabelOrDash(?float $percentage): string
    {
        return $percentage === null ? '—' : $this->percentageLabel($percentage);
    }
}
