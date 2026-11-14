<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AnggotaPeriodeSik;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Santri;
use App\Models\User;
use App\Services\SantriSpreadsheet;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SantriController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['Aktif', 'Tidak Aktif'])],
            'periode_sik_id' => ['nullable', 'integer', 'exists:periode_sik,id'],
            'kelompok_id' => ['nullable', 'integer', 'exists:kelompok,id'],
        ]);

        $query = Santri::query()
            ->with(['fingerprint', 'anggotaPeriodeTerbaru.periode', 'anggotaPeriodeTerbaru.kelompok']);

        if (filled($filters['search'] ?? null)) {
            $search = trim($filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('nomor_induk', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('email', 'like', "%{$search}%"));
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['periode_sik_id'] ?? null)) {
            $query->whereHas('anggotaPeriode', fn ($builder) => $builder->where('periode_sik_id', $filters['periode_sik_id']));
        }

        if (filled($filters['kelompok_id'] ?? null)) {
            $query->whereHas('anggotaPeriode', fn ($builder) => $builder->where('kelompok_id', $filters['kelompok_id']));
        }

        $santris = $query->latest()->paginate(15)->withQueryString();
        $totalSantri = Santri::query()->count();
        $santriAktif = Santri::query()->where('status', 'Aktif')->count();
        $periodes = PeriodeSik::query()->orderByDesc('tahun')->orderByDesc('id')->get();
        $kelompoks = Kelompok::query()->with('periode')->orderBy('nama_kelompok')->get();

        return view('santris.index', compact(
            'santris', 'filters', 'totalSantri', 'santriAktif', 'periodes', 'kelompoks'
        ));
    }

    public function importForm(Request $request): View
    {
        $oldToken = $request->session()->pull('santri_import_token');
        $request->session()->forget('santri_import_corrections');

        if (is_string($oldToken) && Str::isUuid($oldToken)) {
            Storage::disk('local')->delete($this->importStoragePath((int) $request->user()->id, $oldToken));
        }

        return view('santris.import');
    }

    public function downloadImportTemplate(SantriSpreadsheet $spreadsheet): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($spreadsheet): void {
                echo $spreadsheet->template();
            },
            'template-data-santri.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, private',
            ]
        );
    }

    public function downloadImportCredentials(Request $request, string $token): BinaryFileResponse
    {
        $sessionToken = $request->session()->get('santri_credentials_token');
        abort_unless(is_string($sessionToken) && hash_equals($sessionToken, $token), 403);

        $path = $this->credentialsStoragePath((int) $request->user()->id, $token);
        abort_unless(Storage::disk('local')->exists($path), 404);
        $request->session()->forget('santri_credentials_token');

        return response()->download(
            Storage::disk('local')->path($path),
            'kredensial-santri.csv',
            ['Cache-Control' => 'no-store, private']
        )->deleteFileAfterSend(true);
    }

    public function previewImport(Request $request, SantriSpreadsheet $spreadsheet): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx', 'max:10240'],
        ]);

        try {
            $rows = $spreadsheet->read($request->file('file')->getPathname());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'File Excel belum memiliki baris data.']);
        }

        $oldToken = $request->session()->get('santri_import_token');

        if (is_string($oldToken) && Str::isUuid($oldToken)) {
            Storage::disk('local')->delete($this->importStoragePath((int) $request->user()->id, $oldToken));
        }

        $token = (string) Str::uuid();
        $path = $this->importStoragePath((int) $request->user()->id, $token);

        if (! Storage::disk('local')->put($path, $request->file('file')->getContent())) {
            throw ValidationException::withMessages(['file' => 'File Excel tidak dapat disimpan untuk proses pratinjau.']);
        }

        $request->session()->put('santri_import_token', $token);
        $request->session()->forget('santri_import_corrections');

        return $this->renderImportPreview($request, $rows);
    }

    public function recheckImport(Request $request, SantriSpreadsheet $spreadsheet): View|RedirectResponse
    {
        $validated = $request->validate([
            'import_token' => ['required', 'uuid'],
            'corrections' => ['nullable', 'array'],
            'corrections.*' => ['array:jenis_kelamin'],
            'corrections.*.jenis_kelamin' => ['nullable', Rule::in(['Laki-laki', 'Perempuan'])],
        ]);

        abort_unless(hash_equals((string) $request->session()->get('santri_import_token'), $validated['import_token']), 403);

        $path = $this->importStoragePath((int) $request->user()->id, $validated['import_token']);

        if (! Storage::disk('local')->exists($path)) {
            $request->session()->forget('santri_import_token');

            return redirect()->route('santris.import')
                ->withErrors(['file' => 'File pratinjau sudah kedaluwarsa. Silakan unggah kembali.']);
        }

        try {
            $rows = $spreadsheet->read(Storage::disk('local')->path($path));
        } catch (RuntimeException $exception) {
            return redirect()->route('santris.import')
                ->withErrors(['file' => $exception->getMessage()]);
        }

        $corrections = $this->normalizeImportCorrections($validated['corrections'] ?? [], $rows);
        $request->session()->put('santri_import_corrections', $corrections);

        return $this->renderImportPreview($request, $rows, $corrections);
    }

    public function commitImport(Request $request, SantriSpreadsheet $spreadsheet): RedirectResponse|View
    {
        $validated = $request->validate([
            'import_token' => ['required', 'uuid'],
            'corrections' => ['nullable', 'array'],
            'corrections.*' => ['array:jenis_kelamin'],
            'corrections.*.jenis_kelamin' => ['nullable', Rule::in(['Laki-laki', 'Perempuan'])],
        ]);

        abort_unless(hash_equals((string) $request->session()->get('santri_import_token'), $validated['import_token']), 403);

        $path = $this->importStoragePath((int) $request->user()->id, $validated['import_token']);

        if (! Storage::disk('local')->exists($path)) {
            $request->session()->forget('santri_import_token');

            return redirect()->route('santris.import')
                ->withErrors(['file' => 'File pratinjau sudah kedaluwarsa. Silakan unggah kembali.']);
        }

        try {
            $rows = $spreadsheet->read(Storage::disk('local')->path($path));
        } catch (RuntimeException $exception) {
            return redirect()->route('santris.import')
                ->withErrors(['file' => $exception->getMessage()]);
        }

        $corrections = $this->normalizeImportCorrections(
            $validated['corrections'] ?? $request->session()->get('santri_import_corrections', []),
            $rows
        );
        $request->session()->put('santri_import_corrections', $corrections);
        $rows = $this->applyImportCorrections($rows, $corrections);
        $preview = $this->analyzeImportRows($rows);
        $validRows = array_values(array_filter($preview['rows'], fn (array $row): bool => $row['category'] === 'valid'));

        if ($validRows === []) {
            return $this->renderImportPreview($request, $rows, $corrections);
        }

        $credentials = [];
        $credentialToken = null;
        $credentialPath = null;

        $groupIds = array_values(array_unique(array_filter(array_map(
            fn (array $row): ?int => $row['data']['existing_group_id'],
            $validRows
        ))));
        $groupsById = Kelompok::query()
            ->whereIn('id', $groupIds)
            ->where('status', 'Aktif')
            ->get()
            ->keyBy('id');

        try {
            DB::transaction(function () use (
                $validRows,
                $groupsById,
                &$credentials,
                &$credentialToken,
                &$credentialPath,
                $request
            ): void {
                foreach ($validRows as $row) {
                    $data = $row['data'];

                    $group = $groupsById->get($data['existing_group_id']);

                    if ($group === null || (int) $group->periode_sik_id !== (int) $data['periode_sik_id']) {
                        throw new RuntimeException('Kelompok atau periode berubah setelah pratinjau. Periksa ulang file sebelum import.');
                    }

                    $temporaryPassword = Str::random(16);
                    $email = $data['email'] !== '' ? mb_strtolower($data['email']) : mb_strtolower($data['nomor_induk']).'@sik.test';

                    $user = new User;
                    $user->name = $data['nama'];
                    $user->email = $email;
                    $user->email_verified_at = config('sik.auth.require_email_verification') ? null : now();
                    $user->password = $temporaryPassword;
                    $user->role = UserRole::Santri;
                    $user->must_change_password = true;
                    $user->save();

                    $santri = Santri::create([
                        'user_id' => $user->id,
                        'nomor_induk' => $data['nomor_induk'],
                        'nama' => $data['nama'],
                        'jenis_kelamin' => $data['jenis_kelamin'],
                        'program_studi' => $data['program_studi'] !== '' ? $data['program_studi'] : null,
                        'status' => $data['status'],
                    ]);

                    AnggotaPeriodeSik::create([
                        'santri_id' => $santri->id,
                        'periode_sik_id' => $data['periode_sik_id'],
                        'kelompok_id' => $group->id,
                        'tanggal_masuk' => now()->toDateString(),
                        'status' => $data['status'],
                    ]);

                    $credentials[] = [
                        'nomor_induk' => $data['nomor_induk'],
                        'email' => $email,
                        'password' => $temporaryPassword,
                    ];

                    if (! $user->hasVerifiedEmail()) {
                        $credentials[array_key_last($credentials)]['user_id'] = $user->id;
                    }
                }

                $credentialToken = (string) Str::uuid();
                $credentialPath = $this->credentialsStoragePath((int) $request->user()->id, $credentialToken);

                if (! Storage::disk('local')->put($credentialPath, $this->credentialsCsv($credentials))) {
                    throw new RuntimeException('File kredensial sementara tidak dapat disimpan.');
                }
            });
        } catch (QueryException|RuntimeException $exception) {
            report($exception);

            if (is_string($credentialPath)) {
                Storage::disk('local')->delete($credentialPath);
            }

            $request->session()->forget('santri_import_token');
            $request->session()->forget('santri_import_corrections');
            Storage::disk('local')->delete($path);

            return redirect()->route('santris.import')
                ->with('import_failure', [
                    'failed' => count($preview['rows']),
                    'message' => 'Data valid tidak tersimpan karena ada perubahan data atau kendala database. Unggah ulang file setelah memeriksa data.',
                ]);
        }

        $emailFailures = 0;

        foreach ($credentials as $index => $credential) {
            if (! isset($credential['user_id'])) {
                continue;
            }

            try {
                User::findOrFail($credential['user_id'])->sendEmailVerificationNotification();
            } catch (Throwable $exception) {
                report($exception);
                $emailFailures++;
            }

            unset($credentials[$index]['user_id']);
        }

        $previousCredentialsToken = $request->session()->get('santri_credentials_token');

        if (is_string($previousCredentialsToken) && Str::isUuid($previousCredentialsToken)) {
            Storage::disk('local')->delete($this->credentialsStoragePath((int) $request->user()->id, $previousCredentialsToken));
        }

        if (is_string($credentialToken)) {
            $request->session()->put('santri_credentials_token', $credentialToken);
        }

        $request->session()->forget('santri_import_token');
        $request->session()->forget('santri_import_corrections');
        Storage::disk('local')->delete($path);

        return redirect()->route('santris.index')
            ->with('import_result', [
                'valid' => $preview['valid'],
                'invalid' => $preview['invalid'],
                'duplicate' => $preview['duplicate'],
                'created' => count($credentials),
                'failed' => $preview['invalid'] + $preview['duplicate'],
                'email_failures' => $emailFailures,
                'credentials_available' => $credentials !== [],
            ]);
    }

    public function create(): View
    {
        return view('santris.create');
    }

    /**
     * @param  list<array{row: int, sheet: string, nomor_induk: string, nama: string, jenis_kelamin: string, program_studi: string, kelompok: string, periode: string, status: string, email: string}>  $rows
     * @param  array<int, array{jenis_kelamin: string}>  $corrections
     */
    private function renderImportPreview(Request $request, array $rows, array $corrections = []): View
    {
        $rows = $this->applyImportCorrections($rows, $corrections);

        return view('santris.import-preview', [
            'preview' => $this->analyzeImportRows($rows),
            'importToken' => $request->session()->get('santri_import_token'),
        ]);
    }

    /**
     * @param  array<mixed>  $corrections
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, array{jenis_kelamin: string}>
     */
    private function normalizeImportCorrections(array $corrections, array $rows): array
    {
        $normalized = [];

        foreach ($corrections as $index => $fields) {
            $indexString = (string) $index;

            if (! ctype_digit($indexString) || ! array_key_exists((int) $index, $rows)) {
                throw ValidationException::withMessages([
                    'corrections' => 'Pilihan data pratinjau tidak cocok dengan file yang sedang diproses. Muat ulang pratinjau.',
                ]);
            }

            $gender = trim((string) ($fields['jenis_kelamin'] ?? ''));

            if ($gender !== '' && ! in_array($gender, ['Laki-laki', 'Perempuan'], true)) {
                throw ValidationException::withMessages([
                    'corrections' => 'Pilih jenis kelamin yang tersedia untuk setiap baris.',
                ]);
            }

            $normalized[(int) $index] = ['jenis_kelamin' => $gender];
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, array{jenis_kelamin: string}>  $corrections
     * @return list<array<string, mixed>>
     */
    private function applyImportCorrections(array $rows, array $corrections): array
    {
        foreach ($corrections as $index => $fields) {
            if (array_key_exists($index, $rows)) {
                $rows[$index]['jenis_kelamin'] = $fields['jenis_kelamin'];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{row: int, sheet: string, nomor_induk: string, nama: string, jenis_kelamin: string, program_studi: string, kelompok: string, periode: string, status: string, email: string}>  $rows
     * @return array{rows: list<array{row: int, sheet: string, category: string, reasons: list<string>, data: array<string, mixed>}>, valid: int, invalid: int, duplicate: int}
     */
    private function analyzeImportRows(array $rows): array
    {
        $activePeriods = PeriodeSik::query()->where('status', 'Aktif')->get();
        $periodsByName = $activePeriods->keyBy(fn (PeriodeSik $period): string => $this->normalizePeriodLookup($period->nama_periode));
        $groups = Kelompok::query()->whereIn('periode_sik_id', $activePeriods->pluck('id'))->get();
        $groupsByName = $groups->keyBy(fn (Kelompok $group): string => $group->periode_sik_id.'|'.$this->normalizeLookupValue($group->nama_kelompok));
        $numbers = array_values(array_unique(array_filter(
            array_map(fn (array $row): string => trim($row['nomor_induk']), $rows),
            fn (string $number): bool => $number !== ''
        )));
        $emails = array_values(array_unique(array_filter(array_map(
            fn (array $row): string => trim($row['email']) !== '' ? mb_strtolower(trim($row['email'])) : (trim($row['nomor_induk']) !== '' ? mb_strtolower(trim($row['nomor_induk'])).'@sik.test' : ''),
            $rows
        ))));
        $existingNumbers = Santri::query()->whereIn('nomor_induk', $numbers)->pluck('nomor_induk')->mapWithKeys(fn (string $number): array => [$number => true]);
        $existingEmails = User::query()->whereIn('email', $emails)->pluck('email')->mapWithKeys(fn (string $email): array => [mb_strtolower($email) => true]);
        $seenNumbers = [];
        $seenEmails = [];
        $analyzedRows = [];
        $validCount = 0;
        $invalidCount = 0;
        $duplicateCount = 0;

        foreach ($rows as $row) {
            $number = trim($row['nomor_induk']);
            $email = mb_strtolower(trim($row['email']));
            $emailForAccount = $email !== '' ? $email : ($number !== '' ? mb_strtolower($number).'@sik.test' : '');
            $name = trim($row['nama']);
            $gender = trim($row['jenis_kelamin']);
            $programStudy = trim($row['program_studi']);
            $groupName = trim((string) preg_replace('/\s+/', ' ', $row['kelompok']));
            $statusFromFile = trim($row['status']);
            $status = $statusFromFile !== '' ? $statusFromFile : 'Aktif';
            $periodName = $this->normalizePeriodLookup($row['periode']);
            $period = $periodName !== '' ? $periodsByName->get($periodName) : null;
            $reasons = [];
            $isDuplicate = ($number !== '' && ($existingNumbers->has($number) || isset($seenNumbers[$number])))
                || ($emailForAccount !== '' && ($existingEmails->has($emailForAccount) || isset($seenEmails[$emailForAccount])));

            if ($number === '') {
                $reasons[] = 'Nomor Induk/NIM wajib diisi.';
            } elseif (mb_strlen($number) > 255) {
                $reasons[] = 'Nomor Induk/NIM maksimal 255 karakter.';
            }

            if ($name === '') {
                $reasons[] = 'Nama santri wajib diisi.';
            } elseif (mb_strlen($name) > 255) {
                $reasons[] = 'Nama santri maksimal 255 karakter.';
            }

            if (! in_array($gender, ['Laki-laki', 'Perempuan'], true)) {
                $reasons[] = 'Jenis kelamin wajib dilengkapi Admin karena kolom ini wajib di database.';
            }

            if (mb_strlen($programStudy) > 255) {
                $reasons[] = 'Program Studi maksimal 255 karakter.';
            }

            if ($groupName === '') {
                $reasons[] = 'Kelompok wajib diisi.';
            } elseif (mb_strlen($groupName) > 100) {
                $reasons[] = 'Nama kelompok maksimal 100 karakter.';
            }

            if ($period === null) {
                $reasons[] = 'Periode tidak ditemukan atau tidak aktif. Isi periode pada baris data atau judul sheet dengan semester dan tahun yang sesuai.';
            }

            if (! in_array($status, ['Aktif', 'Tidak Aktif'], true)) {
                $reasons[] = 'Status harus Aktif atau Tidak Aktif.';
            }

            if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255)) {
                $reasons[] = 'Alamat email tidak valid atau melebihi 255 karakter.';
            } elseif ($email === '' && config('sik.auth.require_email_verification')) {
                $reasons[] = 'Email wajib diisi karena verifikasi email aktif.';
            }

            $group = $period !== null && $groupName !== ''
                ? $groupsByName->get($period->id.'|'.$this->normalizeLookupValue($groupName))
                : null;

            if ($group !== null && $group->status !== 'Aktif') {
                $reasons[] = 'Kelompok yang dipilih tidak aktif.';
            } elseif ($group === null && $period !== null && $groupName !== '') {
                $reasons[] = "Kelompok {$groupName} belum terdaftar pada periode {$period->nama_periode}. Buat dan tetapkan instruktur untuk kelompok ini sebelum import.";
            }

            if ($isDuplicate) {
                $reasons[] = 'Nomor Induk/NIM atau email sudah terdaftar pada data atau baris lain.';
            }

            $category = $isDuplicate ? 'duplicate' : (count($reasons) > 0 ? 'invalid' : 'valid');

            if ($category === 'valid') {
                $validCount++;
            } elseif ($category === 'duplicate') {
                $duplicateCount++;
            } else {
                $invalidCount++;
            }

            if ($number !== '') {
                $seenNumbers[$number] = true;
            }

            if ($emailForAccount !== '') {
                $seenEmails[$emailForAccount] = true;
            }

            $analyzedRows[] = [
                'row' => $row['row'],
                'sheet' => $row['sheet'],
                'category' => $category,
                'reasons' => $reasons,
                'data' => [
                    'nomor_induk' => $number,
                    'nama' => $name,
                    'jenis_kelamin' => $gender,
                    'program_studi' => $programStudy,
                    'kelompok' => $groupName,
                    'periode_label' => $period?->nama_periode ?? trim($row['periode']),
                    'periode_sik_id' => $period?->id,
                    'status' => $status,
                    'status_defaulted' => $statusFromFile === '',
                    'email' => $email,
                    'email_akun' => $emailForAccount,
                    'existing_group_id' => $group?->id,
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

    private function normalizePeriodLookup(string $period): string
    {
        if (preg_match('/\b(ganjil|genap)\b.{0,80}?\b((?:19|20)\d{2})\b/ui', $period, $matches) === 1) {
            return $matches[1].'_'.$matches[2];
        }

        if (preg_match('/\b((?:19|20)\d{2})\b.{0,80}?\b(ganjil|genap)\b/ui', $period, $matches) === 1) {
            return $matches[2].'_'.$matches[1];
        }

        return $this->normalizeLookupValue($period);
    }

    private function normalizeLookupValue(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
    }

    private function importStoragePath(int $userId, string $token): string
    {
        return 'imports/santri/'.$userId.'/'.$token.'.xlsx';
    }

    private function credentialsStoragePath(int $userId, string $token): string
    {
        return 'imports/santri-credentials/'.$userId.'/'.$token.'.csv';
    }

    /** @param list<array{nomor_induk: string, email: string, password: string, user_id?: int}> $credentials */
    private function credentialsCsv(array $credentials): string
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Daftar kredensial tidak dapat disiapkan.');
        }

        fputcsv($stream, ['Nomor Induk', 'Username/email', 'Password sementara'], ',', '"', '');

        foreach ($credentials as $credential) {
            $values = array_map($this->safeCsvValue(...), [
                $credential['nomor_induk'],
                $credential['email'],
                $credential['password'],
            ]);
            fputcsv($stream, $values, ',', '"', '');
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($contents)) {
            throw new RuntimeException('Daftar kredensial tidak dapat dibaca.');
        }

        return $contents;
    }

    private function safeCsvValue(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'unique:santris,nomor_induk'],
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                Rule::requiredIf(config('sik.auth.require_email_verification')),
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'jenis_kelamin' => ['nullable', Rule::in(['Laki-laki', 'Perempuan'])],
            'program_studi' => ['nullable', 'string', 'max:255'],
            'fingerprint_id' => ['nullable', 'string', 'max:255', 'unique:fingerprints,fingerprint_id'],
            'status' => ['required', Rule::in(['Aktif', 'Tidak Aktif'])],
        ]);

        $temporaryPassword = Str::random(12);
        $fingerprintId = $validated['fingerprint_id'] ?? null;
        $accountEmail = $validated['email'] ?? strtolower($validated['nomor_induk']).'@sik.test';
        unset($validated['email']);
        unset($validated['fingerprint_id']);

        $user = DB::transaction(function () use ($validated, $temporaryPassword, $fingerprintId, $accountEmail): User {
            $user = new User;

            $user->name = $validated['nama'];
            $user->email = $accountEmail;
            $user->email_verified_at = config('sik.auth.require_email_verification') ? null : now();
            $user->password = $temporaryPassword;
            $user->role = UserRole::Santri;
            $user->must_change_password = true;

            $user->save();

            $validated['user_id'] = $user->id;

            $santri = Santri::create($validated);

            if (filled($fingerprintId)) {
                $santri->fingerprint()->create([
                    'fingerprint_id' => $fingerprintId,
                    'tanggal_pendaftaran' => now()->toDateString(),
                    'status' => 'Aktif',
                ]);
            }

            return $user;
        });

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('santris.index')
            ->with('success', 'Data santri dan akun login berhasil dibuat.')
            ->with('account_created', [
                'nomor_induk' => $validated['nomor_induk'],
                'email' => $accountEmail,
                'password' => $temporaryPassword,
            ]);
    }

    public function edit(Santri $santri): View
    {
        $santri->load('fingerprint');

        return view('santris.edit', compact('santri'));
    }

    public function show(Santri $santri): View
    {
        $santri->load(['user', 'fingerprint']);

        return view('santris.show', compact('santri'));
    }

    public function update(Request $request, Santri $santri): RedirectResponse
    {
        $santri->load(['user', 'fingerprint']);

        $validated = $request->validate([
            'nomor_induk' => [
                'required',
                Rule::unique('santris')->ignore($santri->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                Rule::requiredIf(config('sik.auth.require_email_verification')),
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($santri->user_id),
            ],
            'jenis_kelamin' => ['nullable', Rule::in(['Laki-laki', 'Perempuan'])],
            'program_studi' => ['nullable', 'string', 'max:255'],
            'fingerprint_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('fingerprints', 'fingerprint_id')->ignore($santri->fingerprint?->id),
            ],
            'status' => ['required', Rule::in(['Aktif', 'Tidak Aktif'])],
        ]);

        $fingerprintId = $validated['fingerprint_id'] ?? null;
        $accountEmail = $validated['email'] ?? $santri->user?->email;
        unset($validated['fingerprint_id']);
        unset($validated['email']);

        DB::transaction(function () use ($santri, $validated, $fingerprintId, $accountEmail): void {
            $santri->update($validated);

            if ($santri->user !== null) {
                $santri->user->name = $validated['nama'];

                if ($accountEmail !== null && $santri->user->email !== $accountEmail) {
                    $santri->user->email = $accountEmail;
                    $santri->user->email_verified_at = config('sik.auth.require_email_verification') ? null : now();
                }

                $santri->user->save();
            }

            if (filled($fingerprintId)) {
                $fingerprint = $santri->fingerprint()->firstOrNew();
                $fingerprint->fingerprint_id = $fingerprintId;
                $fingerprint->tanggal_pendaftaran ??= now()->toDateString();
                $fingerprint->status ??= 'Aktif';
                $fingerprint->save();
            }
        });

        if ($santri->user !== null && ! $santri->user->hasVerifiedEmail()) {
            $santri->user->sendEmailVerificationNotification();
        }

        return redirect()->route('santris.index')
            ->with('success', 'Data santri berhasil diperbarui.');
    }

    public function destroy(Santri $santri): RedirectResponse
    {
        $hasHistory = $santri->absensi()->exists()
            || $santri->nilai()->exists()
            || $santri->kelulusan()->exists()
            || $santri->sertifikat()->exists()
            || $santri->rekapKehadiran()->exists()
            || AnggotaPeriodeSik::where('santri_id', $santri->id)->exists();

        if ($hasHistory) {
            return back()->with(
                'error',
                'Data santri tidak dapat dihapus karena masih memiliki riwayat akademik atau kehadiran.'
            );
        }

        try {
            DB::transaction(function () use ($santri): void {
                $santri->user?->delete();
                $santri->delete();
            });
        } catch (QueryException) {
            return back()->with(
                'error',
                'Data santri tidak dapat dihapus karena masih memiliki data terkait.'
            );
        }

        return redirect()->route('santris.index')
            ->with('success', 'Data santri berhasil dihapus.');
    }
}
