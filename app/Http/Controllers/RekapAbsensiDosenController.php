<?php

namespace App\Http\Controllers;

use App\Models\AbsensiDosen;
use App\Models\InfrastrukturSik;
use App\Models\PeriodeSik;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekapAbsensiDosenController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validateFilters($request);
        $period = $this->selectedPeriod($filters);
        $instructors = InfrastrukturSik::query()->with('user')->orderBy('nama')->get();

        if (! Schema::hasTable('absensi_dosen')) {
            return view('rekap-absensi-dosen.index', [
                'tableAvailable' => false,
                'filters' => $filters,
                'periods' => PeriodeSik::query()->orderByDesc('tanggal_mulai')->get(),
                'instructors' => $instructors,
                'activeInstructors' => $instructors->where('status', 'Aktif'),
            ]);
        }

        $profiles = $filters['infrastruktur_sik_id']
            ? $instructors->where('id', (int) $filters['infrastruktur_sik_id'])
            : $instructors;
        $summaryQuery = AbsensiDosen::query();
        $this->applyFilters($summaryQuery, $filters, $period);
        $summaries = $summaryQuery
            ->selectRaw('infrastruktur_sik_id, COUNT(*) as total_pertemuan')
            ->selectRaw("SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as total_hadir")
            ->groupBy('infrastruktur_sik_id')
            ->get()
            ->keyBy('infrastruktur_sik_id');

        $profiles->each(function (InfrastrukturSik $profile) use ($summaries): void {
            $summary = $summaries->get($profile->id);
            $meetings = (int) ($summary?->total_pertemuan ?? 0);
            $present = (int) ($summary?->total_hadir ?? 0);

            $profile->setAttribute('attendance_meetings', $meetings);
            $profile->setAttribute('attendance_present', $present);
            $profile->setAttribute('attendance_absent', max(0, $meetings - $present));
            $profile->setAttribute('attendance_percentage', $meetings > 0 ? round(($present / $meetings) * 100, 1) : 0.0);
        });

        $totalMeetings = (int) $summaries->sum('total_pertemuan');
        $totalPresent = (int) $summaries->sum('total_hadir');
        $recordsQuery = AbsensiDosen::query()->with('instruktur.user');
        $this->applyFilters($recordsQuery, $filters, $period);

        return view('rekap-absensi-dosen.index', [
            'tableAvailable' => true,
            'filters' => $filters,
            'periods' => PeriodeSik::query()->orderByDesc('tanggal_mulai')->get(),
            'instructors' => $instructors,
            'activeInstructors' => $instructors->where('status', 'Aktif'),
            'profiles' => $profiles,
            'absensi' => $recordsQuery->latest('tanggal')->latest('id')->paginate(15)->withQueryString(),
            'totalMeetings' => $totalMeetings,
            'totalPresent' => $totalPresent,
            'totalAbsent' => max(0, $totalMeetings - $totalPresent),
            'attendancePercentage' => $totalMeetings > 0 ? round(($totalPresent / $totalMeetings) * 100, 1) : 0.0,
            'period' => $period,
        ]);
    }

    public function show(Request $request, InfrastrukturSik $infrastrukturSik): View
    {
        $filters = $this->validateFilters($request, false);
        $period = $this->selectedPeriod($filters);
        $query = AbsensiDosen::query()->where('infrastruktur_sik_id', $infrastrukturSik->id);
        $this->applyFilters($query, $filters, $period);

        if (! Schema::hasTable('absensi_dosen')) {
            return view('rekap-absensi-dosen.show', [
                'tableAvailable' => false,
                'instructor' => $infrastrukturSik->load('user'),
                'filters' => $filters,
                'periods' => PeriodeSik::query()->orderByDesc('tanggal_mulai')->get(),
            ]);
        }

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total_pertemuan')
            ->selectRaw("SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as total_hadir")
            ->first();
        $meetings = (int) ($summary?->total_pertemuan ?? 0);
        $present = (int) ($summary?->total_hadir ?? 0);

        return view('rekap-absensi-dosen.show', [
            'tableAvailable' => true,
            'instructor' => $infrastrukturSik->load('user'),
            'filters' => $filters,
            'periods' => PeriodeSik::query()->orderByDesc('tanggal_mulai')->get(),
            'absensi' => $query->with('pencatat')->latest('tanggal')->latest('id')->paginate(20)->withQueryString(),
            'totalMeetings' => $meetings,
            'totalPresent' => $present,
            'totalAbsent' => max(0, $meetings - $present),
            'attendancePercentage' => $meetings > 0 ? round(($present / $meetings) * 100, 1) : 0.0,
        ]);
    }

    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        if (! Schema::hasTable('absensi_dosen')) {
            return redirect()->route('rekap-absensi-dosen.index')
                ->with('error', 'Rekap absensi belum tersedia sebelum migration absensi dosen dijalankan.');
        }

        $filters = $this->validateFilters($request);
        $period = $this->selectedPeriod($filters);
        $query = AbsensiDosen::query()->with('instruktur.user');
        $this->applyFilters($query, $filters, $period);
        $filename = 'rekap-absensi-dosen-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Nama Dosen', 'NIP', 'Tanggal', 'Jam Masuk', 'Jam Pulang', 'Status', 'Keterangan']);

            foreach ($query->orderBy('tanggal')->orderBy('infrastruktur_sik_id')->cursor() as $attendance) {
                fputcsv($output, [
                    $this->safeSpreadsheetCell($attendance->instruktur?->nama ?? $attendance->instruktur?->user?->name ?? 'Dosen'),
                    $this->safeSpreadsheetCell($attendance->instruktur?->nip ?? ''),
                    $attendance->tanggal?->format('Y-m-d') ?? '',
                    $attendance->waktu_masuk ?? '',
                    $attendance->waktu_pulang ?? '',
                    $this->safeSpreadsheetCell($attendance->status),
                    $this->safeSpreadsheetCell($attendance->keterangan ?? ''),
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('absensi_dosen')) {
            return back()->with('error', 'Rekap absensi dosen belum tersedia sebelum migration absensi dosen dijalankan.');
        }

        $validated = $request->validate([
            'infrastruktur_sik_id' => [
                'required',
                Rule::exists('infrastruktur_siks', 'id')->where('status', 'Aktif'),
            ],
            'tanggal' => ['required', 'date'],
            'waktu_masuk' => ['nullable', 'date_format:H:i'],
            'waktu_pulang' => ['nullable', 'date_format:H:i', 'after_or_equal:waktu_masuk'],
            'status' => ['required', Rule::in(['Hadir', 'Izin', 'Sakit', 'Alpha'])],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $alreadyRecorded = AbsensiDosen::query()
            ->where('infrastruktur_sik_id', $validated['infrastruktur_sik_id'])
            ->whereDate('tanggal', $validated['tanggal'])
            ->exists();

        if ($alreadyRecorded) {
            return back()->withInput()->withErrors([
                'tanggal' => 'Absensi dosen ini sudah dicatat untuk tanggal tersebut.',
            ]);
        }

        try {
            AbsensiDosen::create([
                ...$validated,
                'dicatat_oleh' => $request->user()->id,
            ]);
        } catch (QueryException) {
            return back()->withInput()->withErrors([
                'tanggal' => 'Absensi dosen ini sudah tercatat. Muat ulang halaman lalu periksa rekapnya.',
            ]);
        }

        return redirect()->route('rekap-absensi-dosen.index', [
            'infrastruktur_sik_id' => $validated['infrastruktur_sik_id'],
        ])->with('success', 'Absensi dosen berhasil dicatat.');
    }

    /** @return array{periode_sik_id: int|null, infrastruktur_sik_id: int|null} */
    private function validateFilters(Request $request, bool $includeInstructor = true): array
    {
        $rules = [
            'periode_sik_id' => ['nullable', 'integer', 'exists:periode_sik,id'],
        ];

        if ($includeInstructor) {
            $rules['infrastruktur_sik_id'] = ['nullable', 'integer', 'exists:infrastruktur_siks,id'];
        }

        $filters = $request->validate($rules);

        return [
            'periode_sik_id' => isset($filters['periode_sik_id']) ? (int) $filters['periode_sik_id'] : null,
            'infrastruktur_sik_id' => isset($filters['infrastruktur_sik_id']) ? (int) $filters['infrastruktur_sik_id'] : null,
        ];
    }

    /** @param array{periode_sik_id: int|null, infrastruktur_sik_id: int|null} $filters */
    private function selectedPeriod(array $filters): ?PeriodeSik
    {
        return $filters['periode_sik_id'] !== null
            ? PeriodeSik::query()->find($filters['periode_sik_id'])
            : null;
    }

    /** @param array{periode_sik_id: int|null, infrastruktur_sik_id: int|null} $filters */
    private function applyFilters(Builder $query, array $filters, ?PeriodeSik $period, ?int $instructorId = null): void
    {
        if ($period !== null) {
            $query->whereBetween('tanggal', [
                $period->tanggal_mulai->toDateString(),
                $period->tanggal_selesai->toDateString(),
            ]);
        }

        $selectedInstructorId = $instructorId ?? $filters['infrastruktur_sik_id'];

        if ($selectedInstructorId !== null) {
            $query->where('infrastruktur_sik_id', $selectedInstructorId);
        }
    }

    private function safeSpreadsheetCell(string $value): string
    {
        return preg_match('/^\s*[=+@\-]/u', $value) === 1 ? "'".$value : $value;
    }
}
