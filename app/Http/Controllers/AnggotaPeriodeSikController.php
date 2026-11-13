<?php

namespace App\Http\Controllers;

use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Santri;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnggotaPeriodeSikController extends Controller
{
    public function index(): View
    {
        $anggota = AnggotaPeriodeSik::with(['santri', 'periode', 'kelompok'])
            ->latest()
            ->paginate(10);

        return view('anggota-periode-sik.index', [
            'anggota' => $anggota,
            'readOnly' => false,
        ]);
    }

    public function indexInstruktur(Request $request): View
    {
        $instruktur = $request->user()->infrastrukturSik;
        abort_unless($instruktur instanceof InfrastrukturSik, 403);

        $anggota = AnggotaPeriodeSik::with(['santri', 'periode', 'kelompok'])
            ->whereHas('kelompok', fn ($query) => $query->where('instruktur_id', $instruktur->id))
            ->latest()
            ->paginate(10);

        return view('anggota-periode-sik.index', [
            'anggota' => $anggota,
            'readOnly' => true,
        ]);
    }

    public function create(): View
    {
        return view('anggota-periode-sik.create', [
            'santri' => Santri::orderBy('nama')->get(),
            'periode' => PeriodeSik::orderByDesc('tahun')->get(),
            'kelompok' => Kelompok::with('periode')->orderBy('nama_kelompok')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AnggotaPeriodeSik::create($this->validatedData($request));

        return redirect()->route('anggota-periode-sik.index')
            ->with('success', 'Anggota periode SIK berhasil ditambahkan.');
    }

    public function edit(AnggotaPeriodeSik $anggotaPeriodeSik): View
    {
        return view('anggota-periode-sik.edit', [
            'anggota' => $anggotaPeriodeSik,
            'santri' => Santri::orderBy('nama')->get(),
            'periode' => PeriodeSik::orderByDesc('tahun')->get(),
            'kelompok' => Kelompok::with('periode')->orderBy('nama_kelompok')->get(),
        ]);
    }

    public function update(Request $request, AnggotaPeriodeSik $anggotaPeriodeSik): RedirectResponse
    {
        $validated = $this->validatedData($request, $anggotaPeriodeSik);
        $hasHistory = $anggotaPeriodeSik->absensi()->exists()
            || $anggotaPeriodeSik->rekapKehadiran()->exists()
            || $anggotaPeriodeSik->kelulusan()->exists();

        if (
            $hasHistory
            && (
                (int) $validated['santri_id'] !== (int) $anggotaPeriodeSik->santri_id
                || (int) $validated['periode_sik_id'] !== (int) $anggotaPeriodeSik->periode_sik_id
                || (int) $validated['kelompok_id'] !== (int) $anggotaPeriodeSik->kelompok_id
            )
        ) {
            return back()->withInput()->withErrors([
                'santri_id' => 'Santri, periode, dan kelompok tidak dapat diubah setelah ada riwayat kehadiran atau kelulusan.',
            ]);
        }

        $anggotaPeriodeSik->update($validated);

        return redirect()->route('anggota-periode-sik.index')
            ->with('success', 'Anggota periode SIK berhasil diperbarui.');
    }

    public function destroy(AnggotaPeriodeSik $anggotaPeriodeSik): RedirectResponse
    {
        try {
            $anggotaPeriodeSik->delete();
        } catch (QueryException) {
            return back()->with(
                'error',
                'Anggota tidak dapat dihapus karena sudah memiliki absensi, rekap, atau kelulusan.'
            );
        }

        return redirect()->route('anggota-periode-sik.index')
            ->with('success', 'Anggota periode SIK berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?AnggotaPeriodeSik $anggota = null): array
    {
        return $request->validate([
            'santri_id' => [
                'required',
                'exists:santris,id',
                Rule::unique('anggota_periode_sik', 'santri_id')
                    ->where('periode_sik_id', $request->input('periode_sik_id'))
                    ->ignore($anggota?->id),
            ],
            'periode_sik_id' => ['required', 'exists:periode_sik,id'],
            'kelompok_id' => [
                'required',
                Rule::exists('kelompok', 'id')
                    ->where('periode_sik_id', $request->input('periode_sik_id')),
            ],
            'tanggal_masuk' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['Aktif', 'Tidak Aktif'])],
        ]);
    }
}
