<?php

namespace App\Http\Controllers;

use App\Models\AnggotaPeriodeSik;
use App\Models\KelulusanSik;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KelulusanSikController extends Controller
{
    public function index(): View
    {
        $kelulusan = KelulusanSik::with([
            'anggotaPeriodeSik.santri',
            'anggotaPeriodeSik.periode',
            'anggotaPeriodeSik.kelompok',
        ])->latest()->paginate(10);

        return view('kelulusan-sik.index', compact('kelulusan'));
    }

    public function create(): View
    {
        $anggota = AnggotaPeriodeSik::with(['santri', 'periode', 'kelompok'])
            ->whereDoesntHave('kelulusan')
            ->where('status', 'Aktif')
            ->get();

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
            ->get();

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
            $jumlahHadir = $anggota->absensi()->where('status', 'Hadir')->count();
            $minimumHadir = (int) config('sik.attendance.minimum_present');
            $totalPertemuan = (int) config('sik.attendance.meeting_count');

            if ($jumlahHadir < $minimumHadir) {
                throw ValidationException::withMessages([
                    'status' => "Santri harus hadir minimal {$minimumHadir} dari {$totalPertemuan} pertemuan. Kehadiran saat ini {$jumlahHadir}.",
                ]);
            }
        }

        return $validated;
    }
}
