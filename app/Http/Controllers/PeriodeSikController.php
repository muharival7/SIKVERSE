<?php

namespace App\Http\Controllers;

use App\Models\PeriodeSik;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeriodeSikController extends Controller
{
    public function index()
    {
        $periodes = PeriodeSik::latest()->paginate(10);

        return view('periode_sik.index', compact('periodes'));
    }

    public function create()
    {
        return view('periode_sik.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_periode' => [
                'required',
                'string',
                'max:100',
                'unique:periode_sik,nama_periode',
            ],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => [
                'required',
                'date',
                'after_or_equal:tanggal_mulai',
            ],
            'status' => [
                'required',
                Rule::in(['Persiapan', 'Aktif', 'Selesai']),
            ],
        ]);

        PeriodeSik::create($validated);

        return redirect()
            ->route('periode-sik.index')
            ->with('success', 'Periode SIK berhasil ditambahkan.');
    }

    public function edit(PeriodeSik $periodeSik)
    {
        return view('periode_sik.edit', compact('periodeSik'));
    }

    public function update(Request $request, PeriodeSik $periodeSik)
    {
        $validated = $request->validate([
            'nama_periode' => [
                'required',
                'string',
                'max:100',
                Rule::unique('periode_sik', 'nama_periode')
                    ->ignore($periodeSik->id),
            ],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => [
                'required',
                'date',
                'after_or_equal:tanggal_mulai',
            ],
            'status' => [
                'required',
                Rule::in(['Persiapan', 'Aktif', 'Selesai']),
            ],
        ]);

        $periodeSik->update($validated);

        return redirect()
            ->route('periode-sik.index')
            ->with('success', 'Periode SIK berhasil diperbarui.');
    }

    public function destroy(PeriodeSik $periodeSik)
    {
        try {
            $periodeSik->delete();

            return redirect()
                ->route('periode-sik.index')
                ->with('success', 'Periode SIK berhasil dihapus.');
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->with(
                'error',
                'Periode tidak dapat dihapus karena masih memiliki data terkait.'
            );
        }
    }
}
