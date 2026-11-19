<?php

namespace App\Http\Controllers;

use App\Models\AnggotaPeriodeSik;
use App\Models\KelulusanSik;
use App\Services\AttendanceRecapService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KelulusanSikController extends Controller
{
    public function __construct(private readonly AttendanceRecapService $attendanceRecapService) {}

    public function index(): View
    {
        $kelulusan = KelulusanSik::with([
            'anggotaPeriodeSik.santri',
            'anggotaPeriodeSik.periode',
            'anggotaPeriodeSik.kelompok',
        ])->latest()->orderByDesc('id')->paginate(10);

        $kelulusan->getCollection()->transform(function (KelulusanSik $item): KelulusanSik {
            return $this->decorateGraduation($item);
        });

        return view('kelulusan-sik.index', compact('kelulusan'));
    }

    public function create(): View
    {
        $anggota = AnggotaPeriodeSik::with(['santri', 'periode', 'kelompok'])
            ->whereDoesntHave('kelulusan')
            ->where('status', 'Aktif')
            ->orderBy('id')
            ->get()
            ->map(function (AnggotaPeriodeSik $member): AnggotaPeriodeSik {
                return $this->decorateAnggota($member);
            });

        return view('kelulusan-sik.create', compact('anggota'));
    }

    public function store(Request $request): RedirectResponse
    {
        KelulusanSik::create($this->validatedData($request));

        return redirect()->route('kelulusan-sik.index')
            ->with('success', 'Data kelulusan berhasil disimpan.');
    }

    public function edit(KelulusanSik $kelulusanSik): View
    {
        $anggota = AnggotaPeriodeSik::with(['santri', 'periode', 'kelompok'])
            ->where(function ($query) use ($kelulusanSik): void {
                $query->whereDoesntHave('kelulusan')
                    ->orWhereKey($kelulusanSik->anggota_periode_sik_id);
            })
            ->orderBy('id')
            ->get()
            ->map(function (AnggotaPeriodeSik $member): AnggotaPeriodeSik {
                return $this->decorateAnggota($member);
            });

        return view('kelulusan-sik.edit', [
            'kelulusan' => $kelulusanSik,
            'anggota' => $anggota,
        ]);
    }

    public function update(Request $request, KelulusanSik $kelulusanSik): RedirectResponse
    {
        $validated = $this->validatedData($request, $kelulusanSik);

        if (
            $kelulusanSik->sertifikat()->exists()
            && (
                (int) $validated['anggota_periode_sik_id'] !== (int) $kelulusanSik->anggota_periode_sik_id
                || $validated['status'] !== 'Lulus'
            )
        ) {
            return back()->withInput()->withErrors([
                'anggota_periode_sik_id' => 'Peserta dan status kelulusan tidak dapat diubah setelah sertifikat diterbitkan.',
            ]);
        }

        $kelulusanSik->update($validated);

        return redirect()->route('kelulusan-sik.index')
            ->with('success', 'Data kelulusan berhasil diperbarui.');
    }

    public function destroy(KelulusanSik $kelulusanSik): RedirectResponse
    {
        try {
            $kelulusanSik->delete();
        } catch (QueryException) {
            return back()->with(
                'error',
                'Data kelulusan tidak dapat dihapus karena sudah memiliki sertifikat.'
            );
        }

        return redirect()->route('kelulusan-sik.index')
            ->with('success', 'Data kelulusan berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?KelulusanSik $kelulusan = null): array
    {
        $validated = $request->validate([
            'anggota_periode_sik_id' => [
                'required',
                'exists:anggota_periode_sik,id',
                Rule::unique('kelulusan_sik', 'anggota_periode_sik_id')->ignore($kelulusan?->id),
            ],
            'status' => ['required', Rule::in(['Lulus', 'Tidak Lulus'])],
            'tanggal_kelulusan' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['status'] === 'Lulus') {
            $anggota = AnggotaPeriodeSik::query()->findOrFail($validated['anggota_periode_sik_id']);
            $metric = $this->attendanceRecapService->calculateForAnggota((int) $anggota->id);

            if (! $metric['memenuhi']) {
                throw ValidationException::withMessages([
                    'status' => 'Santri tidak dapat dinyatakan lulus. '.$this->attendanceRecapService->thresholdRequirementMessage($metric),
                ]);
            }
        }

        return $validated;
    }

    private function decorateGraduation(KelulusanSik $kelulusan): KelulusanSik
    {
        $evaluasi = $this->attendanceRecapService->graduationEvaluation(
            $kelulusan,
            $kelulusan->anggotaPeriodeSik
        );

        $kelulusan->setAttribute('evaluasi', $evaluasi);
        $kelulusan->setAttribute('evaluasi_label', $evaluasi['label']);
        $kelulusan->setAttribute('evaluasi_badge_class', match ($evaluasi['code']) {
            AttendanceRecapService::GRADUATION_PASSED => 'bg-success',
            AttendanceRecapService::GRADUATION_FAILED => 'bg-danger',
            default => 'bg-secondary',
        });
        $kelulusan->setAttribute('attendance', $evaluasi['metric']);
        $kelulusan->setAttribute('attendance_display', $this->formatMetricPercentage($evaluasi['metric']));
        $kelulusan->setAttribute('alasan_kehadiran', $evaluasi['alasan']);

        return $kelulusan;
    }

    private function decorateAnggota(AnggotaPeriodeSik $member): AnggotaPeriodeSik
    {
        $metric = $this->attendanceRecapService->calculateForAnggota((int) $member->id);

        $member->setAttribute('attendance_metric', $metric);
        $member->setAttribute('attendance_status_display', $metric['status']);
        $member->setAttribute('attendance_display', $this->formatMetricPercentage($metric));

        return $member;
    }

    /** @param array<string, mixed> $metric */
    private function formatMetricPercentage(array $metric): string
    {
        if ($metric['persentase'] === null) {
            return (string) $metric['status'];
        }

        return $this->attendanceRecapService->formatPercentage((float) $metric['persentase']).'%';
    }
}
