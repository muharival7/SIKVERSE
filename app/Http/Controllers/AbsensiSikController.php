<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\Pertemuan;
use App\Models\RekapKehadiranSik;
use App\Models\RiwayatAbsensi;
use App\Services\AttendanceRecapService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AbsensiSikController extends Controller
{
    public function __construct(private readonly AttendanceRecapService $attendanceRecapService)
    {
    }

    public function index(Request $request): View
    {
        $isAdmin = $request->user()->role === UserRole::Admin;
        $instruktur = $isAdmin ? null : $request->user()->infrastrukturSik;
        abort_if(! $isAdmin && ! ($instruktur instanceof InfrastrukturSik), 403);
        $visibleGroupIds = collect();

        $absensiQuery = AbsensiSik::with([
            'pertemuan.kegiatan',
            'anggotaPeriode.periode',
            'anggotaPeriode.kelompok',
            'anggotaPeriode.santri',
        ])->latest();

        $pertemuanQuery = Pertemuan::with(['kegiatan.periode', 'kelompokHost'])
            ->where('status', '!=', 'Dibatalkan')
            ->orderByDesc('tanggal');

        $anggotaQuery = AnggotaPeriodeSik::with(['santri', 'kelompok', 'periode'])
            ->where('status', 'Aktif')
            ->orderBy('id');

        if ($instruktur instanceof InfrastrukturSik) {
            $groupIds = Kelompok::where('instruktur_id', $instruktur->id)->pluck('id');
            $replacementGroupIds = Pertemuan::where('instruktur_pengganti_id', $instruktur->id)
                ->whereNotNull('kelompok_host_id')
                ->pluck('kelompok_host_id');
            $visibleGroupIds = $groupIds->merge($replacementGroupIds)->unique()->values();

            $absensiQuery->where(function ($query) use ($instruktur): void {
                $query->whereHas(
                    'anggotaPeriode.kelompok',
                    fn ($groupQuery) => $groupQuery->where('instruktur_id', $instruktur->id)
                )->orWhereHas('pertemuan', function ($meetingQuery) use ($instruktur): void {
                    $meetingQuery->where('instruktur_pengganti_id', $instruktur->id)
                        ->orWhereHas(
                            'kelompokHost',
                            fn ($groupQuery) => $groupQuery->where('instruktur_id', $instruktur->id)
                        );
                });
            });

            $pertemuanQuery->where(function ($query) use ($instruktur): void {
                $query->where('instruktur_pengganti_id', $instruktur->id)
                    ->orWhereHas(
                        'kelompokHost',
                        fn ($groupQuery) => $groupQuery->where('instruktur_id', $instruktur->id)
                    )
                    ->orWhereHas(
                        'kegiatan.kelompok',
                        fn ($groupQuery) => $groupQuery->where('instruktur_id', $instruktur->id)
                    );
            });

            $anggotaQuery->whereIn('kelompok_id', $visibleGroupIds);
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $absensiQuery->whereHas('anggotaPeriode.santri', function ($query) use ($search): void {
                $query->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('nomor_induk', 'like', '%'.$search.'%');
            });
        }

        $selectedGroup = $request->integer('kelompok_id') ?: null;
        if ($selectedGroup !== null) {
            if ($instruktur instanceof InfrastrukturSik) {
                abort_unless($visibleGroupIds->contains($selectedGroup), 403);
            }
            $absensiQuery->whereHas(
                'anggotaPeriode.kelompok',
                fn ($query) => $query->whereKey($selectedGroup)
            );
        }

        $groupsQuery = Kelompok::query()->orderBy('nama_kelompok');
        if ($instruktur instanceof InfrastrukturSik) {
            $groupsQuery->whereIn('id', $visibleGroupIds);
        }

        return view('absensi-sik.index', [
            'absensi' => $absensiQuery->paginate(15)->withQueryString(),
            'pertemuan' => $pertemuanQuery->get(),
            'anggota' => $anggotaQuery->get(),
            'kelompoks' => $groupsQuery->get(),
            'search' => $search,
            'selectedGroup' => $selectedGroup,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function riwayatSantri(Request $request): View
    {
        $santri = $request->user()->santri;
        abort_unless($santri !== null, 403, 'Data santri tidak ditemukan untuk akun ini.');

        $absensi = AbsensiSik::with([
            'pertemuan.kegiatan',
            'anggotaPeriode.periode',
            'anggotaPeriode.kelompok',
            'buktiKetidakhadiran.pemeriksa',
        ])
            ->whereHas('anggotaPeriode', fn ($query) => $query->where('santri_id', $santri->id))
            ->latest()
            ->paginate(10);

        $rekap = RekapKehadiranSik::with(['anggotaPeriode.periode', 'kegiatan'])
            ->whereHas('anggotaPeriode', fn ($query) => $query->where('santri_id', $santri->id))
            ->latest()
            ->get();

        $this->attendanceRecapService->prepareDisplay($rekap);

        return view('santris.absensi-saya', [
            'santri' => $santri,
            'absensi' => $absensi,
            'totalAbsensi' => $absensi->total(),
            'rekap' => $rekap,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'anggota_periode_sik_id' => ['required', 'exists:anggota_periode_sik,id'],
            'pertemuan_id' => ['required', 'exists:pertemuan,id'],
            'waktu_masuk' => ['nullable', 'date_format:H:i'],
            'waktu_pulang' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::in(['Hadir', 'Izin', 'Sakit', 'Alpha', 'Tidak Lengkap'])],
            'keterangan' => ['nullable', 'string'],
        ]);

        $anggota = AnggotaPeriodeSik::with('kelompok')->findOrFail($validated['anggota_periode_sik_id']);
        $pertemuan = Pertemuan::with(['kegiatan.kelompok', 'kelompokHost'])->findOrFail($validated['pertemuan_id']);
        $instruktur = $this->currentInstructor($request);
        $this->authorizeAttendance($anggota, $pertemuan, $instruktur);

        try {
            DB::transaction(function () use ($validated, $anggota, $pertemuan, $instruktur): void {
                $absensi = AbsensiSik::create([
                    ...$validated,
                    'metode_absensi' => 'Manual',
                    'dicatat_oleh' => $instruktur?->id,
                ]);

                $this->syncRekap($anggota, $pertemuan);
            });
        } catch (QueryException) {
            return back()->withInput()->withErrors([
                'pertemuan_id' => 'Absensi untuk santri dan pertemuan ini sudah tercatat.',
            ]);
        }

        return redirect()->route('absensi-sik.index')
            ->with('success', 'Absensi manual berhasil disimpan.');
    }

    public function update(Request $request, AbsensiSik $absensiSik): RedirectResponse
    {
        $validated = $request->validate([
            'waktu_masuk' => ['nullable', 'date_format:H:i'],
            'waktu_pulang' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::in(['Hadir', 'Izin', 'Sakit', 'Alpha', 'Tidak Lengkap'])],
            'keterangan' => ['nullable', 'string'],
            'alasan_perubahan' => ['nullable', 'string', 'max:1000'],
        ]);

        $absensiSik->load(['anggotaPeriode.kelompok', 'pertemuan.kegiatan.kelompok', 'pertemuan.kelompokHost']);
        $instruktur = $this->currentInstructor($request);
        $this->authorizeAttendance($absensiSik->anggotaPeriode, $absensiSik->pertemuan, $instruktur);

        $buktiMenunggu = $absensiSik->buktiKetidakhadiran()
            ->whereIn('status_verifikasi', ['Menunggu', 'Diterima'])
            ->latest()
            ->first();

        if ($buktiMenunggu !== null && $validated['status'] !== $buktiMenunggu->jenis_bukti) {
            throw ValidationException::withMessages([
                'status' => 'Status absensi harus sesuai dengan bukti yang sedang menunggu atau sudah diterima.',
            ]);
        }

        $statusSebelumnya = $absensiSik->status;
        $alasan = $validated['alasan_perubahan'] ?? null;
        unset($validated['alasan_perubahan']);

        DB::transaction(function () use (
            $absensiSik,
            $validated,
            $statusSebelumnya,
            $alasan,
            $instruktur
        ): void {
            if ($statusSebelumnya !== $validated['status'] && $instruktur instanceof InfrastrukturSik) {
                RiwayatAbsensi::create([
                    'absensi_sik_id' => $absensiSik->id,
                    'instruktur_id' => $instruktur->id,
                    'status_sebelumnya' => $statusSebelumnya,
                    'status_sesudahnya' => $validated['status'],
                    'alasan_perubahan' => $alasan,
                ]);
            }

            $absensiSik->update($validated);
            $this->syncRekap($absensiSik->anggotaPeriode, $absensiSik->pertemuan);
        });

        return redirect()->route('absensi-sik.index')
            ->with('success', 'Absensi berhasil diperbarui.');
    }

    private function authorizeAttendance(
        AnggotaPeriodeSik $anggota,
        Pertemuan $pertemuan,
        ?InfrastrukturSik $instruktur
    ): void {
        if ($pertemuan->status === 'Dibatalkan') {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Pertemuan yang dibatalkan tidak menerima absensi.',
            ]);
        }

        $kegiatan = $pertemuan->kegiatan;

        if (! $kegiatan instanceof Kegiatan) {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Kegiatan pertemuan tidak ditemukan.',
            ]);
        }

        $hostGroupId = $pertemuan->kelompok_host_id;
        $kegiatanGroupId = $kegiatan->kelompok_id;

        if ($hostGroupId !== null && $kegiatanGroupId !== null
            && (int) $hostGroupId !== (int) $kegiatanGroupId) {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Kelompok host pertemuan dan kelompok kegiatan tidak konsisten.',
            ]);
        }

        $targetGroupId = $hostGroupId ?? $kegiatanGroupId;

        if ($targetGroupId === null) {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Target kelompok pertemuan belum ditentukan. Isi kelompok host pertemuan atau kelompok kegiatan terlebih dahulu.',
            ]);
        }

        $targetGroup = $pertemuan->kelompokHost ?? $kegiatan->kelompok;

        if (! $targetGroup instanceof Kelompok) {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Kelompok target pertemuan tidak ditemukan.',
            ]);
        }

        $periodeIds = array_map('intval', array_filter([
            $anggota->periode_sik_id,
            $kegiatan->periode_sik_id,
            $anggota->kelompok?->periode_sik_id,
            $kegiatan->kelompok?->periode_sik_id,
            $pertemuan->kelompokHost?->periode_sik_id,
        ], static fn ($value): bool => $value !== null));

        if (count(array_unique($periodeIds)) > 1) {
            throw ValidationException::withMessages([
                'anggota_periode_sik_id' => 'Periode santri, kegiatan, dan kelompok harus sama.',
            ]);
        }

        if ($anggota->kelompok_id === null || (int) $anggota->kelompok_id !== (int) $targetGroupId) {
            throw ValidationException::withMessages([
                'anggota_periode_sik_id' => 'Santri tidak terdaftar pada kelompok target pertemuan ini.',
            ]);
        }

        if (! $anggota->kelompok instanceof Kelompok) {
            throw ValidationException::withMessages([
                'anggota_periode_sik_id' => 'Kelompok santri tidak ditemukan.',
            ]);
        }

        if ($instruktur instanceof InfrastrukturSik) {
            $isAssigned = (int) $anggota->kelompok->instruktur_id === (int) $instruktur->id
                || (int) $pertemuan->kelompokHost?->instruktur_id === (int) $instruktur->id
                || (int) $pertemuan->instruktur_pengganti_id === (int) $instruktur->id;

            abort_unless($isAssigned, 403);
        }
    }

    private function currentInstructor(Request $request): ?InfrastrukturSik
    {
        if ($request->user()->role !== UserRole::InfrastrukturSik) {
            return null;
        }

        $instruktur = $request->user()->infrastrukturSik;
        abort_unless($instruktur instanceof InfrastrukturSik, 403);

        return $instruktur;
    }

    private function syncRekap(AnggotaPeriodeSik $anggota, Pertemuan $pertemuan): void
    {
        $this->attendanceRecapService->sync(
            (int) $anggota->id,
            (int) $pertemuan->kegiatan_id
        );
    }
}
