<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\InfrastrukturSik;
use App\Models\KelulusanSik;
use App\Models\PeriodeSik;
use App\Models\RekapKehadiranSik;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekapKehadiranSikController extends Controller
{
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

        $totalPertemuan = (int) config('sik.attendance.meeting_count', 16);
        $minimumHadir = (int) config('sik.attendance.minimum_present', 14);

        $rekap->each(function (RekapKehadiranSik $item) use ($totalPertemuan, $minimumHadir): void {
            $jumlahHadir = min((int) $item->jumlah_hadir, $totalPertemuan);
            $item->setAttribute('total_pertemuan', $totalPertemuan);
            $item->setAttribute(
                'persentase_kehadiran',
                round(($jumlahHadir / $totalPertemuan) * 100, 2)
            );
            $item->setAttribute(
                'status_kehadiran',
                (int) $item->jumlah_hadir >= $minimumHadir ? 'Memenuhi' : 'Belum Memenuhi'
            );

            $activityAttendance = $item->anggotaPeriode?->absensi
                ->filter(fn (AbsensiSik $attendance): bool => (int) $attendance->pertemuan?->kegiatan_id === (int) $item->kegiatan_id)
                ->sortBy(fn (AbsensiSik $attendance): string => sprintf(
                    '%s-%04d',
                    $attendance->pertemuan?->tanggal?->format('Y-m-d') ?? '0000-00-00',
                    (int) ($attendance->pertemuan?->pertemuan_ke ?? 0)
                ))
                ->values();

            foreach (['waktu_masuk' => 'jam_datang', 'waktu_pulang' => 'jam_pulang'] as $field => $attribute) {
                $lastRecorded = $activityAttendance
                    ->filter(fn (AbsensiSik $attendance): bool => filled($attendance->{$field}))
                    ->last();
                $item->setAttribute($attribute, $lastRecorded?->{$field});
                $item->setAttribute($attribute.'_pertemuan', $lastRecorded?->pertemuan?->pertemuan_ke);
            }
        });

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
        ));
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
        $meetingCount = max(1, (int) config('sik.attendance.meeting_count', 16));
        $minimumPresent = max(1, (int) config('sik.attendance.minimum_present', 14));

        return response()->streamDownload(function () use ($query, $meetingCount, $minimumPresent): void {
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
            $query->reorder()->orderBy('id')->chunkById(500, function ($items) use ($stream, $meetingCount, $minimumPresent, &$rowNumber): void {
                foreach ($items as $item) {
                    $rowNumber++;
                    $santri = $item->anggotaPeriode?->santri;
                    $anggota = $item->anggotaPeriode;
                    $jumlahHadir = min((int) $item->jumlah_hadir, $meetingCount);
                    $status = (int) $item->jumlah_hadir >= $minimumPresent ? 'Memenuhi' : 'Belum Memenuhi';
                    $values = [
                        $rowNumber,
                        $this->spreadsheetSafeCell($santri?->nomor_induk ?? ''),
                        $this->spreadsheetSafeCell($santri?->nama ?? ''),
                        $this->spreadsheetSafeCell($anggota?->periode?->nama_periode ?? ''),
                        $this->spreadsheetSafeCell($anggota?->kelompok?->nama_kelompok ?? ''),
                        $this->spreadsheetSafeCell($item->kegiatan?->nama_kegiatan ?? ''),
                        $meetingCount,
                        $jumlahHadir,
                        (int) $item->jumlah_izin,
                        (int) $item->jumlah_sakit,
                        (int) $item->jumlah_alpha,
                        (int) $item->jumlah_tidak_lengkap,
                        number_format(($jumlahHadir / $meetingCount) * 100, 2, '.', '').'%',
                        $status,
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

    // Syarat minimal kehadiran dari konfigurasi SIK
    $minimumHadir = (int) config('sik.attendance.minimum_present');

    $totalPeserta = (int) $kelulusanTahunan->sum('total_kelulusan');
    $totalLulus = (int) $kelulusanTahunan->sum('jumlah_lulus');

    $persentaseKelulusan = $totalPeserta > 0
        ? round(($totalLulus / $totalPeserta) * 100, 2)
        : 0;

    return view('laporan-sik.index', compact(
        'kelulusanTahunan',
        'detailKelulusan',
        'minimumHadir',
        'totalPeserta',
        'totalLulus',
        'persentaseKelulusan'
    ));
}

}
