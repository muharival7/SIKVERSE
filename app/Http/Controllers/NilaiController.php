<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
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
use Illuminate\Support\Collection;
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

        foreach ($santris as $santri) {
            $candidates = $this->gradeableAnggotaPeriodeForInstructor($instruktur, (int) $santri->id);
            $selectedAnggota = $candidates->count() === 1 ? $candidates->first() : null;

            $candidatesWithEligibility = $candidates->map(function (AnggotaPeriodeSik $candidate): array {
                $eligibility = $this->examEligibilityService->forAnggotaPeriode($candidate);

                return [
                    'id' => (int) $candidate->id,
                    'periode_nama' => $candidate->periode?->nama_periode ?? 'Periode',
                    'kelompok_nama' => $candidate->kelompok?->nama_kelompok ?? 'Kelompok',
                    'label' => ($candidate->periode?->nama_periode ?? 'Periode').' · '.($candidate->kelompok?->nama_kelompok ?? 'Kelompok'),
                    'exam_eligibility' => $eligibility,
                ];
            })->values();

            $santri->setAttribute('anggota_periode_nilai_candidates', $candidatesWithEligibility);
            $santri->setAttribute('anggota_periode_nilai_id', $selectedAnggota?->id);
            $santri->setAttribute('anggota_periode_nilai_ambiguous', $candidates->count() > 1);
            $santri->setAttribute(
                'exam_eligibility',
                $selectedAnggota instanceof AnggotaPeriodeSik
                    ? ($candidatesWithEligibility->firstWhere('id', (int) $selectedAnggota->id)['exam_eligibility'] ?? $this->examEligibilityService->forAnggotaPeriode($selectedAnggota))
                    : [
                        'eligible' => false,
                        'metric' => null,
                        'alasan' => $candidates->count() > 1
                            ? 'Pilih konteks periode/kelompok santri terlebih dahulu untuk mengevaluasi kelayakan ujian.'
                            : 'Konteks keanggotaan periode santri tidak ditemukan.',
                    ]
            );
        }

        return view('nilai.index', compact(
            'santris',
            'mataPelajaran',
            'kelompoks',
            'selectedGroupIds',
            'search'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $instruktur = $this->currentInstructor($request);
        $validated = $request->validate($this->validationRules());
        $normalized = $this->normalizeNilaiInput($validated);

        $anggota = $this->resolveAnggotaPeriodeForInstructor(
            $instruktur,
            (int) $normalized['santri_id'],
            $normalized['anggota_periode_sik_id']
        );

        $this->ensureExamEligible($anggota, $normalized['jenis_nilai']);

        DB::transaction(function () use ($normalized, $instruktur, $anggota): void {
            $mataPelajaran = $this->resolveMataPelajaran(
                $normalized['nama_mapel'],
                $normalized['semester']
            );

            if ($normalized['jenis_nilai'] === 'Ujian') {
                $this->ensureExamGradeIsUnique(
                    (int) $anggota->santri_id,
                    (int) $mataPelajaran->id,
                    $normalized['semester'],
                    $normalized['tahun_ajaran'],
                );
            } else {
                $this->ensureNilaiIsUnique(
                    (int) $anggota->santri_id,
                    (int) $mataPelajaran->id,
                    $normalized['semester'],
                    $normalized['tahun_ajaran'],
                );
            }

            try {
                Nilai::create([
                    'santri_id' => $anggota->santri_id,
                    'mata_pelajaran_id' => $mataPelajaran->id,
                    'kegiatan_id' => null,
                    'nama_kegiatan' => $normalized['nama_kegiatan'],
                    'jenis_nilai' => $normalized['jenis_nilai'],
                    'infrastruktur_sik_id' => $instruktur->id,
                    'semester' => $normalized['semester'],
                    'tahun_ajaran' => $normalized['tahun_ajaran'],
                    'nilai' => $normalized['nilai'],
                    'keterangan' => $normalized['keterangan'],
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
        $normalized = $this->normalizeNilaiInput($validated);

        $anggota = $this->resolveAnggotaPeriodeForInstructor(
            $instruktur,
            (int) $normalized['santri_id'],
            $normalized['anggota_periode_sik_id']
        );

        $this->ensureExamEligible($anggota, $normalized['jenis_nilai']);

        DB::transaction(function () use ($nilai, $normalized, $anggota): void {
            $mataPelajaran = $this->resolveMataPelajaran(
                $normalized['nama_mapel'],
                $normalized['semester']
            );

            if ($normalized['jenis_nilai'] === 'Ujian') {
                $this->ensureExamGradeIsUnique(
                    (int) $anggota->santri_id,
                    (int) $mataPelajaran->id,
                    $normalized['semester'],
                    $normalized['tahun_ajaran'],
                    (int) $nilai->id,
                );
            } else {
                $this->ensureNilaiIsUnique(
                    (int) $anggota->santri_id,
                    (int) $mataPelajaran->id,
                    $normalized['semester'],
                    $normalized['tahun_ajaran'],
                    (int) $nilai->id,
                );
            }

            try {
                $nilai->update([
                    'santri_id' => $anggota->santri_id,
                    'mata_pelajaran_id' => $mataPelajaran->id,
                    // Pertahankan relasi kegiatan historis. Nilai baru tetap kegiatan_id=null;
                    // update tidak boleh menghapus relasi lama hanya karena form memakai nama_kegiatan teks.
                    'kegiatan_id' => $nilai->kegiatan_id,
                    'nama_kegiatan' => $normalized['nama_kegiatan'],
                    'jenis_nilai' => $normalized['jenis_nilai'],
                    'semester' => $normalized['semester'],
                    'tahun_ajaran' => $normalized['tahun_ajaran'],
                    'nilai' => $normalized['nilai'],
                    'keterangan' => $normalized['keterangan'],
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

    /**
     * @return Collection<int, AnggotaPeriodeSik>
     */
    private function gradeableAnggotaPeriodeForInstructor(InfrastrukturSik $instruktur, int $santriId): Collection
    {
        $gradeableGroupIds = $this->instructorKelompokService->activeOwnedGroupIds($instruktur);

        if ($gradeableGroupIds->isEmpty()) {
            return collect();
        }

        return AnggotaPeriodeSik::query()
            ->with(['kelompok', 'periode'])
            ->where('santri_id', $santriId)
            ->where('status', 'Aktif')
            ->whereIn('kelompok_id', $gradeableGroupIds)
            ->whereHas('kelompok', fn ($groupQuery) => $groupQuery->where('status', 'Aktif'))
            ->orderBy('periode_sik_id')
            ->orderBy('kelompok_id')
            ->orderBy('id')
            ->get();
    }

    private function resolveAnggotaPeriodeForInstructor(
        InfrastrukturSik $instruktur,
        int $santriId,
        ?int $anggotaPeriodeSikId
    ): AnggotaPeriodeSik {
        $candidates = $this->gradeableAnggotaPeriodeForInstructor($instruktur, $santriId);

        if ($anggotaPeriodeSikId !== null) {
            $anggota = $candidates->firstWhere('id', $anggotaPeriodeSikId);

            if ($anggota instanceof AnggotaPeriodeSik) {
                return $anggota;
            }

            throw ValidationException::withMessages([
                'anggota_periode_sik_id' => 'Konteks keanggotaan periode santri tidak valid atau bukan kewenangan Anda.',
            ]);
        }

        if ($candidates->count() === 1) {
            $anggota = $candidates->first();

            if ($anggota instanceof AnggotaPeriodeSik) {
                return $anggota;
            }
        }

        if ($candidates->isEmpty()) {
            abort(403, 'Santri bukan anggota kelompok instruktur ini.');
        }

        throw ValidationException::withMessages([
            'anggota_periode_sik_id' => 'Konteks keanggotaan periode santri ambigu. Pilih konteks periode/kelompok secara eksplisit.',
        ]);
    }

    private function ensureExamEligible(AnggotaPeriodeSik $anggota, string $jenisNilai): void
    {
        if ($jenisNilai !== 'Ujian') {
            return;
        }

        if ($this->examEligibilityService->forAnggotaPeriode($anggota)['eligible']) {
            return;
        }

        throw ValidationException::withMessages([
            'jenis_nilai' => $this->examEligibilityService->rejectionMessageForAnggotaPeriode($anggota),
        ]);
    }

    private function ensureExamGradeIsUnique(
        int $santriId,
        int $mataPelajaranId,
        string $semester,
        string $tahunAjaran,
        ?int $ignoreNilaiId = null
    ): void {
        $exists = Nilai::query()
            ->where('santri_id', $santriId)
            ->where('mata_pelajaran_id', $mataPelajaranId)
            ->where('jenis_nilai', 'Ujian')
            ->whereRaw('TRIM(semester) = ?', [$semester])
            ->whereRaw('TRIM(tahun_ajaran) = ?', [$tahunAjaran])
            ->when($ignoreNilaiId !== null, fn ($query) => $query->whereKeyNot($ignoreNilaiId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'jenis_nilai' => 'Nilai ujian untuk santri, mata pelajaran, semester, dan tahun ajaran ini sudah tersedia. Ubah nilai yang sudah tercatat.',
            ]);
        }
    }

    private function throwUniqueViolation(QueryException $exception): never
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;
        $message = $exception->getMessage();

        $isUnique = $sqlState === '23000' && (int) $driverCode === 1062;
        $isExamViolation = $isUnique && (
            str_contains($message, 'nilai_ujian_santri_mapel_semester_tahun_unique')
            || str_contains($message, 'ujian_santri_id')
        );

        if ($isExamViolation) {
            throw ValidationException::withMessages([
                'jenis_nilai' => 'Nilai ujian untuk santri, mata pelajaran, semester, dan tahun ajaran ini sudah tersedia. Ubah nilai yang sudah tercatat.',
            ]);
        }

        $isDailyViolation = $isUnique && (
            str_contains($message, 'nilai_harian_santri_mapel_semester_tahun_unique')
            || str_contains($message, 'harian_santri_id')
        );

        if ($isDailyViolation) {
            throw ValidationException::withMessages([
                'nama_mapel' => 'Nilai untuk mata pelajaran, semester, dan tahun ajaran ini sudah ada. Ubah nilai yang sudah tercatat.',
            ]);
        }

        throw $exception;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     santri_id: int,
     *     anggota_periode_sik_id: int|null,
     *     nama_kegiatan: string,
     *     jenis_nilai: string,
     *     nama_mapel: string,
     *     semester: string,
     *     tahun_ajaran: string,
     *     nilai: mixed,
     *     keterangan: string|null
     * }
     */
    private function normalizeNilaiInput(array $validated): array
    {
        $namaKegiatan = preg_replace('/\s+/', ' ', trim((string) ($validated['nama_kegiatan'] ?? ''))) ?? trim((string) ($validated['nama_kegiatan'] ?? ''));
        $semester = trim((string) ($validated['semester'] ?? ''));
        $tahunAjaran = trim((string) ($validated['tahun_ajaran'] ?? ''));
        $namaMapel = trim((string) ($validated['nama_mapel'] ?? ''));
        $keterangan = $validated['keterangan'] ?? null;
        $keterangan = is_string($keterangan) ? trim($keterangan) : null;

        return [
            'santri_id' => (int) $validated['santri_id'],
            'anggota_periode_sik_id' => filled($validated['anggota_periode_sik_id'] ?? null)
                ? (int) $validated['anggota_periode_sik_id']
                : null,
            'nama_kegiatan' => $namaKegiatan,
            'jenis_nilai' => (string) ($validated['jenis_nilai'] ?? 'Harian'),
            'nama_mapel' => $namaMapel,
            'semester' => $semester,
            'tahun_ajaran' => $tahunAjaran,
            'nilai' => $validated['nilai'],
            'keterangan' => $keterangan !== '' ? $keterangan : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRules(): array
    {
        return [
            'santri_id' => ['required', 'integer', 'exists:santris,id'],
            'anggota_periode_sik_id' => ['nullable', 'integer', 'exists:anggota_periode_sik,id'],
            'nama_kegiatan' => ['required', 'string', 'max:150'],
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
            ->whereRaw('TRIM(semester) = ?', [$semester])
            ->whereRaw('TRIM(tahun_ajaran) = ?', [$tahunAjaran])
            ->when($ignoreNilaiId !== null, fn ($query) => $query->whereKeyNot($ignoreNilaiId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'nama_mapel' => 'Nilai untuk mata pelajaran, semester, dan tahun ajaran ini sudah ada. Ubah nilai yang sudah tercatat.',
            ]);
        }
    }
}
