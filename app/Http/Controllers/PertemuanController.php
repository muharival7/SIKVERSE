<?php

namespace App\Http\Controllers;

use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Services\AttendanceAuthorizationService;
use App\Services\AttendanceRecapService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PertemuanController extends Controller
{
    public function __construct(
        private readonly AttendanceRecapService $attendanceRecapService,
        private readonly AttendanceAuthorizationService $attendanceAuthorizationService
    ) {}

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
        DB::transaction(function () use ($request): void {
            $pertemuan = Pertemuan::create($this->validatedData($request));
            $this->attendanceRecapService->syncForPertemuan($pertemuan);
        });

        return redirect()->route('pertemuan.index')
            ->with('success', 'Pertemuan berhasil ditambahkan.');
    }

    public function edit(Pertemuan $pertemuan): View
    {
        return view('pertemuan.edit', [
            'pertemuan' => $pertemuan,
            ...$this->formOptions($pertemuan),
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

        $kegiatanSebelumnya = (int) $pertemuan->kegiatan_id;

        DB::transaction(function () use ($pertemuan, $validated, $kegiatanSebelumnya): void {
            $pertemuan->update($validated);
            $this->attendanceRecapService->syncForPertemuan($pertemuan, $kegiatanSebelumnya);
        });

        return redirect()->route('pertemuan.index')
            ->with('success', 'Pertemuan berhasil diperbarui.');
    }

    public function destroy(Pertemuan $pertemuan): RedirectResponse
    {
        try {
            DB::transaction(function () use ($pertemuan): void {
                $kegiatanId = (int) $pertemuan->kegiatan_id;
                $pertemuan->delete();
                $this->attendanceRecapService->syncForKegiatan($kegiatanId);
            });
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
    private function formOptions(?Pertemuan $pertemuan = null): array
    {
        $selectedKelompokId = (int) old('kelompok_host_id', $pertemuan?->kelompok_host_id ?? 0);

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

        $daftarKegiatan = Kegiatan::with('periode')->orderBy('nama_kegiatan')->get();
        $sharedActivityIds = $daftarKegiatan
            ->filter(fn (Kegiatan $activity): bool => $this->attendanceAuthorizationService->isSharedActivity($activity))
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();

        $instruktur = InfrastrukturSik::query()
            ->where('status', 'Aktif')
            ->orderBy('nama')
            ->get();

        $selectedInstrukturId = (int) old('instruktur_pengganti_id', $pertemuan?->instruktur_pengganti_id ?? 0);

        if ($selectedInstrukturId > 0 && ! $instruktur->contains('id', $selectedInstrukturId)) {
            $selected = InfrastrukturSik::find($selectedInstrukturId);

            if ($selected instanceof InfrastrukturSik) {
                $instruktur->push($selected);
                $instruktur = $instruktur->sortBy('nama')->values();
            }
        }

        return [
            'kegiatan' => $daftarKegiatan,
            'instruktur' => $instruktur,
            'kelompok' => $kelompok,
            'sharedActivityIds' => $sharedActivityIds,
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
            'instruktur_pengganti_id' => [
                'nullable',
                'exists:infrastruktur_siks,id',
                function (string $attribute, mixed $value, $fail) use ($pertemuan): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if ((string) $value === (string) $pertemuan?->instruktur_pengganti_id) {
                        return;
                    }

                    if (! InfrastrukturSik::query()->whereKey($value)->where('status', 'Aktif')->exists()) {
                        $fail('Instruktur pengganti harus berstatus Aktif.');
                    }
                },
            ],
            'kelompok_host_id' => ['nullable', 'exists:kelompok,id'],
            'waktu_mulai' => ['nullable', 'date_format:H:i'],
            'waktu_selesai' => $request->filled('waktu_mulai')
                ? ['nullable', 'date_format:H:i', 'after:waktu_mulai']
                : ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::in(['Terjadwal', 'Terlaksana', 'Dibatalkan'])],
            'keterangan' => ['nullable', 'string'],
        ]);

        $kegiatan = Kegiatan::with('periode')->findOrFail($validated['kegiatan_id']);
        $kelompokHostId = $validated['kelompok_host_id'] ?? null;

        if ($this->attendanceAuthorizationService->isSharedActivity($kegiatan)) {
            if ($kegiatan->kelompok_id !== null || $kelompokHostId !== null) {
                throw ValidationException::withMessages([
                    'kelompok_host_id' => 'Pertemuan Rabu Mengaji berlaku untuk semua kelompok dan tidak boleh memiliki kelompok host.',
                ]);
            }

            $validated['kelompok_host_id'] = null;
        } elseif ($kelompokHostId !== null) {
            $kelompok = Kelompok::findOrFail($kelompokHostId);

            if ((int) $kelompok->periode_sik_id !== (int) $kegiatan->periode_sik_id) {
                throw ValidationException::withMessages([
                    'kelompok_host_id' => 'Kelompok host harus berasal dari periode kegiatan.',
                ]);
            }

            if ($kegiatan->kelompok_id !== null && (int) $kelompokHostId !== (int) $kegiatan->kelompok_id) {
                throw ValidationException::withMessages([
                    'kelompok_host_id' => 'Kelompok host harus sama dengan kelompok kegiatan.',
                ]);
            }
        } elseif ($kegiatan->kelompok_id !== null) {
            $validated['kelompok_host_id'] = $kegiatan->kelompok_id;
        }

        $validated['kelompok_host_id'] = isset($validated['kelompok_host_id'])
            ? (int) $validated['kelompok_host_id']
            : null;

        $periode = $kegiatan->periode;

        if (! $periode instanceof PeriodeSik) {
            throw ValidationException::withMessages([
                'kegiatan_id' => 'Kegiatan harus memiliki periode SIK yang terdefinisi.',
            ]);
        }

        $tanggalMulai = $periode->tanggal_mulai->toDateString();
        $tanggalSelesai = $periode->tanggal_selesai->toDateString();

        if ($validated['tanggal'] < $tanggalMulai || $validated['tanggal'] > $tanggalSelesai) {
            throw ValidationException::withMessages([
                'tanggal' => 'Tanggal pertemuan harus berada dalam rentang periode "'.$periode->nama_periode.'" ('.$tanggalMulai.' hingga '.$tanggalSelesai.').',
            ]);
        }

        return $validated;
    }
}
