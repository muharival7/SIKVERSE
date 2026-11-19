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
        return view('anggota-periode-sik.create', array_merge(
            ['santri' => Santri::orderBy('nama')->get()],
            $this->membershipFormOptions()
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        AnggotaPeriodeSik::create($this->validatedData($request));

        return redirect()->route('anggota-periode-sik.index')
            ->with('success', 'Anggota periode SIK berhasil ditambahkan.');
    }

    public function edit(AnggotaPeriodeSik $anggotaPeriodeSik): View
    {
        return view('anggota-periode-sik.edit', array_merge(
            [
                'anggota' => $anggotaPeriodeSik,
                'santri' => Santri::orderBy('nama')->get(),
            ],
            $this->membershipFormOptions($anggotaPeriodeSik)
        ));
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
     * @return array{periode: mixed, kelompok: mixed}
     */
    private function membershipFormOptions(?AnggotaPeriodeSik $anggota = null): array
    {
        $periodes = PeriodeSik::query()
            ->where('status', 'Aktif')
            ->orderByDesc('tahun')
            ->get();

        if ($anggota?->periode instanceof PeriodeSik && ! $periodes->contains('id', $anggota->periode_sik_id)) {
            $periodes->push($anggota->periode);
            $periodes = $periodes->sortByDesc('tahun')->values();
        }

        $kelompoks = Kelompok::query()
            ->with('periode')
            ->where('status', 'Aktif')
            ->whereHas('periode', fn ($query) => $query->where('status', 'Aktif'))
            ->orderBy('nama_kelompok')
            ->get();

        if ($anggota?->kelompok instanceof Kelompok && ! $kelompoks->contains('id', $anggota->kelompok_id)) {
            $kelompoks->push($anggota->kelompok);
            $kelompoks = $kelompoks->sortBy('nama_kelompok')->values();
        }

        return [
            'periode' => $periodes,
            'kelompok' => $kelompoks,
        ];
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
            'periode_sik_id' => [
                'required',
                'exists:periode_sik,id',
                function (string $attribute, mixed $value, $fail) use ($anggota): void {
                    if ((string) $value === (string) $anggota?->periode_sik_id) {
                        return;
                    }

                    if (! PeriodeSik::query()->whereKey($value)->where('status', 'Aktif')->exists()) {
                        $fail('Periode yang dipilih tidak aktif.');
                    }
                },
            ],
            'kelompok_id' => [
                'required',
                Rule::exists('kelompok', 'id')
                    ->where('periode_sik_id', $request->input('periode_sik_id')),
                function (string $attribute, mixed $value, $fail) use ($anggota): void {
                    if ((string) $value === (string) $anggota?->kelompok_id) {
                        return;
                    }

                    if (! Kelompok::query()->whereKey($value)->where('status', 'Aktif')->exists()) {
                        $fail('Kelompok yang dipilih tidak aktif.');
                    }
                },
            ],
            'tanggal_masuk' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['Aktif', 'Tidak Aktif'])],
        ], [
            'santri_id.unique' => 'Santri sudah terdaftar pada periode ini. Gunakan menu edit untuk memindahkan kelompok.',
            'periode_sik_id.exists' => 'Periode yang dipilih tidak ditemukan.',
            'kelompok_id.required' => 'Kelompok wajib dipilih.',
            'kelompok_id.exists' => 'Kelompok harus berada pada periode yang dipilih.',
        ]);
    }
}
