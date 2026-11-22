<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\MataPelajaran;
use App\Models\Nilai;
use App\Models\PeriodeSik;
use App\Models\Santri;
use App\Services\ExamEligibilityService;
use App\Services\InstructorKelompokService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NilaiController extends Controller
{
    public function __construct(
        private readonly InstructorKelompokService $instructorKelompokService,
        private readonly ExamEligibilityService $examEligibilityService
    ) {}

    public function index(Request $request): View
    {
        if ($request->user()->role === UserRole::Admin) {
            return $this->adminIndex($request);
        }

        $instruktur = $this->currentInstructor($request);
        $ownedGroupIds = $this->instructorKelompokService->ownedGroupIds($instruktur);
        $gradeableGroupIds = $this->instructorKelompokService->activeOwnedGroupIds($instruktur, $ownedGroupIds);
        $selectedGroupIds = $this->instructorKelompokService->resolveSelectedGroupIds(
            $request->query('kelompok_id', []),
            $gradeableGroupIds
        );
        $search = trim((string) $request->query('q', ''));

        $santris = Santri::query()
            ->with([
                'nilai' => fn ($query) => $query
                    ->where('infrastruktur_sik_id', $instruktur->id)
                    ->with(['mataPelajaran', 'kegiatan']),
                'anggotaPeriode' => fn ($query) => $query
                    ->where('status', 'Aktif')
                    ->whereIn('kelompok_id', $gradeableGroupIds)
                    ->with(['kelompok', 'periode']),
            ])
            ->whereHas('anggotaPeriode', function ($query) use ($gradeableGroupIds, $selectedGroupIds): void {
                $query->where('status', 'Aktif')
                    ->whereIn('kelompok_id', $gradeableGroupIds)
                    ->whereHas('kelompok', fn ($groupQuery) => $groupQuery->where('status', 'Aktif'));

                if ($selectedGroupIds !== []) {
                    $query->whereIn('kelompok_id', $selectedGroupIds);
                }
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('nama', 'like', '%'.$search.'%')
                        ->orWhere('nomor_induk', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('nama')
            ->get();

        $mataPelajaranIds = MataPelajaran::query()
            ->where('status', true)
            ->pluck('id')
            ->merge(
                Nilai::query()
                    ->whereIn('santri_id', $santris->modelKeys())
                    ->pluck('mata_pelajaran_id')
                    ->filter()
            )
            ->unique()
            ->values();

        $mataPelajaran = MataPelajaran::query()
            ->whereIn('id', $mataPelajaranIds)
            ->orderByDesc('status')
            ->orderBy('semester')
            ->orderBy('nama_mapel')
            ->get();

        $kelompoks = Kelompok::query()
            ->whereIn('id', $gradeableGroupIds)
            ->where('status', 'Aktif')
            ->orderBy('nama_kelompok')
            ->orderBy('id')
            ->get();

        $kegiatans = Kegiatan::query()
            ->with(['kelompok', 'periode'])
            ->whereIn('kelompok_id', $gradeableGroupIds)
            ->where('status', 'Aktif')
            ->orderBy('nama_kegiatan')
            ->orderBy('id')
            ->get();

        foreach ($santris as $santri) {
            $santri->setAttribute(
                'exam_eligibility',
                $this->examEligibilityService->forSantri((int) $santri->id)
            );
        }

        return view('nilai.index', compact(
            'santris',
            'mataPelajaran',
            'kelompoks',
            'kegiatans',
            'selectedGroupIds',
            'search'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $instruktur = $this->currentInstructor($request);
        $validated = $request->validate($this->validationRules());

        $this->ensureInstructorCanGrade($instruktur, (int) $validated['santri_id']);
        $jenisNilai = $validated['jenis_nilai'] ?? 'Harian';
        $kegiatanId = $validated['kegiatan_id'] ?? null;

        $this->ensureKegiatanDipilih($jenisNilai, $kegiatanId);
        $this->ensureKegiatanCanGrade($instruktur, (int) $validated['santri_id'], $kegiatanId);
        $this->ensureExamEligible((int) $validated['santri_id'], $jenisNilai, $kegiatanId);

        DB::transaction(function () use ($validated, $instruktur, $jenisNilai, $kegiatanId): void {
            $mataPelajaran = $this->resolveMataPelajaran(
                $validated['nama_mapel'],
                $validated['semester']
            );

            if ($jenisNilai === 'Ujian') {
                $this->ensureExamGradeIsUnique(
                    (int) $validated['santri_id'],
                    (int) $kegiatanId,
                );
            } else {
                $this->ensureNilaiIsUnique(
                    (int) $validated['santri_id'],
                    (int) $mataPelajaran->id,
                    $validated['semester'],
                    $validated['tahun_ajaran'],
                );
            }

            try {
                Nilai::create([
                    'santri_id' => $validated['santri_id'],
                    'mata_pelajaran_id' => $mataPelajaran->id,
                    'kegiatan_id' => $kegiatanId,
                    'jenis_nilai' => $jenisNilai,
                    'infrastruktur_sik_id' => $instruktur->id,
                    'semester' => $validated['semester'],
                    'tahun_ajaran' => $validated['tahun_ajaran'],
                    'nilai' => $validated['nilai'],
                    'keterangan' => $validated['keterangan'] ?? null,
                ]);
            } catch (QueryException $exception) {
                $this->throwUniqueViolation($exception);
            }
        });

        return redirect()->route('nilai.index')
            ->with('success', 'Nilai santri berhasil disimpan.');
    }

    public function update(Request $request, Nilai $nilai): RedirectResponse
    {
        $instruktur = $this->currentInstructor($request);
        abort_unless((int) $nilai->infrastruktur_sik_id === (int) $instruktur->id, 403);

        $validated = $request->validate($this->validationRules());
        $this->ensureInstructorCanGrade($instruktur, (int) $validated['santri_id']);
        $jenisNilai = $validated['jenis_nilai'] ?? 'Harian';
        $kegiatanId = $validated['kegiatan_id'] ?? null;

        $this->ensureKegiatanDipilih($jenisNilai, $kegiatanId);
        $this->ensureKegiatanCanGrade($instruktur, (int) $validated['santri_id'], $kegiatanId);
        $this->ensureExamEligible((int) $validated['santri_id'], $jenisNilai, $kegiatanId);

        DB::transaction(function () use ($nilai, $validated, $jenisNilai, $kegiatanId): void {
            $mataPelajaran = $this->resolveMataPelajaran(
                $validated['nama_mapel'],
                $validated['semester']
            );

            if ($jenisNilai === 'Ujian') {
                $this->ensureExamGradeIsUnique(
                    (int) $validated['santri_id'],
                    (int) $kegiatanId,
                    (int) $nilai->id,
                );
            } else {
                $this->ensureNilaiIsUnique(
                    (int) $validated['santri_id'],
                    (int) $mataPelajaran->id,
                    $validated['semester'],
                    $validated['tahun_ajaran'],
                    (int) $nilai->id,
                );
            }

            try {
                $nilai->update([
                    'santri_id' => $validated['santri_id'],
                    'mata_pelajaran_id' => $mataPelajaran->id,
                    'kegiatan_id' => $kegiatanId,
                    'jenis_nilai' => $jenisNilai,
                    'semester' => $validated['semester'],
                    'tahun_ajaran' => $validated['tahun_ajaran'],
                    'nilai' => $validated['nilai'],
                    'keterangan' => $validated['keterangan'] ?? null,
                ]);
            } catch (QueryException $exception) {
                $this->throwUniqueViolation($exception);
            }
        });

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

    private function adminIndex(Request $request): View
    {
        $filters = $request->validate([
            'periode_sik_id' => ['nullable', 'integer', Rule::exists('periode_sik', 'id')],
            'kelompok_id' => ['nullable', 'integer', Rule::exists('kelompok', 'id')],
            'semester' => ['nullable', 'string', 'max:50'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $nilaiQuery = Nilai::query()->with(['santri', 'mataPelajaran', 'infrastrukturSik']);

        if (filled($filters['periode_sik_id'] ?? null) || filled($filters['kelompok_id'] ?? null)) {
            $nilaiQuery->whereHas('santri.anggotaPeriode', function ($query) use ($filters): void {
                if (filled($filters['periode_sik_id'] ?? null)) {
                    $query->where('periode_sik_id', (int) $filters['periode_sik_id']);
                }

                if (filled($filters['kelompok_id'] ?? null)) {
                    $query->where('kelompok_id', (int) $filters['kelompok_id']);
                }
            });
        }

        if (filled($filters['semester'] ?? null)) {
            $nilaiQuery->where('semester', $filters['semester']);
        }

        if (filled($filters['tahun_ajaran'] ?? null)) {
            $nilaiQuery->where('tahun_ajaran', $filters['tahun_ajaran']);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $nilaiQuery->whereHas('santri', function ($query) use ($search): void {
                $query->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('nomor_induk', 'like', '%'.$search.'%');
            });
        }

        $periods = PeriodeSik::query()->orderByDesc('tahun')->orderByDesc('id')->get();
        $groupsQuery = Kelompok::query()->orderBy('nama_kelompok')->orderBy('id');
        if (filled($filters['periode_sik_id'] ?? null)) {
            $groupsQuery->where('periode_sik_id', (int) $filters['periode_sik_id']);
        }

        $rataRata = (clone $nilaiQuery)->avg('nilai');
        $summary = [
            'total_nilai' => (clone $nilaiQuery)->count(),
            'total_santri' => (clone $nilaiQuery)->distinct()->count('santri_id'),
            'total_mata_pelajaran' => (clone $nilaiQuery)->distinct()->count('mata_pelajaran_id'),
            'rata_rata' => $rataRata !== null ? round((float) $rataRata, 2) : null,
        ];

        return view('nilai.admin-index', [
            'nilai' => $nilaiQuery->orderBy('tahun_ajaran')->orderBy('semester')->orderBy('id')->paginate(15)->withQueryString(),
            'periods' => $periods,
            'groups' => $groupsQuery->get(),
            'filters' => $filters,
            'search' => $search,
            'summary' => $summary,
        ]);
    }

    private function currentInstructor(Request $request): InfrastrukturSik
    {
        $instruktur = $request->user()?->infrastrukturSik;

        abort_unless($instruktur instanceof InfrastrukturSik, 403, 'Data instruktur tidak ditemukan.');

        return $instruktur;
    }

    private function ensureInstructorCanGrade(InfrastrukturSik $instruktur, int $santriId): void
    {
        $gradeableGroupIds = $this->instructorKelompokService->activeOwnedGroupIds($instruktur);

        $isAssignedToSantri = AnggotaPeriodeSik::query()
            ->where('santri_id', $santriId)
            ->where('status', 'Aktif')
            ->whereIn('kelompok_id', $gradeableGroupIds)
            ->whereHas('kelompok', fn ($query) => $query->where('status', 'Aktif'))
            ->exists();

        abort_unless($isAssignedToSantri, 403, 'Santri bukan anggota kelompok instruktur ini.');
    }

    private function ensureKegiatanDipilih(string $jenisNilai, ?int $kegiatanId): void
    {
        if ($jenisNilai === 'Ujian' && $kegiatanId === null) {
            throw ValidationException::withMessages([
                'kegiatan_id' => 'Kegiatan SIK wajib dipilih untuk nilai ujian akhir.',
            ]);
        }
    }

    private function ensureKegiatanCanGrade(InfrastrukturSik $instruktur, int $santriId, ?int $kegiatanId): void
    {
        if ($kegiatanId === null) {
            return;
        }

        $kegiatan = Kegiatan::query()->findOrFail($kegiatanId);
        $ownedGroupIds = $this->instructorKelompokService->activeOwnedGroupIds($instruktur);

        abort_unless(
            $kegiatan->kelompok_id !== null && $ownedGroupIds->contains($kegiatan->kelompok_id),
            403,
            'Kegiatan SIK bukan kelompok tanggung jawab Anda.'
        );

        $isAnggotaKegiatan = AnggotaPeriodeSik::query()
            ->where('santri_id', $santriId)
            ->where('periode_sik_id', $kegiatan->periode_sik_id)
            ->where('kelompok_id', $kegiatan->kelompok_id)
            ->where('status', 'Aktif')
            ->whereHas('kelompok', fn ($query) => $query->where('status', 'Aktif'))
            ->exists();

        abort_unless($isAnggotaKegiatan, 403, 'Santri bukan anggota kegiatan SIK yang dipilih.');
    }

    private function ensureExamEligible(int $santriId, string $jenisNilai, ?int $kegiatanId): void
    {
        if ($jenisNilai !== 'Ujian') {
            return;
        }

        if ($this->examEligibilityService->forSantri($santriId, $kegiatanId)['eligible']) {
            return;
        }

        throw ValidationException::withMessages([
            'jenis_nilai' => $this->examEligibilityService->rejectionMessage($santriId, $kegiatanId),
        ]);
    }

    private function ensureExamGradeIsUnique(
        int $santriId,
        int $kegiatanId,
        ?int $ignoreNilaiId = null
    ): void {
        $exists = Nilai::query()
            ->where('santri_id', $santriId)
            ->where('kegiatan_id', $kegiatanId)
            ->where('jenis_nilai', 'Ujian')
            ->when($ignoreNilaiId !== null, fn ($query) => $query->whereKeyNot($ignoreNilaiId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'jenis_nilai' => 'Nilai ujian akhir untuk santri ini pada Kegiatan SIK tersebut sudah tersedia. Ubah nilai yang sudah tercatat.',
            ]);
        }
    }

    private function throwUniqueViolation(QueryException $exception): never
    {
        $message = $exception->getMessage();
        $isExamViolation = str_contains($message, 'ujian_kegiatan_id')
            || str_contains($message, 'nilai_ujian_santri_kegiatan_unique');

        if ($isExamViolation) {
            throw ValidationException::withMessages([
                'jenis_nilai' => 'Nilai ujian akhir untuk santri ini pada Kegiatan SIK tersebut sudah tersedia. Ubah nilai yang sudah tercatat.',
            ]);
        }

        throw ValidationException::withMessages([
            'nama_mapel' => 'Nilai untuk mata pelajaran, semester, dan tahun ajaran ini sudah ada. Ubah nilai yang sudah tercatat.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRules(): array
    {
        return [
            'santri_id' => ['required', 'integer', 'exists:santris,id'],
            'kegiatan_id' => ['sometimes', 'nullable', 'integer', 'exists:kegiatan,id'],
            'jenis_nilai' => ['sometimes', Rule::in(['Harian', 'Ujian'])],
            'nama_mapel' => ['required', 'string', 'max:255'],
            'semester' => ['required', 'string', 'max:50'],
            'tahun_ajaran' => ['required', 'string', 'max:20'],
            'nilai' => ['required', 'numeric', 'min:0', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Cari mata pelajaran berdasarkan nama yang diketik (abaikan perbedaan
     * huruf besar/kecil dan spasi). Bila belum ada, buat master baru dengan
     * kode yang dijamin unik sehingga mata pelajaran yang sama tidak
     * terduplikasi dan nilai lama tetap memakai relasi yang sama.
     */
    private function resolveMataPelajaran(string $namaMapel, string $semester): MataPelajaran
    {
        $namaMapel = preg_replace('/\s+/', ' ', trim($namaMapel)) ?? trim($namaMapel);
        $normalized = mb_strtolower(str_replace(' ', '', $namaMapel));

        $existing = MataPelajaran::query()
            ->whereRaw("REPLACE(LOWER(nama_mapel), ' ', '') = ?", [$normalized])
            ->orderByDesc('status')
            ->orderBy('id')
            ->first();

        if ($existing instanceof MataPelajaran) {
            return $existing;
        }

        return MataPelajaran::query()->create([
            'kode_mapel' => $this->generateKodeMapel(),
            'nama_mapel' => $namaMapel,
            'sks' => 1,
            'semester' => ctype_digit($semester) ? max(1, (int) $semester) : 1,
            'status' => true,
        ]);
    }

    private function generateKodeMapel(): string
    {
        $sequence = ((int) MataPelajaran::query()->max('id')) + 1;

        do {
            $kode = 'MP-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (MataPelajaran::query()->where('kode_mapel', $kode)->exists());

        return $kode;
    }

    private function ensureNilaiIsUnique(
        int $santriId,
        int $mataPelajaranId,
        string $semester,
        string $tahunAjaran,
        ?int $ignoreNilaiId = null
    ): void {
        $exists = Nilai::query()
            ->where('santri_id', $santriId)
            ->where('mata_pelajaran_id', $mataPelajaranId)
            ->where('semester', $semester)
            ->where('tahun_ajaran', $tahunAjaran)
            ->when($ignoreNilaiId !== null, fn ($query) => $query->whereKeyNot($ignoreNilaiId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'nama_mapel' => 'Nilai untuk mata pelajaran, semester, dan tahun ajaran ini sudah ada. Ubah nilai yang sudah tercatat.',
            ]);
        }
    }
}
