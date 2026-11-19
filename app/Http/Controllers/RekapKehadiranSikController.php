<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kelompok;
use App\Models\KelulusanSik;
use App\Models\PeriodeSik;
use App\Models\RekapKehadiranSik;
use App\Models\User;
use App\Services\AttendanceRecapService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekapKehadiranSikController extends Controller
{
    public function __construct(private readonly AttendanceRecapService $attendanceRecapService) {}

    /**
     * Menampilkan rekap kehadiran seluruh anggota aktif yang menjadi Tanggung
     * jawab instruktur. Baris dibuat dari keanggotaan, bukan dari cache rekap,
     * sehingga anggota tanpa absensi tetap tampil.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $isAdmin = $user->role === UserRole::Admin;
        $filters = $this->validateFilters($request, $isAdmin);
        $filterInstructor = null;
        $selectedPeriodId = filled($filters['periode_sik_id'] ?? null)
            ? (int) $filters['periode_sik_id']
            : null;
        $selectedGroupId = filled($filters['kelompok_id'] ?? null)
            ? (int) $filters['kelompok_id']
            : null;
        $anggota = $this->filteredAnggotaQuery($user, $filters, $filterInstructor)->get();
        $rekap = $this->attendanceRecapService->displayRows($anggota);

        $this->attendanceRecapService->prepareDisplay($rekap);

        $periodsQuery = PeriodeSik::query()->orderByDesc('tahun')->orderByDesc('id');
        if ($filterInstructor instanceof InfrastrukturSik) {
            $periodsQuery->whereHas(
                'kelompok',
                fn ($query) => $query->where('instruktur_id', $filterInstructor->id)
            );
        }

        $groupsQuery = Kelompok::query()->orderBy('nama_kelompok')->orderBy('id');
        if ($filterInstructor instanceof InfrastrukturSik) {
            $groupsQuery->where('instruktur_id', $filterInstructor->id);
        }
        if ($selectedPeriodId !== null) {
            $groupsQuery->where('periode_sik_id', $selectedPeriodId);
        }

        $selectedInstructor = $isAdmin ? $filterInstructor : null;
        $periods = $periodsQuery->get();
        $groups = $groupsQuery->get();
        $instructors = InfrastrukturSik::query()->with('user')->orderBy('nama')->get();

        return view('rekap-kehadiran-sik.index', compact(
            'rekap',
            'isAdmin',
            'selectedInstructor',
            'periods',
            'selectedPeriodId',
            'groups',
            'selectedGroupId',
            'instructors'
        ))->with([
            'minimumPercentage' => $this->attendanceRecapService->minimumPercentage(),
            'minimumPercentageDisplay' => $this->percentageLabel($this->attendanceRecapService->minimumPercentage()),
            'rataKehadiran' => $this->attendanceRecapService->averagePercentage($rekap),
            'rataKehadiranDisplay' => $this->percentageLabelOrDash($this->attendanceRecapService->averagePercentage($rekap)),
            'noMeetingsStatus' => AttendanceRecapService::STATUS_NO_MEETINGS,
            'noAttendanceStatus' => AttendanceRecapService::STATUS_NO_ATTENDANCE,
            'filterQuery' => $this->filterQuery($request, $isAdmin),
        ]);
    }

    /**
     * Detail untuk baris rekap tersimpan. Angka tetap dihitung ulang dari
     * absensi sehingga tidak bergantung pada cache lama.
     */
    public function show(Request $request, RekapKehadiranSik $rekapKehadiranSik): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $isAdmin = $user->role === UserRole::Admin;
        $filters = $this->validateFilters($request, $isAdmin);

        return $this->renderDetail(
            $request,
            $filters,
            (int) $rekapKehadiranSik->anggota_periode_sik_id,
            $rekapKehadiranSik->kegiatan_id === null ? null : (int) $rekapKehadiranSik->kegiatan_id
        );
    }

    /**
     * Detail untuk baris yang dibuat dari data keanggotaan, termasuk anggota
     * yang belum memiliki baris rekap tersimpan.
     */
    public function detail(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $isAdmin = $user->role === UserRole::Admin;
        $filters = $this->validateFilters($request, $isAdmin);
        $validated = $request->validate([
            'anggota_periode_sik_id' => ['required', 'integer', Rule::exists('anggota_periode_sik', 'id')],
            'kegiatan_id' => ['nullable', 'integer', Rule::exists('kegiatan', 'id')],
        ]);

        return $this->renderDetail(
            $request,
            $filters,
            (int) $validated['anggota_periode_sik_id'],
            filled($validated['kegiatan_id'] ?? null) ? (int) $validated['kegiatan_id'] : null
        );
    }

    /** Download an Excel-compatible CSV using the same role and period scope as the recap page. */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $isAdmin = $user->role === UserRole::Admin;
        $filters = $this->validateFilters($request, $isAdmin);
        $filterInstructor = null;
        $query = $this->filteredAnggotaQuery($user, $filters, $filterInstructor);
        $recapService = $this->attendanceRecapService;

        return response()->streamDownload(function () use ($query, $recapService): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'No', 'Nomor Induk', 'Nama Santri', 'Periode', 'Kelompok', 'Kegiatan',
                'Total Pertemuan', 'Hadir', 'Izin', 'Sakit', 'Alpha', 'Tidak Lengkap',
                'Persentase Kehadiran', 'Status',
            ]);

            $rowNumber = 0;
            $query->reorder()->orderBy('id')->chunkById(500, function ($members) use ($stream, $recapService, &$rowNumber): void {
                $items = $recapService->displayRows($members);
                $recapService->prepareDisplay($items);

                foreach ($items as $item) {
                    $rowNumber++;
                    $santri = $item->anggotaPeriode?->santri;
                    $anggota = $item->anggotaPeriode;
                    $values = [
                        $rowNumber,
                        $this->spreadsheetSafeCell($santri?->nomor_induk ?? ''),
                        $this->spreadsheetSafeCell($santri?->nama ?? ''),
                        $this->spreadsheetSafeCell($anggota?->periode?->nama_periode ?? ''),
                        $this->spreadsheetSafeCell($anggota?->kelompok?->nama_kelompok ?? ''),
                        $this->spreadsheetSafeCell($item->kegiatan?->nama_kegiatan ?? ''),
                        (int) $item->total_pertemuan,
                        (int) $item->jumlah_hadir,
                        (int) $item->jumlah_izin,
                        (int) $item->jumlah_sakit,
                        (int) $item->jumlah_alpha,
                        (int) $item->jumlah_tidak_lengkap,
                        (string) $item->persentase_display,
                        (string) $item->status_kehadiran,
                    ];

                    fputcsv($stream, $values);
                }
            });

            fclose($stream);
        }, 'rekap-kehadiran-sik-'.now()->format('Ymd').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Menampilkan laporan SIK untuk Ka-Lab Mutu. Seluruh status kelulusan
     * berasal dari evaluasi AttendanceRecapService, bukan dari catatan lama.
     */
    public function laporan(): View
    {
        $kelulusanTahunan = $this->attendanceRecapService->graduationAnnualSummary()
            ->map(function (array $row): array {
                $persentase = $row['total'] > 0
                    ? ($row['lulus'] / $row['total']) * 100
                    : null;

                return [
                    ...$row,
                    'persentase_lulus' => $persentase,
                    'persentase_lulus_display' => $this->percentageLabelOrDash($persentase),
                ];
            });
        $detailKelulusan = $this->attendanceRecapService->graduationEvaluations()
            ->map(function (array $row): KelulusanSik {
                $detail = $row['kelulusan'];
                $evaluasi = $row['evaluasi'];
                $metric = $evaluasi['metric'];

                $detail->setAttribute('attendance', $metric);
                $detail->setAttribute('evaluasi', $evaluasi);
                $detail->setAttribute('evaluasi_label', $evaluasi['label']);
                $detail->setAttribute('evaluasi_badge_class', match ($evaluasi['code']) {
                    AttendanceRecapService::GRADUATION_PASSED => 'bg-success',
                    AttendanceRecapService::GRADUATION_FAILED => 'bg-danger',
                    default => 'bg-secondary',
                });
                $detail->setAttribute('persentase_kehadiran_display', $metric['persentase'] === null
                    ? (string) $metric['status']
                    : $this->percentageLabel((float) $metric['persentase']));
                $detail->setAttribute('alasan_kehadiran', $evaluasi['alasan']);

                return $detail;
            });

        $totalPeserta = (int) $kelulusanTahunan->sum('total');
        $totalLulus = (int) $kelulusanTahunan->sum('lulus');
        $persentaseKelulusan = $totalPeserta > 0
            ? ($totalLulus / $totalPeserta) * 100
            : null;

        return view('laporan-sik.index', [
            'kelulusanTahunan' => $kelulusanTahunan,
            'detailKelulusan' => $detailKelulusan,
            'minimumPercentage' => $this->attendanceRecapService->minimumPercentage(),
            'minimumPercentageDisplay' => $this->percentageLabel($this->attendanceRecapService->minimumPercentage()),
            'totalPeserta' => $totalPeserta,
            'totalLulus' => $totalLulus,
            'persentaseKelulusan' => $persentaseKelulusan,
            'persentaseKelulusanDisplay' => $this->attendanceRecapService->formatPercentage($persentaseKelulusan),
            'persentaseKelulusanLabel' => $this->percentageLabelOrDash($persentaseKelulusan),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateFilters(Request $request, bool $isAdmin): array
    {
        $rules = [
            'periode_sik_id' => ['nullable', 'integer', Rule::exists('periode_sik', 'id')],
            'kelompok_id' => ['nullable', 'integer', Rule::exists('kelompok', 'id')],
        ];
        if ($isAdmin) {
            $rules['instruktur_id'] = ['nullable', 'integer', Rule::exists('infrastruktur_siks', 'id')];
        }

        return $request->validate($rules);
    }

    /** @param array<string, mixed> $filters */
    private function filteredAnggotaQuery(User $user, array $filters, ?InfrastrukturSik &$filterInstructor): Builder
    {
        if ($user->role === UserRole::Admin) {
            $filterInstructor = filled($filters['instruktur_id'] ?? null)
                ? InfrastrukturSik::with('user')->findOrFail((int) $filters['instruktur_id'])
                : null;
        } else {
            $filterInstructor = $user->infrastrukturSik;
            abort_unless($filterInstructor instanceof InfrastrukturSik, 403, 'Data instruktur tidak ditemukan.');
        }

        $query = AnggotaPeriodeSik::query()
            ->with(['santri', 'kelompok', 'periode'])
            ->where('status', 'Aktif');

        if ($filterInstructor instanceof InfrastrukturSik) {
            $query->whereHas(
                'kelompok',
                fn ($groupQuery) => $groupQuery->where('instruktur_id', $filterInstructor->id)
            );
        }

        if (filled($filters['periode_sik_id'] ?? null)) {
            $query->where('periode_sik_id', (int) $filters['periode_sik_id']);
        }

        if (filled($filters['kelompok_id'] ?? null)) {
            $query->where('kelompok_id', (int) $filters['kelompok_id']);
        }

        return $query->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function renderDetail(Request $request, array $filters, int $anggotaId, ?int $kegiatanId): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $filterInstructor = null;
        $anggota = $this->filteredAnggotaQuery($user, $filters, $filterInstructor)
            ->whereKey($anggotaId)
            ->firstOrFail();
        $item = $this->attendanceRecapService->displayRowForAnggota($anggota, $kegiatanId);
        abort_if($item === null, 404);

        $this->attendanceRecapService->prepareDisplay([$item]);

        $riwayat = $this->attendanceRecapService->pertemuanForAnggota($anggota, $kegiatanId);

        foreach ($riwayat as $pertemuan) {
            $pertemuan->setAttribute(
                'absensi_anggota',
                $pertemuan->absensi->firstWhere('anggota_periode_sik_id', (int) $anggota->id)
            );
        }

        return view('rekap-kehadiran-sik.show', compact('item', 'riwayat'))->with([
            'minimumPercentage' => $this->attendanceRecapService->minimumPercentage(),
            'minimumPercentageDisplay' => $this->percentageLabel($this->attendanceRecapService->minimumPercentage()),
            'noMeetingsStatus' => AttendanceRecapService::STATUS_NO_MEETINGS,
            'noAttendanceStatus' => AttendanceRecapService::STATUS_NO_ATTENDANCE,
            'backQuery' => $this->filterQuery($request, $user->role === UserRole::Admin),
        ]);
    }

    /** @return array<string, int|string> */
    private function filterQuery(Request $request, bool $isAdmin): array
    {
        $keys = $isAdmin
            ? ['periode_sik_id', 'kelompok_id', 'instruktur_id']
            : ['periode_sik_id', 'kelompok_id'];

        return array_filter(
            $request->only($keys),
            fn ($value): bool => filled($value)
        );
    }

    private function percentageLabel(float $percentage): string
    {
        return $this->attendanceRecapService->formatPercentage($percentage).'%';
    }

    private function percentageLabelOrDash(?float $percentage): string
    {
        return $percentage === null ? '—' : $this->percentageLabel($percentage);
    }

    private function spreadsheetSafeCell(string $value): string
    {
        return preg_match('/^[=+@\-\t\r]/u', $value) === 1 ? "'".$value : $value;
    }
}
