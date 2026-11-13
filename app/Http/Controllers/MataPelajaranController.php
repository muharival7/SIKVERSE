<?php

namespace App\Http\Controllers;

use App\Models\MataPelajaran;
use Illuminate\Http\Request;

class MataPelajaranController extends Controller
{
    public function index()
    {
        $mataPelajaran = MataPelajaran::latest()->paginate(10);

        return view('mata-pelajaran.index', compact('mataPelajaran'));
    }

    public function create()
    {
        return view('mata-pelajaran.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_mapel' => ['required', 'string', 'max:255', 'unique:mata_pelajaran,kode_mapel'],
            'nama_mapel' => ['required', 'string', 'max:255'],
            'sks' => ['required', 'integer', 'min:1'],
            'semester' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'boolean'],
        ]);

        MataPelajaran::create($validated);

        return redirect()
            ->route('mata-pelajaran.index')
            ->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function show(MataPelajaran $mataPelajaran)
    {
        return view('mata-pelajaran.show', compact('mataPelajaran'));
    }

    public function edit(MataPelajaran $mataPelajaran)
    {
        return view('mata-pelajaran.edit', compact('mataPelajaran'));
    }

    public function update(Request $request, MataPelajaran $mataPelajaran)
    {
        $validated = $request->validate([
            'kode_mapel' => [
                'required',
                'string',
                'max:255',
                'unique:mata_pelajaran,kode_mapel,'.$mataPelajaran->id,
            ],
            'nama_mapel' => ['required', 'string', 'max:255'],
            'sks' => ['required', 'integer', 'min:1'],
            'semester' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'boolean'],
        ]);

        $mataPelajaran->update($validated);

        return redirect()
            ->route('mata-pelajaran.index')
            ->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function destroy(MataPelajaran $mataPelajaran)
    {
        if ($mataPelajaran->jadwal()->exists() || $mataPelajaran->nilai()->exists()) {
            return back()->with(
                'error',
                'Mata pelajaran tidak dapat dihapus karena masih digunakan oleh jadwal atau nilai.'
            );
        }

        $mataPelajaran->delete();

        return redirect()
            ->route('mata-pelajaran.index')
            ->with('success', 'Mata pelajaran berhasil dihapus.');
    }
}
