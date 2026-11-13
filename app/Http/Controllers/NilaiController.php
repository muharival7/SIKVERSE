<?php

namespace App\Http\Controllers;

use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\Santri;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NilaiController extends Controller
{
    public function index(Request $request): View
    {
        $instruktur = $this->currentInstructor($request);

        $santris = Santri::query()
            ->with([
                'nilai' => fn ($query) => $query
                    ->where('infrastruktur_sik_id', $instruktur->id)
                    ->with('mataPelajaran'),
            ])
            ->whereHas('anggotaPeriode', function ($query) use ($instruktur): void {
                $query->where('status', 'Aktif')
                    ->whereHas('kelompok', function ($query) use ($instruktur): void {
                        $query->where('instruktur_id', $instruktur->id)
                            ->where('status', 'Aktif');
                    });
            })
            ->orderBy('nama')
            ->get();

        $mataPelajaran = MataPelajaran::query()
            ->where('status', true)
            ->orderBy('semester')
            ->orderBy('nama_mapel')
            ->get();

        return view('nilai.index', compact('santris', 'mataPelajaran'));
    }

    public function store(Request $request): RedirectResponse
    {
        $instruktur = $this->currentInstructor($request);
        $validated = $request->validate($this->validationRules($request));

        $this->ensureInstructorCanGrade($instruktur, (int) $validated['santri_id']);

        $validated['infrastruktur_sik_id'] = $instruktur->id;
        Nilai::create($validated);

        return redirect()->route('nilai.index')
            ->with('success', 'Nilai santri berhasil disimpan.');
    }

    public function update(Request $request, Nilai $nilai): RedirectResponse
    {
        $instruktur = $this->currentInstructor($request);
        abort_unless((int) $nilai->infrastruktur_sik_id === (int) $instruktur->id, 403);

        $validated = $request->validate($this->validationRules($request, $nilai));
        $this->ensureInstructorCanGrade($instruktur, (int) $validated['santri_id']);

        $nilai->update($validated);

        return redirect()->route('nilai.index')
            ->with('success', 'Nilai santri berhasil diperbarui.');
    }

    public function transcript(Request $request): View
    {
        $santri = Santri::query()
            ->with(['anggotaPeriode.periode', 'anggotaPeriode.kelulusan'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $nilai = $santri->nilai()
            ->with(['mataPelajaran', 'infrastrukturSik'])
            ->orderBy('tahun_ajaran')
            ->orderBy('semester')
            ->get();

        return view('nilai.transkrip', compact('santri', 'nilai'));
    }

    private function currentInstructor(Request $request): InfrastrukturSik
    {
        $instruktur = $request->user()?->infrastrukturSik;

        abort_unless($instruktur instanceof InfrastrukturSik, 403, 'Data instruktur tidak ditemukan.');

        return $instruktur;
    }

    private function ensureInstructorCanGrade(InfrastrukturSik $instruktur, int $santriId): void
    {
        $isAssignedToSantri = AnggotaPeriodeSik::query()
            ->where('santri_id', $santriId)
            ->where('status', 'Aktif')
            ->whereHas('kelompok', function ($query) use ($instruktur): void {
                $query->where('instruktur_id', $instruktur->id)
                    ->where('status', 'Aktif');
            })
            ->exists();

        abort_unless($isAssignedToSantri, 403, 'Santri bukan anggota kelompok instruktur ini.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRules(Request $request, ?Nilai $nilai = null): array
    {
        $uniqueSantri = Rule::unique('nilai', 'santri_id')
            ->where('mata_pelajaran_id', $request->input('mata_pelajaran_id'))
            ->where('semester', $request->input('semester'))
            ->where('tahun_ajaran', $request->input('tahun_ajaran'))
            ->ignore($nilai?->id);

        return [
            'santri_id' => ['required', 'integer', 'exists:santris,id', $uniqueSantri],
            'mata_pelajaran_id' => [
                'required',
                'integer',
                Rule::exists('mata_pelajaran', 'id')->where('status', true),
            ],
            'semester' => ['required', 'string', 'max:50'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'nilai' => ['required', 'numeric', 'min:0', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
