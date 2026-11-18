<?php

namespace App\Http\Controllers;

use App\Models\AbsensiSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Services\AttendanceAuthorizationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KegiatanController extends Controller
{
    public function __construct(
        private readonly AttendanceAuthorizationService $attendanceAuthorizationService
    ) {}

    public function index(): View
    {
        $kegiatans = Kegiatan::with(['periode', 'kelompok'])
            ->latest()
            ->paginate(10);

        return view('kegiatan.index', compact('kegiatans'));
    }

    public function create(): View
    {
        return view('kegiatan.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        Kegiatan::create($this->validatedData($request));

        return redirect()->route('kegiatan.index')
            ->with('success', 'Kegiatan berhasil ditambahkan.');
    }

    public function edit(Kegiatan $kegiatan): View
    {
        return view('kegiatan.edit', [
            'kegiatan' => $kegiatan,
            ...$this->formOptions($kegiatan),
        ]);
    }

    public function update(Request $request, Kegiatan $kegiatan): RedirectResponse
    {
        $validated = $this->validatedData($request, $kegiatan);
        $kelompokSebelumnya = $kegiatan->kelompok_id;
        $kelompokBaru = $validated['kelompok_id'] ?? null;

        if ($kegiatan->pertemuan()->exists() || $kegiatan->rekapKehadiran()->exists()) {
            $identityChanged = (int) $validated['periode_sik_id'] !== (int) $kegiatan->periode_sik_id
                || $validated['jenis_kegiatan'] !== $kegiatan->jenis_kegiatan;

            $adaAbsensi = AbsensiSik::query()
                ->join('pertemuan', 'pertemuan.id', '=', 'absensi_sik.pertemuan_id')
                ->where('pertemuan.kegiatan_id', $kegiatan->id)
                ->exists();

            if ($identityChanged || ($adaAbsensi && (int) $kelompokSebelumnya !== (int) $kelompokBaru)) {
                return back()->withInput()->withErrors([
                    'periode_sik_id' => 'Periode dan jenis kegiatan tidak dapat diubah setelah ada pertemuan atau rekap. Kelompok tidak dapat diubah setelah absensi dicatat.',
                ]);
            }
        }

        DB::transaction(function () use ($kegiatan, $validated, $kelompokSebelumnya, $kelompokBaru): void {
            $kegiatan->update($validated);

            if ($kelompokBaru === null || (int) $kelompokSebelumnya === (int) $kelompokBaru) {
                return;
            }

            $kegiatan->pertemuan()->update(['kelompok_host_id' => $kelompokBaru]);
        });

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
    private function validatedData(Request $request, ?Kegiatan $kegiatan = null): array
    {
        $sharedActivity = $this->attendanceAuthorizationService
            ->isSharedActivityName((string) $request->input('nama_kegiatan'));
        $kelompokWajib = ! $sharedActivity
            && ($request->input('jenis_kegiatan') === 'Kelas'
                || ($kegiatan instanceof Kegiatan && $kegiatan->pertemuan()->exists()));

        $validated = $request->validate([
            'nama_kegiatan' => ['required', 'string', 'max:150'],
            'jenis_kegiatan' => ['required', Rule::in(['Kelas', 'Aula'])],
            'periode_sik_id' => ['required', 'exists:periode_sik,id'],
            'kelompok_id' => [
                Rule::prohibitedIf($sharedActivity),
                Rule::requiredIf($kelompokWajib),
                'nullable',
                Rule::exists('kelompok', 'id')->where(
                    fn ($query) => $query
                        ->where('periode_sik_id', $request->input('periode_sik_id'))
                        ->where('status', 'Aktif')
                ),
            ],
            'keterangan' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['Aktif', 'Tidak Aktif'])],
        ], [
            'kelompok_id.prohibited_if' => 'Kegiatan Rabu Mengaji berlaku untuk semua kelompok.',
        ]);

        if ($sharedActivity) {
            $validated['kelompok_id'] = null;
        }

        return $validated;
    }

    /**
     * Kelompok aktif menjadi pilihan default. Kelompok yang sedang tersimpan
     * tetap ditampilkan bila statusnya berubah agar form edit tidak kehilangan
     * nilai lama sebelum Admin menggantinya.
     *
     * @return array{periodeSik: Collection<int, PeriodeSik>, kelompok: Collection<int, Kelompok>}
     */
    private function formOptions(?Kegiatan $kegiatan = null): array
    {
        $selectedKelompokId = (int) old('kelompok_id', $kegiatan?->kelompok_id ?? 0);

        $kelompok = Kelompok::with('periode')
            ->where('status', 'Aktif')
            ->orderBy('nama_kelompok')
            ->get();

        if ($selectedKelompokId > 0 && ! $kelompok->contains('id', $selectedKelompokId)) {
            $selected = Kelompok::with('periode')->find($selectedKelompokId);

            if ($selected instanceof Kelompok) {
                $kelompok->push($selected);
                $kelompok = $kelompok->sortBy('nama_kelompok')->values();
            }
        }

        return [
            'periodeSik' => PeriodeSik::orderByDesc('tahun')->get(),
            'kelompok' => $kelompok,
        ];
    }
}
