<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\RekapKehadiranSik;
use App\Models\RiwayatAbsensi;
use App\Models\User;
use App\Services\AttendanceAuthorizationService;
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
    public function __construct(
        private readonly AttendanceRecapService $attendanceRecapService,
        private readonly AttendanceAuthorizationService $attendanceAuthorizationService
    ) {}

    public function index(Request $request): View
    {
        $isAdmin = $request->user()->role === UserRole::Admin;
        $instruktur = $isAdmin ? null : $request->user()->infrastrukturSik;
        abort_if(! $isAdmin && ! ($instruktur instanceof InfrastrukturSik), 403);
        $visibleGroupIds = collect();

        $absensiQuery = AbsensiSik::with([
            'pertemuan.kegiatan.periode',
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
            'minimumPercentage' => $this->attendanceRecapService->minimumPercentage(),
            'noMeetingsStatus' => AttendanceRecapService::STATUS_NO_MEETINGS,
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
        $this->attendanceAuthorizationService->authorize($anggota, $pertemuan, $instruktur);

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
        $this->attendanceAuthorizationService->authorize($absensiSik->anggotaPeriode, $absensiSik->pertemuan, $instruktur);

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

    /**
     * Ubah tanggal pertemuan tanpa menyentuh catatan absensi. Absensi tetap
     * terhubung lewat pertemuan_id, sehingga status, identitas santri, dan
     * kegiatan/kelompok tidak berubah.
     */
    public function ubahTanggalPertemuan(Request $request, Pertemuan $pertemuan): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'tanggal_baru' => ['required', 'date', 'date_format:Y-m-d'],
            'konfirmasi' => ['nullable', 'accepted'],
        ]);

        $pertemuan->load(['kegiatan.periode', 'kegiatan.kelompok', 'kelompokHost']);
        $instruktur = $this->currentInstructor($request);
        $this->attendanceAuthorizationService->authorizePertemuanDateChange($pertemuan, $instruktur);

        if ($pertemuan->absensi()->exists() && ! $request->boolean('konfirmasi')) {
            throw ValidationException::withMessages([
                'konfirmasi' => 'Konfirmasi diperlukan untuk mengubah tanggal pertemuan yang sudah memiliki catatan absensi.',
            ]);
        }

        $kegiatan = $pertemuan->kegiatan;
        $periode = $kegiatan?->periode;

        if (! $kegiatan instanceof Kegiatan || ! $periode instanceof PeriodeSik) {
            throw ValidationException::withMessages([
                'tanggal_baru' => 'Kegiatan pertemuan tidak memiliki periode SIK yang terdefinisi.',
            ]);
        }

        $tanggalMulai = $periode->tanggal_mulai->toDateString();
        $tanggalSelesai = $periode->tanggal_selesai->toDateString();

        if ($validated['tanggal_baru'] < $tanggalMulai || $validated['tanggal_baru'] > $tanggalSelesai) {
            throw ValidationException::withMessages([
                'tanggal_baru' => 'Tanggal pertemuan harus berada dalam rentang periode "'.$periode->nama_periode.'" ('.$tanggalMulai.' hingga '.$tanggalSelesai.').',
            ]);
        }

        $tanggalLama = $pertemuan->tanggal->toDateString();

        if ($tanggalLama === $validated['tanggal_baru']) {
            return redirect()->route('absensi-sik.index')
                ->with('success', 'Tanggal pertemuan tidak berubah.');
        }

        DB::transaction(function () use ($pertemuan, $validated): void {
            // Hanya pertemuan yang diubah. Catatan absensi tetap terhubung
            // melalui pertemuan_id tanpa perubahan status atau identitas.
            $pertemuan->update(['tanggal' => $validated['tanggal_baru']]);
        });

        return redirect()->route('absensi-sik.index')
            ->with('success', 'Tanggal pertemuan berhasil diubah dari '.$tanggalLama.' menjadi '.$validated['tanggal_baru'].'.');
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
