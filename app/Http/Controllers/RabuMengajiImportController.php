<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\Pertemuan;
use App\Models\Santri;
use App\Models\User;
use App\Services\AttendanceAuthorizationService;
use App\Services\AttendanceRecapService;
use App\Services\RabuMengajiSpreadsheet;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RabuMengajiImportController extends Controller
{
    public function __construct(
        private readonly AttendanceRecapService $attendanceRecapService,
        private readonly AttendanceAuthorizationService $attendanceAuthorizationService
    ) {}

    public function importForm(Request $request): View
    {
        $oldToken = $request->session()->pull('rabu_mengaji_import_token');
        $request->session()->forget('rabu_mengaji_import_rows');

        if (is_string($oldToken) && Str::isUuid($oldToken)) {
            Storage::disk('local')->delete($this->importStoragePath($oldToken));
        }

        $pertemuan = Pertemuan::with(['kegiatan', 'kelompokHost'])
            ->whereHas(
                'kegiatan',
                fn ($query) => $this->attendanceAuthorizationService->scopeSharedActivity($query)
            )
            ->orderByDesc('tanggal')
            ->get();

        return view('rabu-mengaji.import', compact('pertemuan'));
    }

    public function downloadTemplate(RabuMengajiSpreadsheet $spreadsheet): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($spreadsheet): void {
                echo $spreadsheet->template();
            },
            'template-kehadiran-rabu-mengaji.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, private',
            ]
        );
    }

    public function previewImport(Request $request, RabuMengajiSpreadsheet $spreadsheet): View|RedirectResponse
    {
        $validated = $request->validate([
            'pertemuan_id' => ['required', 'integer', 'exists:pertemuan,id'],
            'file' => ['required', 'file', 'extensions:xlsx', 'max:10240'],
        ]);

        $pertemuan = Pertemuan::with(['kegiatan', 'kelompokHost'])->findOrFail($validated['pertemuan_id']);
        $kegiatan = $pertemuan->kegiatan;

        if (! $kegiatan instanceof Kegiatan || ! $this->attendanceAuthorizationService->isSharedActivity($kegiatan)) {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Pertemuan bukan bagian dari kegiatan Rabu Mengaji.',
            ]);
        }

        $this->attendanceAuthorizationService->authorizePertemuanAccess(
            $pertemuan,
            $this->resolveInstruktur($request)
        );

        try {
            $rows = $spreadsheet->read($request->file('file')->getPathname());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'File Excel belum memiliki baris data.']);
        }

        $oldToken = $request->session()->get('rabu_mengaji_import_token');

        if (is_string($oldToken) && Str::isUuid($oldToken)) {
            Storage::disk('local')->delete($this->importStoragePath($oldToken));
        }

        $token = (string) Str::uuid();
        $path = $this->importStoragePath($token);

        if (! Storage::disk('local')->put($path, $request->file('file')->getContent())) {
            throw ValidationException::withMessages(['file' => 'File Excel tidak dapat disimpan untuk proses pratinjau.']);
        }

        $request->session()->put('rabu_mengaji_import_token', $token);
        $request->session()->put('rabu_mengaji_import_pertemuan_id', $pertemuan->id);
        $request->session()->forget('rabu_mengaji_import_rows');

        return $this->renderImportPreview($request, $rows, $pertemuan);
    }

    public function recheckImport(Request $request, RabuMengajiSpreadsheet $spreadsheet): View|RedirectResponse
    {
        $validated = $request->validate([
            'import_token' => ['required', 'uuid'],
        ]);

        abort_unless(hash_equals((string) $request->session()->get('rabu_mengaji_import_token'), $validated['import_token']), 403);

        $path = $this->importStoragePath($validated['import_token']);

        if (! Storage::disk('local')->exists($path)) {
            $request->session()->forget('rabu_mengaji_import_token');
            $request->session()->forget('rabu_mengaji_import_pertemuan_id');

            return redirect()->route('rabu-mengaji.import')
                ->withErrors(['file' => 'File pratinjau sudah kedaluwarsa. Silakan unggah kembali.']);
        }

        try {
            $rows = $spreadsheet->read(Storage::disk('local')->path($path));
        } catch (RuntimeException $exception) {
            return redirect()->route('rabu-mengaji.import')
                ->withErrors(['file' => $exception->getMessage()]);
        }

        $pertemuanId = (int) ($request->session()->get('rabu_mengaji_import_pertemuan_id') ?? 0);
        $pertemuan = Pertemuan::with(['kegiatan', 'kelompokHost'])->find($pertemuanId);

        if (! $pertemuan instanceof Pertemuan) {
            $request->session()->forget('rabu_mengaji_import_token');
            $request->session()->forget('rabu_mengaji_import_pertemuan_id');
            Storage::disk('local')->delete($path);

            return redirect()->route('rabu-mengaji.import')
                ->withErrors(['pertemuan_id' => 'Pertemuan tidak ditemukan. Silakan unggah kembali.']);
        }

        $kegiatan = $pertemuan->kegiatan;

        if (! $kegiatan instanceof Kegiatan || ! $this->attendanceAuthorizationService->isSharedActivity($kegiatan)) {
            abort(404, 'Pertemuan bukan bagian dari kegiatan Rabu Mengaji.');
        }

        $this->attendanceAuthorizationService->authorizePertemuanAccess(
            $pertemuan,
            $this->resolveInstruktur($request)
        );

        return $this->renderImportPreview($request, $rows, $pertemuan);
    }

    public function commitImport(Request $request, RabuMengajiSpreadsheet $spreadsheet): RedirectResponse
    {
        $validated = $request->validate([
            'import_token' => ['required', 'uuid'],
        ]);

        abort_unless(hash_equals((string) $request->session()->get('rabu_mengaji_import_token'), $validated['import_token']), 403);

        $path = $this->importStoragePath($validated['import_token']);

        if (! Storage::disk('local')->exists($path)) {
            $request->session()->forget('rabu_mengaji_import_token');
            $request->session()->forget('rabu_mengaji_import_pertemuan_id');

            return redirect()->route('rabu-mengaji.import')
                ->withErrors(['file' => 'File pratinjau sudah kedaluwarsa. Silakan unggah kembali.']);
        }

        try {
            $rows = $spreadsheet->read(Storage::disk('local')->path($path));
        } catch (RuntimeException $exception) {
            return redirect()->route('rabu-mengaji.import')
                ->withErrors(['file' => $exception->getMessage()]);
        }

        $pertemuanId = (int) ($request->session()->get('rabu_mengaji_import_pertemuan_id') ?? 0);
        $pertemuan = Pertemuan::with(['kegiatan', 'kelompokHost'])->find($pertemuanId);

        if (! $pertemuan instanceof Pertemuan) {
            $request->session()->forget('rabu_mengaji_import_token');
            $request->session()->forget('rabu_mengaji_import_pertemuan_id');
            Storage::disk('local')->delete($path);

            return redirect()->route('rabu-mengaji.import')
                ->withErrors(['pertemuan_id' => 'Pertemuan tidak ditemukan. Silakan unggah kembali.']);
        }

        $instruktur = $this->resolveInstruktur($request);

        $kegiatan = $pertemuan->kegiatan;

        if (! $kegiatan instanceof Kegiatan || ! $this->attendanceAuthorizationService->isSharedActivity($kegiatan)) {
            abort(404, 'Pertemuan bukan bagian dari kegiatan Rabu Mengaji.');
        }

        $this->attendanceAuthorizationService->authorizePertemuanAccess($pertemuan, $instruktur);

        $analysis = $this->analyzeRows($rows, $pertemuan, $instruktur);
        $validRows = array_values(array_filter($analysis['rows'], fn (array $row): bool => $row['category'] === 'valid'));

        if ($validRows === []) {
            $request->session()->forget('rabu_mengaji_import_token');
            $request->session()->forget('rabu_mengaji_import_pertemuan_id');
            Storage::disk('local')->delete($path);

            // preview route hanya menerima POST, sehingga redirect GET ke route
            // tersebut menghasilkan 405. Kembali ke form import dengan error.
            return redirect()->route('rabu-mengaji.import')
                ->withErrors(['file' => 'Tidak ada baris yang valid untuk disimpan.']);
        }

        $created = 0;
        $skipped = 0;

        try {
            DB::transaction(function () use ($validRows, $pertemuan, $instruktur, &$created, &$skipped): void {
                foreach ($validRows as $row) {
                    $data = $row['data'];

                    $this->attendanceAuthorizationService->authorize(
                        $data['anggota'],
                        $pertemuan,
                        $instruktur
                    );

                    $existing = AbsensiSik::where('anggota_periode_sik_id', $data['anggota']->id)
                        ->where('pertemuan_id', $pertemuan->id)
                        ->first();

                    if ($existing instanceof AbsensiSik) {
                        $skipped++;

                        continue;
                    }

                    AbsensiSik::create([
                        'anggota_periode_sik_id' => $data['anggota']->id,
                        'pertemuan_id' => $pertemuan->id,
                        'status' => $data['status_kehadiran'],
                        'keterangan' => $data['keterangan'] ?? null,
                        'metode_absensi' => 'Manual',
                        'waktu_masuk' => null,
                        'waktu_pulang' => null,
                        'dicatat_oleh' => $instruktur?->id,
                    ]);

                    $this->attendanceRecapService->syncForAbsensi(AbsensiSik::where('anggota_periode_sik_id', $data['anggota']->id)
                        ->where('pertemuan_id', $pertemuan->id)
                        ->first());

                    $created++;
                }
            });
        } catch (QueryException|RuntimeException $exception) {
            report($exception);

            return redirect()->route('rabu-mengaji.import')
                ->withErrors(['file' => 'Gagal menyimpan data kehadiran. Silakan coba lagi.']);
        }

        $request->session()->forget('rabu_mengaji_import_token');
        $request->session()->forget('rabu_mengaji_import_pertemuan_id');
        Storage::disk('local')->delete($path);

        return redirect()->route('rabu-mengaji.index')
            ->with('import_result', [
                'valid' => $analysis['valid'],
                'invalid' => $analysis['invalid'],
                'duplicate' => $analysis['duplicate'],
                'created' => $created,
                'skipped' => $skipped,
            ]);
    }

    /**
     * @param  list<array{row: int, sheet: string, nim: string, nama_santri: string, tanggal: string, status_kehadiran: string, keterangan: string}>  $rows
     * @return array{rows: list<array{row: int, sheet: string, category: string, reasons: list<string>, data: array<string, mixed>}>, valid: int, invalid: int, duplicate: int}
     */
    private function analyzeRows(array $rows, Pertemuan $pertemuan, ?InfrastrukturSik $instruktur): array
    {
        $kegiatan = $pertemuan->kegiatan;
        $sharedActivity = $kegiatan instanceof Kegiatan
            && $this->attendanceAuthorizationService->isSharedActivity($kegiatan);
        $targetGroupId = $sharedActivity
            ? null
            : ($pertemuan->kelompok_host_id ?? ($kegiatan instanceof Kegiatan ? $kegiatan->kelompok_id : null));
        $meetingDate = $pertemuan->tanggal?->toDateString() ?? '';

        $nimList = array_values(array_unique(array_filter(
            array_map(fn (array $row): string => trim($row['nim']), $rows),
            fn (string $nim): bool => $nim !== ''
        )));

        $santris = Santri::query()->whereIn('nomor_induk', $nimList)->get()->keyBy('nomor_induk');

        $analyzedRows = [];
        $validCount = 0;
        $invalidCount = 0;
        $duplicateCount = 0;
        $seenNims = [];

        foreach ($rows as $row) {
            $nim = trim($row['nim']);
            $namaSantri = trim($row['nama_santri']);
            $tanggal = trim($row['tanggal']);
            $status = trim($row['status_kehadiran']);
            $keterangan = trim($row['keterangan']);
            $reasons = [];

            $isDuplicate = $nim !== '' && isset($seenNims[$nim]);

            if ($nim === '') {
                $reasons[] = 'NIM wajib diisi.';
            }

            if ($namaSantri === '') {
                $reasons[] = 'Nama santri wajib diisi.';
            }

            if ($tanggal === '') {
                $reasons[] = 'Tanggal wajib diisi.';
            } elseif ($tanggal !== $meetingDate) {
                $reasons[] = 'Tanggal tidak cocok dengan tanggal pertemuan ('.$meetingDate.').';
            }

            if (! in_array($status, ['Hadir', 'Izin', 'Sakit', 'Alpha'], true)) {
                $reasons[] = 'Status kehadiran harus Hadir, Izin, Sakit, atau Alpha.';
            }

            $santri = $santris->get($nim);

            if ($santri === null) {
                $reasons[] = 'NIM '.$nim.' tidak terdaftar sebagai santri.';
            } elseif ($namaSantri !== '' && mb_strtolower($namaSantri) !== mb_strtolower($santri->nama)) {
                $reasons[] = 'Nama santri tidak cocok dengan NIM '.$nim.'.';
            }

            if ($santri !== null) {
                $anggota = AnggotaPeriodeSik::with(['kelompok', 'periode'])
                    ->where('santri_id', $santri->id)
                    ->where('status', 'Aktif')
                    ->first();

                if ($anggota === null) {
                    $reasons[] = 'Santri '.$nim.' tidak memiliki keanggiatan periode SIK yang aktif.';
                } else {
                    if ($sharedActivity) {
                        $kelompok = $anggota->kelompok;

                        if (! $kelompok instanceof Kelompok || $kelompok->status !== 'Aktif') {
                            $reasons[] = 'Santri '.$nim.' tidak memiliki kelompok asal yang aktif.';
                        } elseif ($kegiatan instanceof Kegiatan
                            && (int) $kelompok->periode_sik_id !== (int) $kegiatan->periode_sik_id) {
                            $reasons[] = 'Kelompok asal santri '.$nim.' tidak berada pada periode SIK yang sama dengan kegiatan.';
                        }
                    } elseif ($targetGroupId !== null && (int) $anggota->kelompok_id !== (int) $targetGroupId) {
                        $reasons[] = 'Santri '.$nim.' bukan anggota kelompok target pertemuan.';
                    }

                    if ($kegiatan instanceof Kegiatan && (int) $anggota->periode_sik_id !== (int) $kegiatan->periode_sik_id) {
                        $reasons[] = 'Santri '.$nim.' tidak berada pada periode SIK yang sama dengan kegiatan.';
                    }

                    if ($instruktur instanceof InfrastrukturSik && ! $sharedActivity) {
                        $isAssigned = (int) $anggota->kelompok?->instruktur_id === (int) $instruktur->id
                            || (int) $pertemuan->kelompokHost?->instruktur_id === (int) $instruktur->id
                            || (int) $pertemuan->instruktur_pengganti_id === (int) $instruktur->id;

                        if (! $isAssigned) {
                            $reasons[] = 'Anda tidak berwenang menginput kehadiran untuk santri '.$nim.'.';
                        }
                    }

                    $existingAbsensi = AbsensiSik::where('anggota_periode_sik_id', $anggota->id)
                        ->where('pertemuan_id', $pertemuan->id)
                        ->first();

                    if ($existingAbsensi instanceof AbsensiSik) {
                        $reasons[] = 'Absensi untuk santri '.$nim.' pada pertemuan ini sudah tercatat.';
                    }
                }
            }

            if ($isDuplicate) {
                $reasons[] = 'NIM '.$nim.' muncul lebih dari sekali dalam file.';
            }

            $category = $isDuplicate ? 'duplicate' : (count($reasons) > 0 ? 'invalid' : 'valid');

            if ($category === 'valid') {
                $validCount++;
            } elseif ($category === 'duplicate') {
                $duplicateCount++;
            } else {
                $invalidCount++;
            }

            if ($nim !== '') {
                $seenNims[$nim] = true;
            }

            $analyzedRows[] = [
                'row' => $row['row'],
                'sheet' => $row['sheet'],
                'category' => $category,
                'reasons' => $reasons,
                'data' => [
                    'nim' => $nim,
                    'nama_santri' => $namaSantri,
                    'tanggal' => $tanggal,
                    'status_kehadiran' => $status,
                    'keterangan' => $keterangan,
                    'santri' => $santri ?? null,
                    'anggota' => $santri !== null && isset($anggota) ? $anggota : null,
                ],
            ];
        }

        return [
            'rows' => $analyzedRows,
            'valid' => $validCount,
            'invalid' => $invalidCount,
            'duplicate' => $duplicateCount,
        ];
    }

    private function renderImportPreview(Request $request, array $rows, Pertemuan $pertemuan): View
    {
        $analysis = $this->analyzeRows($rows, $pertemuan, $this->resolveInstruktur($request));
        $importToken = $request->session()->get('rabu_mengaji_import_token');

        return view('rabu-mengaji.import-preview', compact(
            'pertemuan',
            'analysis',
            'importToken'
        ));
    }

    private function resolveInstruktur(Request $request): ?InfrastrukturSik
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if ($user->role !== UserRole::InfrastrukturSik) {
            return null;
        }

        $instruktur = $user->infrastrukturSik;
        abort_unless($instruktur instanceof InfrastrukturSik, 403, 'Data instruktur tidak ditemukan.');

        return $instruktur;
    }

    private function importStoragePath(string $token): string
    {
        return 'imports/rabu-mengaji/'.$token.'.xlsx';
    }
}
