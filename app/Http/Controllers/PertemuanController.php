<?php

namespace App\Http\Controllers;

use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\Pertemuan;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PertemuanController extends Controller
{
    public function index(): View
    {
        $pertemuan = Pertemuan::with(['kegiatan.periode', 'instrukturPengganti', 'kelompokHost'])
            ->latest('tanggal')
            ->paginate(10);

        return view('pertemuan.index', compact('pertemuan'));
    }

    public function create(): View
    {
        return view('pertemuan.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        Pertemuan::create($this->validatedData($request));

        return redirect()->route('pertemuan.index')
            ->with('success', 'Pertemuan berhasil ditambahkan.');
    }

    public function edit(Pertemuan $pertemuan): View
    {
        return view('pertemuan.edit', [
            'pertemuan' => $pertemuan,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Pertemuan $pertemuan): RedirectResponse
    {
        $validated = $this->validatedData($request, $pertemuan);

        if ($pertemuan->absensi()->exists()) {
            $identityChanged = (int) $validated['kegiatan_id'] !== (int) $pertemuan->kegiatan_id
                || (int) $validated['pertemuan_ke'] !== (int) $pertemuan->pertemuan_ke
                || $validated['tanggal'] !== $pertemuan->tanggal->toDateString()
                || (int) ($validated['kelompok_host_id'] ?? 0) !== (int) ($pertemuan->kelompok_host_id ?? 0);

            if ($identityChanged) {
                return back()->withInput()->withErrors([
                    'kegiatan_id' => 'Kegiatan, nomor, tanggal, dan kelompok host tidak dapat diubah setelah absensi dicatat.',
                ]);
            }

            if ($validated['status'] === 'Dibatalkan') {
                return back()->withInput()->withErrors([
                    'status' => 'Pertemuan yang sudah memiliki absensi tidak dapat dibatalkan.',
                ]);
            }
        }

        $pertemuan->update($validated);

        return redirect()->route('pertemuan.index')
            ->with('success', 'Pertemuan berhasil diperbarui.');
    }

    public function destroy(Pertemuan $pertemuan): RedirectResponse
    {
        try {
            $pertemuan->delete();
        } catch (QueryException) {
            return back()->with(
                'error',
                'Pertemuan tidak dapat dihapus karena sudah memiliki data absensi.'
            );
        }

        return redirect()->route('pertemuan.index')
            ->with('success', 'Pertemuan berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'kegiatan' => Kegiatan::with('periode')->orderBy('nama_kegiatan')->get(),
            'instruktur' => InfrastrukturSik::orderBy('nama')->get(),
            'kelompok' => Kelompok::with('periode')->orderBy('nama_kelompok')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validatedData(Request $request, ?Pertemuan $pertemuan = null): array
    {
        $validated = $request->validate([
            'kegiatan_id' => ['required', 'exists:kegiatan,id'],
            'pertemuan_ke' => [
                'required',
                'integer',
                'min:1',
                'max:'.config('sik.attendance.meeting_count', 16),
                Rule::unique('pertemuan', 'pertemuan_ke')
                    ->where('kegiatan_id', $request->input('kegiatan_id'))
                    ->ignore($pertemuan?->id),
            ],
            'tanggal' => ['required', 'date'],
            'instruktur_pengganti_id' => ['nullable', 'exists:infrastruktur_siks,id'],
            'kelompok_host_id' => ['nullable', 'exists:kelompok,id'],
            'waktu_mulai' => ['nullable', 'date_format:H:i'],
            'waktu_selesai' => $request->filled('waktu_mulai')
                ? ['nullable', 'date_format:H:i', 'after:waktu_mulai']
                : ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::in(['Terjadwal', 'Terlaksana', 'Dibatalkan'])],
            'keterangan' => ['nullable', 'string'],
        ]);

        if (filled($validated['kelompok_host_id'] ?? null)) {
            $kegiatan = Kegiatan::findOrFail($validated['kegiatan_id']);
            $kelompok = Kelompok::findOrFail($validated['kelompok_host_id']);

            if ($kegiatan->periode_sik_id !== $kelompok->periode_sik_id) {
                throw ValidationException::withMessages([
                    'kelompok_host_id' => 'Kelompok host harus berasal dari periode kegiatan.',
                ]);
            }
        }

        return $validated;
    }
}
