<?php

namespace App\Http\Controllers;

use App\Models\InfrastrukturSik;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KelompokController extends Controller
{
    public function index()
    {
        $kelompoks = Kelompok::with(['periode', 'instruktur'])
            ->latest()
            ->paginate(10);

        return view('kelompok.index', compact('kelompoks'));
    }

    public function indexInstruktur(Request $request): View
    {
        $instruktur = $request->user()->infrastrukturSik;
        abort_unless($instruktur instanceof InfrastrukturSik, 403);

        $kelompoks = Kelompok::with(['periode', 'instruktur'])
            ->where('instruktur_id', $instruktur->id)
            ->latest()
            ->paginate(10);

        return view('kelompok.instruktur-index', compact('kelompoks'));
    }

    public function create()
    {
        $periodes = PeriodeSik::orderBy('tahun', 'desc')->get();

        $instrukturs = InfrastrukturSik::where('status', 'Aktif')
            ->orderBy('nama')
            ->get();

        return view('kelompok.create', compact('periodes', 'instrukturs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'periode_sik_id' => [
                'required',
                'exists:periode_sik,id',
            ],
            'instruktur_id' => [
                'required',
                Rule::exists('infrastruktur_siks', 'id')
                    ->where('status', 'Aktif'),
            ],
            'nama_kelompok' => [
                'required',
                'string',
                'max:100',
                Rule::unique('kelompok', 'nama_kelompok')
                    ->where('periode_sik_id', $request->periode_sik_id),
            ],
            'keterangan' => ['nullable', 'string'],
            'status' => [
                'required',
                Rule::in(['Aktif', 'Tidak Aktif']),
            ],
        ]);

        Kelompok::create($validated);

        return redirect()
            ->route('kelompok.index')
            ->with('success', 'Kelompok berhasil ditambahkan.');
    }

    public function edit(Kelompok $kelompok)
    {
        $periodes = PeriodeSik::orderBy('tahun', 'desc')->get();

        $instrukturs = InfrastrukturSik::where(function ($query) use ($kelompok) {
            $query->where('status', 'Aktif')
                ->orWhere('id', $kelompok->instruktur_id);
        })->orderBy('nama')->get();

        return view('kelompok.edit', compact(
            'kelompok',
            'periodes',
            'instrukturs'
        ));
    }

    public function update(Request $request, Kelompok $kelompok)
    {
        $validated = $request->validate([
            'periode_sik_id' => [
                'required',
                'exists:periode_sik,id',
            ],
            'instruktur_id' => [
                'required',
                Rule::exists('infrastruktur_siks', 'id')
                    ->where(function ($query) use ($kelompok) {
                        $query->where('status', 'Aktif')
                            ->orWhere('id', $kelompok->instruktur_id);
                    }),
            ],
            'nama_kelompok' => [
                'required',
                'string',
                'max:100',
                Rule::unique('kelompok', 'nama_kelompok')
                    ->where('periode_sik_id', $request->periode_sik_id)
                    ->ignore($kelompok->id),
            ],
            'keterangan' => ['nullable', 'string'],
            'status' => [
                'required',
                Rule::in(['Aktif', 'Tidak Aktif']),
            ],
        ]);

        if (
            (int) $validated['periode_sik_id'] !== (int) $kelompok->periode_sik_id
            && (
                $kelompok->anggota()->exists()
                || $kelompok->kegiatan()->exists()
                || Pertemuan::where('kelompok_host_id', $kelompok->id)->exists()
            )
        ) {
            return back()->withInput()->withErrors([
                'periode_sik_id' => 'Periode kelompok tidak dapat diubah setelah kelompok dipakai oleh anggota, kegiatan, atau pertemuan.',
            ]);
        }

        $kelompok->update($validated);

        return redirect()
            ->route('kelompok.index')
            ->with('success', 'Kelompok berhasil diperbarui.');
    }

    public function destroy(Kelompok $kelompok)
    {
        try {
            $kelompok->delete();

            return redirect()
                ->route('kelompok.index')
                ->with('success', 'Kelompok berhasil dihapus.');
        } catch (QueryException $e) {
            return back()->with(
                'error',
                'Kelompok tidak dapat dihapus karena masih memiliki anggota atau kegiatan terkait.'
            );
        }
    }
}
