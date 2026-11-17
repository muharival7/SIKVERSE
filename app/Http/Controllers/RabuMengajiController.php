<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\Kegiatan;
use App\Models\RekapKehadiranSik;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RabuMengajiController extends Controller
{
    /** Daftar kehadiran Rabu Mengaji memakai kegiatan, pertemuan, dan absensi_sik yang sudah ada. */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tanggal' => ['nullable', 'date'],
            'kegiatan_id' => ['nullable', 'integer'],
        ]);

        $kegiatan = $this->mengajiActivities()->orderBy('nama_kegiatan')->get();
        $query = $this->scopedAbsensiQuery($user);
        $this->applyFilters($query, $filters);

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total_pertemuan')
            ->selectRaw("SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as total_hadir")
            ->selectRaw("SUM(CASE WHEN status = 'Izin' THEN 1 ELSE 0 END) as total_izin")
            ->selectRaw("SUM(CASE WHEN status = 'Sakit' THEN 1 ELSE 0 END) as total_sakit")
            ->selectRaw("SUM(CASE WHEN status = 'Alpha' THEN 1 ELSE 0 END) as total_alpha")
            ->first();

        $absensi = (clone $query)
            ->with([
                'anggotaPeriode.santri',
                'anggotaPeriode.kelompok',
                'pertemuan.kegiatan',
            ])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('rabu-mengaji.index', compact('absensi', 'kegiatan', 'filters', 'summary'));
    }

    /** Rincian kehadiran satu catatan Rabu Mengaji beserta riwayat dan rekapnya. */
    public function show(Request $request, AbsensiSik $absensiSik): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $catatan = $this->scopedAbsensiQuery($user)
            ->with([
                'anggotaPeriode.santri',
                'anggotaPeriode.kelompok',
                'anggotaPeriode.periode',
                'pertemuan.kegiatan',
            ])
            ->findOrFail($absensiSik->id);

        $kegiatan = $catatan->pertemuan?->kegiatan;
        abort_unless($kegiatan instanceof Kegiatan && $this->isMengajiActivity($kegiatan), 404);

        $riwayat = AbsensiSik::query()
            ->with('pertemuan')
            ->where('anggota_periode_sik_id', $catatan->anggota_periode_sik_id)
            ->whereHas('pertemuan', fn (Builder $query): mixed => $query->where('kegiatan_id', $kegiatan->id))
            ->get()
            ->sortBy(fn (AbsensiSik $item): string => sprintf(
                '%s-%04d',
                $item->pertemuan?->tanggal?->format('Y-m-d') ?? '0000-00-00',
                (int) ($item->pertemuan?->pertemuan_ke ?? 0)
            ))
            ->values();

        $rekap = RekapKehadiranSik::query()
            ->where('anggota_periode_sik_id', $catatan->anggota_periode_sik_id)
            ->where('kegiatan_id', $kegiatan->id)
            ->first();

        $meetingCount = max(1, (int) config('sik.attendance.meeting_count', 16));
        $minimumPresent = max(1, (int) config('sik.attendance.minimum_present', 14));

        return view('rabu-mengaji.show', compact(
            'catatan',
            'kegiatan',
            'riwayat',
            'rekap',
            'meetingCount',
            'minimumPresent'
        ));
    }

    /** @return Builder<Kegiatan> */
    private function mengajiActivities(): Builder
    {
        return $this->scopeMengaji(Kegiatan::query());
    }

    /** @param Builder<Kegiatan> $query */
    private function scopeMengaji(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(nama_kegiatan) LIKE ?', ['%mengaji%']);
    }

    private function isMengajiActivity(Kegiatan $kegiatan): bool
    {
        return str_contains(mb_strtolower((string) $kegiatan->nama_kegiatan), 'mengaji');
    }

    /** @return Builder<AbsensiSik> */
    private function scopedAbsensiQuery(User $user): Builder
    {
        $query = AbsensiSik::query()
            ->whereHas('pertemuan.kegiatan', fn (Builder $query): mixed => $this->scopeMengaji($query));

        if ($user->role === UserRole::Admin) {
            return $query;
        }

        $instructor = $user->infrastrukturSik;
        abort_unless($instructor !== null, 403, 'Data instruktur tidak ditemukan.');

        return $query->whereHas(
            'anggotaPeriode.kelompok',
            fn (Builder $query): mixed => $query->where('instruktur_id', $instructor->id)
        );
    }

    /**
     * @param  array<string, string|null>  $filters
     * @param  Builder<AbsensiSik>  $query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (filled($filters['search'] ?? null)) {
            $search = '%'.mb_strtolower(trim((string) $filters['search'])).'%';
            $query->whereHas(
                'anggotaPeriode.santri',
                fn (Builder $query): mixed => $query->whereRaw('LOWER(nama) LIKE ?', [$search])
            );
        }

        if (filled($filters['tanggal'] ?? null)) {
            $query->whereHas(
                'pertemuan',
                fn (Builder $query): mixed => $query->whereDate('tanggal', (string) $filters['tanggal'])
            );
        }

        if (filled($filters['kegiatan_id'] ?? null)) {
            $query->whereHas(
                'pertemuan',
                fn (Builder $query): mixed => $query->where('kegiatan_id', (int) $filters['kegiatan_id'])
            );
        }
    }
}
