<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\InfrastrukturSik;
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
    public function __construct(private readonly AttendanceRecapService $attendanceRecapService)
    {
    }

    /**
     * Menampilkan rekap kehadiran santri untuk instruktur SIK.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $isAdmin = $user->role === UserRole::Admin;
        $filters = $this->validateFilters($request, $isAdmin);
        $filterInstructor = null;
        $rekapQuery = $this->filteredRecapQuery($user, $filters, $filterInstructor);
        $selectedInstructor = $isAdmin ? $filterInstructor : null;
        $rekap = $rekapQuery->get();

        $this->attendanceRecapService->prepareDisplay($rekap);

        $periodsQuery = PeriodeSik::query()->orderByDesc('tahun')->orderByDesc('id');
        if ($filterInstructor instanceof InfrastrukturSik) {
            $periodsQuery->whereHas('kelompok', fn ($query) => $query->where('instruktur_id', $filterInstructor->id));
        }

        $periods = $periodsQuery->get();
        $selectedPeriodId = filled($filters['periode_sik_id'] ?? null)
            ? (int) $filters['periode_sik_id']
            : null;

        return view('rekap-kehadiran-sik.index', compact(
            'rekap',
            'isAdmin',
            'selectedInstructor',
            'periods',
            'selectedPeriodId'
        ))->with([
            'minimumPercentage' => $this->attendanceRecapService->minimumPercentage(),
            'rataKehadiran' => $this->attendanceRecapService->averagePercentage($rekap),
            'noMeetingsStatus' => AttendanceRecapService::STATUS_NO_MEETINGS,
        ]);
    }

    /**
     * Menampilkan rincian kehadiran satu santri untuk satu kegiatan.
     *
     * Baris rekap dicari lagi melalui query yang sama dengan halaman indeks
     * sehingga instruktur tidak dapat membuka rekap di luar kelompok binaannya.
     */
    public function show(Request $request, RekapKehadiranSik $rekapKehadiranSik): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $filterInstructor = null;
        $item = $this->filteredRecapQuery($user, [], $filterInstructor)
            ->whereKey($rekapKehadiranSik->id)
            ->firstOrFail();

        $this->attendanceRecapService->prepareDisplay([$item]);

        $kegiatanId = (int) $item->kegiatan_id;
        $riwayat = $item->anggotaPeriode?->absensi
            ->filter(fn ($attendance): bool => (int) $attendance->pertemuan?->kegiatan_id === $kegiatanId)
            ->sortBy(fn ($attendance): string => sprintf(
                '%s-%04d',
                $attendance->pertemuan?->tanggal?->format('Y-m-d') ?? '0000-00-00',
                (int) ($attendance->pertemuan?->pertemuan_ke ?? 0)
            ))
            ->values() ?? collect();

        return view('rekap-kehadiran-sik.show', compact('item', 'riwayat'))->with([
            'minimumPercentage' => $this->attendanceRecapService->minimumPercentage(),
            'noMeetingsStatus' => AttendanceRecapService::STATUS_NO_MEETINGS,
        ]);
    }

    /** Download an Excel-compatible CSV using the same role and period scope as the recap page. */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $isAdmin = $user->role === UserRole::Admin;
        $filters = $this->validateFilters($request, $isAdmin);
        $filterInstructor = null;
        $query = $this->filteredRecapQuery($user, $filters, $filterInstructor);
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
            $query->reorder()->orderBy('id')->chunkById(500, function ($items) use ($stream, $recapService, &$rowNumber): void {
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

    /** @return array<string, mixed> */
    private function validateFilters(Request $request, bool $isAdmin): array
    {
        $rules = [
            'periode_sik_id' => ['nullable', 'integer', Rule::exists('periode_sik', 'id')],
        ];
        if ($isAdmin) {
            $rules['instruktur_id'] = ['nullable', 'integer', Rule::exists('infrastruktur_siks', 'id')];
        }

        return $request->validate($rules);
    }

    /** @param array<string, mixed> $filters */
    private function filteredRecapQuery(User $user, array $filters, ?InfrastrukturSik &$filterInstructor): Builder
    {
        if ($user->role === UserRole::Admin) {
            $filterInstructor = filled($filters['instruktur_id'] ?? null)
                ? InfrastrukturSik::with('user')->findOrFail((int) $filters['instruktur_id'])
                : null;
        } else {
            $filterInstructor = $user->infrastrukturSik;
            abort_unless($filterInstructor instanceof InfrastrukturSik, 403, 'Data instruktur tidak ditemukan.');
        }

        $query = RekapKehadiranSik::query()->with([
            'anggotaPeriode.santri',
            'anggotaPeriode.kelompok',
            'anggotaPeriode.periode',
            'anggotaPeriode.absensi.pertemuan',
            'kegiatan',
        ])->latest();

        if ($filterInstructor instanceof InfrastrukturSik) {
            $query->whereHas('anggotaPeriode.kelompok', fn ($groupQuery) => $groupQuery->where('instruktur_id', $filterInstructor->id));
        }

        if (filled($filters['periode_sik_id'] ?? null)) {
            $query->whereHas('anggotaPeriode', fn ($membershipQuery) => $membershipQuery->where('periode_sik_id', (int) $filters['periode_sik_id']));
        }

        return $query;
    }

    private function spreadsheetSafeCell(string $value): string
    {
        return preg_match('/^[=+@\-\t\r]/u', $value) === 1 ? "'".$value : $value;
    }

    /**
     * Menampilkan laporan SIK untuk Ka-Lab Mutu.
     * Ka-Lab Mutu hanya memiliki akses melihat laporan.
     */
    public function laporan(): View
    {
        // Rekap kelulusan per tahun
        $kelulusanTahunan = KelulusanSik::query()
            ->join(
                'anggota_periode_sik as anggota',
                'anggota.id',
                '=',
                'kelulusan_sik.anggota_periode_sik_id'
            )
            ->join(
                'periode_sik as periode',
                'periode.id',
                '=',
                'anggota.periode_sik_id'
            )
            ->select('periode.tahun')
            ->selectRaw('COUNT(*) as total_kelulusan')
            ->selectRaw("SUM(CASE WHEN kelulusan_sik.status = 'Lulus' THEN 1 ELSE 0 END) as jumlah_lulus")
            ->selectRaw("SUM(CASE WHEN kelulusan_sik.status = 'Tidak Lulus' THEN 1 ELSE 0 END) as jumlah_tidak_lulus")
            ->selectRaw("SUM(CASE WHEN kelulusan_sik.status = 'Belum Lulus' THEN 1 ELSE 0 END) as jumlah_belum_ditetapkan")
            ->groupBy('periode.tahun')
            ->orderByDesc('periode.tahun')
            ->get();

        // Detail evaluasi kelulusan setiap santri
        $detailKelulusan = KelulusanSik::with([
            'anggotaPeriodeSik.santri',
            'anggotaPeriodeSik.periode',
            'anggotaPeriodeSik.kelompok',
            'anggotaPeriodeSik.absensi',
        ])
            ->latest()
            ->get();

        // Seluruh angka kehadiran berasal dari AttendanceRecapService.
        $detailKelulusan->each(function (KelulusanSik $detail): void {
            $metric = $this->attendanceRecapService->calculateForAnggota(
                (int) $detail->anggota_periode_sik_id
            );

            $detail->setAttribute('attendance', $metric);
            $detail->setAttribute('alasan_kehadiran', $metric['tersedia']
                ? ($metric['memenuhi']
                    ? 'Memenuhi ambang kehadiran '.number_format($this->attendanceRecapService->minimumPercentage(), 1, ',', '.').'% pada pertemuan Terlaksana.'
                    : 'Belum memenuhi ambang kehadiran '.number_format($this->attendanceRecapService->minimumPercentage(), 1, ',', '.').'% pada pertemuan Terlaksana.')
                : AttendanceRecapService::STATUS_NO_MEETINGS);
        });

        $minimumPercentage = $this->attendanceRecapService->minimumPercentage();

        $totalPeserta = (int) $kelulusanTahunan->sum('total_kelulusan');
        $totalLulus = (int) $kelulusanTahunan->sum('jumlah_lulus');

        $persentaseKelulusan = $totalPeserta > 0
            ? round(($totalLulus / $totalPeserta) * 100, 2)
            : 0;

        return view('laporan-sik.index', compact(
            'kelulusanTahunan',
            'detailKelulusan',
            'minimumPercentage',
            'totalPeserta',
            'totalLulus',
            'persentaseKelulusan'
        ));
    }
}
