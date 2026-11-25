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
use Illuminate\Validation\ValidationException;
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
        $validated = $this->validatedData($request);
        $this->ensureNoDuplicateKegiatan($validated);

        Kegiatan::create($validated);

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
        $this->ensureNoDuplicateKegiatan($validated, $kegiatan);

        $kelompokSebelumnya = $kegiatan->kelompok_id;
        $kelompokBaru = $validated['kelompok_id'] ?? null;

        $sharedSebelumnya = $this->attendanceAuthorizationService->isSharedActivity($kegiatan);
        $sharedSesudah = $this->attendanceAuthorizationService->isSharedActivityName(
            (string) ($validated['nama_kegiatan'] ?? '')
        );
        $adaAbsensi = null;

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

            if ($sharedSebelumnya !== $sharedSesudah) {
                return back()->withInput()->withErrors([
                    'nama_kegiatan' => 'Nama kegiatan tidak dapat diubah karena akan mengubah klasifikasi kegiatan bersama setelah kegiatan memiliki pertemuan atau rekap terkait.',
                ]);
            }
        }

        if ($sharedSebelumnya !== $sharedSesudah) {
            $adaAbsensi ??= AbsensiSik::query()
                ->join('pertemuan', 'pertemuan.id', '=', 'absensi_sik.pertemuan_id')
                ->where('pertemuan.kegiatan_id', $kegiatan->id)
                ->exists();

            if ($adaAbsensi) {
                return back()->withInput()->withErrors([
                    'nama_kegiatan' => 'Nama kegiatan tidak dapat diubah karena akan mengubah klasifikasi kegiatan bersama setelah kegiatan memiliki data absensi terkait.',
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
     * @param  array<string, mixed>  $validated
     */
    private function ensureNoDuplicateKegiatan(array $validated, ?Kegiatan $kegiatan = null): void
    {
        $namaNormal = $this->normalizeKegiatanName((string) ($validated['nama_kegiatan'] ?? ''));
        $jenisKegiatan = (string) ($validated['jenis_kegiatan'] ?? '');
        $periodeSikId = (int) ($validated['periode_sik_id'] ?? 0);
        $kelompokId = $validated['kelompok_id'] ?? null;

        $duplicate = Kegiatan::query()
            ->when($kegiatan !== null, fn ($query) => $query->whereKeyNot($kegiatan->getKey()))
            ->where('periode_sik_id', $periodeSikId)
            ->where('jenis_kegiatan', $jenisKegiatan)
            ->where(function ($query) use ($kelompokId): void {
                if ($kelompokId === null) {
                    $query->whereNull('kelompok_id');

                    return;
                }

                $query->where('kelompok_id', $kelompokId);
            })
            ->get(['id', 'nama_kegiatan'])
            ->first(fn (Kegiatan $candidate): bool => $this->normalizeKegiatanName((string) $candidate->nama_kegiatan) === $namaNormal);

        if ($duplicate === null) {
            return;
        }

        throw ValidationException::withMessages([
            'nama_kegiatan' => 'Kegiatan dengan periode, nama, jenis, dan kelompok yang sama sudah ada. Gunakan nama atau kombinasi yang berbeda.',
        ]);
    }

    private function normalizeKegiatanName(string $name): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($name));

        return mb_strtolower($normalized ?? trim($name));
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
