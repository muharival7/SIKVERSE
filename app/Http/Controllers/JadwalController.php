<?php

namespace App\Http\Controllers;

use App\Models\InfrastrukturSik;
use App\Models\Jadwal;
use App\Models\MataPelajaran;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JadwalController extends Controller
{
    public function index()
    {
        $jadwal = Jadwal::with(['mataPelajaran', 'infrastrukturSik'])
            ->latest()
            ->paginate(10);

        return view('jadwal.index', compact('jadwal'));
    }

    public function indexInstruktur(Request $request): View
    {
        $instruktur = $request->user()->infrastrukturSik;
        abort_unless($instruktur instanceof InfrastrukturSik, 403);

        $jadwal = Jadwal::with(['mataPelajaran', 'infrastrukturSik'])
            ->where('infrastruktur_sik_id', $instruktur->id)
            ->latest()
            ->paginate(10);

        return view('jadwal.instruktur-index', compact('jadwal'));
    }

    public function create()
    {
        $mataPelajaran = MataPelajaran::where('status', true)
            ->orderBy('nama_mapel')
            ->get();

        $infrastruktur = InfrastrukturSik::where('status', 'Aktif')
            ->orderBy('nama')
            ->get();

        return view('jadwal.create', compact('mataPelajaran', 'infrastruktur'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mata_pelajaran_id' => ['required', 'exists:mata_pelajaran,id'],
            'infrastruktur_sik_id' => ['required', 'exists:infrastruktur_siks,id'],
            'hari' => ['required', 'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i', 'after:waktu_mulai'],
            'ruang' => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
        ]);

        Jadwal::create($validated);

        return redirect()
            ->route('jadwal.index')
            ->with('success', 'Jadwal berhasil ditambahkan.');
    }

    public function show(Jadwal $jadwal)
    {
        $jadwal->load(['mataPelajaran', 'infrastrukturSik']);

        return view('jadwal.show', compact('jadwal'));
    }

    public function edit(Jadwal $jadwal)
    {
        $mataPelajaran = MataPelajaran::where('status', true)
            ->orderBy('nama_mapel')
            ->get();

        $infrastruktur = InfrastrukturSik::where('status', 'Aktif')
            ->orderBy('nama')
            ->get();

        return view('jadwal.edit', compact(
            'jadwal',
            'mataPelajaran',
            'infrastruktur'
        ));
    }

    public function update(Request $request, Jadwal $jadwal)
    {
        $validated = $request->validate([
            'mata_pelajaran_id' => ['required', 'exists:mata_pelajaran,id'],
            'infrastruktur_sik_id' => ['required', 'exists:infrastruktur_siks,id'],
            'hari' => ['required', 'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i', 'after:waktu_mulai'],
            'ruang' => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
        ]);

        if (
            $jadwal->absensi()->exists()
            && (
                (int) $validated['mata_pelajaran_id'] !== (int) $jadwal->mata_pelajaran_id
                || (int) $validated['infrastruktur_sik_id'] !== (int) $jadwal->infrastruktur_sik_id
            )
        ) {
            return back()->withInput()->withErrors([
                'mata_pelajaran_id' => 'Mata pelajaran dan pengajar tidak dapat diganti setelah ada riwayat kehadiran.',
            ]);
        }

        $jadwal->update($validated);

        return redirect()
            ->route('jadwal.index')
            ->with('success', 'Jadwal berhasil diperbarui.');
    }

    public function destroy(Jadwal $jadwal)
    {
        if ($jadwal->absensi()->exists()) {
            return back()->with(
                'error',
                'Jadwal tidak dapat dihapus karena masih memiliki riwayat kehadiran.'
            );
        }

        $jadwal->delete();

        return redirect()
            ->route('jadwal.index')
            ->with('success', 'Jadwal berhasil dihapus.');
    }
}
