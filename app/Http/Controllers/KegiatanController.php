<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KegiatanController extends Controller
{
    public function index(): View
    {
        $kegiatans = Kegiatan::with(['periode', 'kelompok'])
            ->latest()
            ->paginate(10);

        return view('kegiatan.index', compact('kegiatans'));
    }

    public function create(): View
    {
        return view('kegiatan.create', [
            'periodeSik' => PeriodeSik::orderByDesc('tahun')->get(),
            'kelompok' => Kelompok::with('periode')->orderBy('nama_kelompok')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);

        if ($validated['jenis_kegiatan'] === 'Aula') {
            $validated['kelompok_id'] = null;
        }

        Kegiatan::create($validated);

        return redirect()->route('kegiatan.index')
            ->with('success', 'Kegiatan berhasil ditambahkan.');
    }

    public function edit(Kegiatan $kegiatan): View
    {
        return view('kegiatan.edit', [
            'kegiatan' => $kegiatan,
            'periodeSik' => PeriodeSik::orderByDesc('tahun')->get(),
            'kelompok' => Kelompok::with('periode')->orderBy('nama_kelompok')->get(),
        ]);
    }

    public function update(Request $request, Kegiatan $kegiatan): RedirectResponse
    {
        $validated = $this->validatedData($request);

        if ($validated['jenis_kegiatan'] === 'Aula') {
            $validated['kelompok_id'] = null;
        }

        if ($kegiatan->pertemuan()->exists() || $kegiatan->rekapKehadiran()->exists()) {
            $identityChanged = (int) $validated['periode_sik_id'] !== (int) $kegiatan->periode_sik_id
                || (int) ($validated['kelompok_id'] ?? 0) !== (int) ($kegiatan->kelompok_id ?? 0)
                || $validated['jenis_kegiatan'] !== $kegiatan->jenis_kegiatan;

            if ($identityChanged) {
                return back()->withInput()->withErrors([
                    'periode_sik_id' => 'Periode, kelompok, dan jenis kegiatan tidak dapat diubah setelah ada pertemuan atau rekap.',
                ]);
            }
        }

        $kegiatan->update($validated);

        return redirect()->route('kegiatan.index')
            ->with('success', 'Kegiatan berhasil diperbarui.');
    }

    public function destroy(Kegiatan $kegiatan): RedirectResponse
    {
        try {
            $kegiatan->delete();
        } catch (QueryException) {
            return back()->with(
                'error',
                'Kegiatan tidak dapat dihapus karena masih memiliki data pertemuan atau rekap.'
            );
        }

        return redirect()->route('kegiatan.index')
            ->with('success', 'Kegiatan berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'nama_kegiatan' => ['required', 'string', 'max:150'],
            'jenis_kegiatan' => ['required', Rule::in(['Kelas', 'Aula'])],
            'periode_sik_id' => ['required', 'exists:periode_sik,id'],
            'kelompok_id' => [
                Rule::requiredIf($request->input('jenis_kegiatan') === 'Kelas'),
                'nullable',
                Rule::exists('kelompok', 'id')->where(
                    fn ($query) => $query->where('periode_sik_id', $request->input('periode_sik_id'))
                ),
            ],
            'keterangan' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['Aktif', 'Tidak Aktif'])],
        ]);
    }
}
