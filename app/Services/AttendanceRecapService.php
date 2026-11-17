<?php

namespace App\Services;

use App\Models\AbsensiSik;
use App\Models\Pertemuan;
use App\Models\RekapKehadiranSik;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu sumber perhitungan rekap kehadiran SIK.
 *
 * Semua angka dihitung dari pertemuan berstatus Terlaksana dan absensi_sik.
 * Rekap_kehadiran_sik hanya berupa cache yang disinkronkan oleh service ini.
 */
class AttendanceRecapService
{
    public const STATUS_NO_MEETINGS = 'Belum Ada Pertemuan Terlaksana';

    public const STATUS_MET = 'Memenuhi';

    public const STATUS_UNMET = 'Belum Memenuhi';

    private const STATUSES_COUNTED = ['Hadir', 'Izin', 'Sakit', 'Alpha'];

    /** @var array<int, array<string, mixed>> */
    private array $anggotaMetricCache = [];

    /**
     * Hitung metrik kehadiran satu anggota untuk satu kegiatan.
     *
     * @return array{
     *     anggota_periode_sik_id: int,
     *     kegiatan_id: int,
     *     total_pertemuan: int,
     *     jumlah_hadir: int,
     *     jumlah_izin: int,
     *     jumlah_sakit: int,
     *     jumlah_alpha: int,
     *     jumlah_tidak_lengkap: int,
     *     persentase: float|null,
     *     status: string,
     *     memenuhi: bool,
     *     tersedia: bool
     * }
     */
    public function calculate(int $anggotaPeriodeSikId, int $kegiatanId): array
    {
        $totalPertemuan = Pertemuan::query()
            ->where('kegiatan_id', $kegiatanId)
            ->where('status', 'Terlaksana')
            ->count();

        $jumlahPerStatus = AbsensiSik::query()
            ->join('pertemuan', 'pertemuan.id', '=', 'absensi_sik.pertemuan_id')
            ->where('absensi_sik.anggota_periode_sik_id', $anggotaPeriodeSikId)
            ->where('pertemuan.kegiatan_id', $kegiatanId)
            ->where('pertemuan.status', 'Terlaksana')
            ->whereIn('absensi_sik.status', self::STATUSES_COUNTED)
            ->selectRaw('absensi_sik.status as absensi_status, COUNT(*) as jumlah')
            ->groupBy('absensi_sik.status')
            ->pluck('jumlah', 'absensi_status');

        $jumlahHadir = (int) ($jumlahPerStatus['Hadir'] ?? 0);
        $jumlahIzin = (int) ($jumlahPerStatus['Izin'] ?? 0);
        $jumlahSakit = (int) ($jumlahPerStatus['Sakit'] ?? 0);
        $jumlahAlpha = (int) ($jumlahPerStatus['Alpha'] ?? 0);
        $tersedia = $totalPertemuan > 0;

        return [
            'anggota_periode_sik_id' => $anggotaPeriodeSikId,
            'kegiatan_id' => $kegiatanId,
            'total_pertemuan' => $totalPertemuan,
            'jumlah_hadir' => $jumlahHadir,
            'jumlah_izin' => $jumlahIzin,
            'jumlah_sakit' => $jumlahSakit,
            'jumlah_alpha' => $jumlahAlpha,
            'jumlah_tidak_lengkap' => 0,
            'persentase' => $tersedia ? round(($jumlahHadir / $totalPertemuan) * 100, 2) : null,
            'status' => $tersedia
                ? ($this->meetsThreshold($jumlahHadir, $totalPertemuan)
                    ? self::STATUS_MET
                    : self::STATUS_UNMET)
                : self::STATUS_NO_MEETINGS,
            'memenuhi' => $this->meetsThreshold($jumlahHadir, $totalPertemuan),
            'tersedia' => $tersedia,
        ];
    }

    /**
     * Agregasi lintas kegiatan untuk satu anggota.
     *
     * Kelulusan saat ini tidak memiliki kegiatan_id, sehingga penilaian
     * dilakukan atas gabungan pertemuan Terlaksana pada kegiatan yang sudah
     * memiliki data rekap atau absensi untuk anggota tersebut.
     *
     * @return array{
     *     anggota_periode_sik_id: int,
     *     total_pertemuan: int,
     *     jumlah_hadir: int,
     *     jumlah_izin: int,
     *     jumlah_sakit: int,
     *     jumlah_alpha: int,
     *     persentase: float|null,
     *     status: string,
     *     memenuhi: bool,
     *     tersedia: bool
     * }
     */
    public function calculateForAnggota(int $anggotaPeriodeSikId): array
    {
        if (isset($this->anggotaMetricCache[$anggotaPeriodeSikId])) {
            return $this->anggotaMetricCache[$anggotaPeriodeSikId];
        }

        $kegiatanIds = RekapKehadiranSik::query()
            ->where('anggota_periode_sik_id', $anggotaPeriodeSikId)
            ->pluck('kegiatan_id')
            ->merge(
                AbsensiSik::query()
                    ->join('pertemuan', 'pertemuan.id', '=', 'absensi_sik.pertemuan_id')
                    ->where('absensi_sik.anggota_periode_sik_id', $anggotaPeriodeSikId)
                    ->pluck('pertemuan.kegiatan_id')
            )
            ->filter()
            ->unique()
            ->values();

        $totals = [
            'total_pertemuan' => 0,
            'jumlah_hadir' => 0,
            'jumlah_izin' => 0,
            'jumlah_sakit' => 0,
            'jumlah_alpha' => 0,
        ];

        foreach ($kegiatanIds as $kegiatanId) {
            $metric = $this->calculate($anggotaPeriodeSikId, (int) $kegiatanId);

            if (! $metric['tersedia']) {
                continue;
            }

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $metric[$key];
            }
        }

        $tersedia = $totals['total_pertemuan'] > 0;
        $memenuhi = $this->meetsThreshold($totals['jumlah_hadir'], $totals['total_pertemuan']);

        return $this->anggotaMetricCache[$anggotaPeriodeSikId] = [
            'anggota_periode_sik_id' => $anggotaPeriodeSikId,
            ...$totals,
            'persentase' => $tersedia
                ? round(($totals['jumlah_hadir'] / $totals['total_pertemuan']) * 100, 2)
                : null,
            'status' => $tersedia
                ? ($memenuhi ? self::STATUS_MET : self::STATUS_UNMET)
                : self::STATUS_NO_MEETINGS,
            'memenuhi' => $memenuhi,
            'tersedia' => $tersedia,
        ];
    }

    /**
     * Perbandingan integer untuk ambang kehadiran, aman dari pembulatan float.
     */
    public function meetsThreshold(int $jumlahHadir, int $totalPertemuanTerlaksana): bool
    {
        if ($totalPertemuanTerlaksana <= 0) {
            return false;
        }

        $minimumPromille = (int) round(
            (float) config('sik.attendance.minimum_percentage', 87.5) * 10
        );

        return $jumlahHadir * 1000 >= $totalPertemuanTerlaksana * $minimumPromille;
    }

    public function minimumPercentage(): float
    {
        return (float) config('sik.attendance.minimum_percentage', 87.5);
    }

    /**
     * Simpan hasil hitung ke cache rekap yang sudah ada (tanpa perubahan schema).
     */
    public function sync(int $anggotaPeriodeSikId, int $kegiatanId): RekapKehadiranSik
    {
        $metric = $this->calculate($anggotaPeriodeSikId, $kegiatanId);

        return RekapKehadiranSik::updateOrCreate(
            [
                'anggota_periode_sik_id' => $anggotaPeriodeSikId,
                'kegiatan_id' => $kegiatanId,
            ],
            [
                'total_pertemuan' => $metric['total_pertemuan'],
                'jumlah_hadir' => $metric['jumlah_hadir'],
                'jumlah_izin' => $metric['jumlah_izin'],
                'jumlah_sakit' => $metric['jumlah_sakit'],
                'jumlah_alpha' => $metric['jumlah_alpha'],
                'jumlah_tidak_lengkap' => $metric['jumlah_tidak_lengkap'],
                // Kolom persentase numeric tidak bisa menyimpan "belum tersedia";
                // status khusus menjadi penentu tampilan pada lapisan presentasi.
                'persentase_kehadiran' => $metric['persentase'] ?? 0,
                'status_kehadiran' => $metric['status'],
            ]
        );
    }

    public function syncForAbsensi(AbsensiSik $absensi): void
    {
        $pertemuan = $absensi->pertemuan;

        if (! $pertemuan instanceof Pertemuan) {
            return;
        }

        $this->sync((int) $absensi->anggota_periode_sik_id, (int) $pertemuan->kegiatan_id);
    }

    /**
     * Sinkron ulang seluruh anggota yang memiliki rekap atau absensi pada
     * kegiatan yang bersangkutan, termasuk setelah perubahan status pertemuan.
     */
    public function syncForPertemuan(Pertemuan $pertemuan, ?int $kegiatanSebelumnya = null): void
    {
        $kegiatanIds = collect([(int) $pertemuan->kegiatan_id, $kegiatanSebelumnya])
            ->filter(fn ($id): bool => $id > 0)
            ->unique()
            ->values();

        foreach ($kegiatanIds as $kegiatanId) {
            $this->syncForKegiatan((int) $kegiatanId);
        }
    }

    public function syncForKegiatan(int $kegiatanId): void
    {
        $anggotaIds = RekapKehadiranSik::query()
            ->where('kegiatan_id', $kegiatanId)
            ->pluck('anggota_periode_sik_id')
            ->merge(
                AbsensiSik::query()
                    ->join('pertemuan', 'pertemuan.id', '=', 'absensi_sik.pertemuan_id')
                    ->where('pertemuan.kegiatan_id', $kegiatanId)
                    ->pluck('absensi_sik.anggota_periode_sik_id')
            )
            ->filter()
            ->unique()
            ->values();

        foreach ($anggotaIds as $anggotaId) {
            $this->sync((int) $anggotaId, $kegiatanId);
        }
    }

    /**
     * Daftar anggota yang memenuhi ambang kehadiran untuk purposes kelulusan.
     *
     * @return Collection<int, int>
     */
    public function eligibleAnggotaIds(): Collection
    {
        $anggotaIds = RekapKehadiranSik::query()
            ->pluck('anggota_periode_sik_id')
            ->merge(AbsensiSik::query()->pluck('anggota_periode_sik_id'))
            ->filter()
            ->unique()
            ->values();

        return $anggotaIds
            ->filter(fn ($anggotaId): bool => $this->calculateForAnggota((int) $anggotaId)['memenuhi'])
            ->values();
    }

    /**
     * Tambahkan atribut tampilan dari data rekap hasil sinkronisasi.
     * Tidak ada perhitungan ulang di sini.
     *
     * @param  iterable<RekapKehadiranSik>  $recaps
     */
    public function prepareDisplay(iterable $recaps): void
    {
        foreach ($recaps as $recap) {
            if (! $recap instanceof RekapKehadiranSik) {
                continue;
            }

            $tersedia = $recap->status_kehadiran !== self::STATUS_NO_MEETINGS;

            $recap->setAttribute('tersedia', $tersedia);
            $recap->setAttribute('memenuhi', $recap->status_kehadiran === self::STATUS_MET);
            $recap->setAttribute(
                'persentase_display',
                $tersedia
                    ? number_format((float) $recap->persentase_kehadiran, 2, ',', '.').'%'
                    : self::STATUS_NO_MEETINGS
            );
        }
    }

    /**
     * Hitung persentase rata-rata dari rekap tersimpan tanpa menyertakan
     * baris yang belum memiliki pertemuan Terlaksana.
     *
     * @param  iterable<RekapKehadiranSik>  $recaps
     */
    public function averagePercentage(iterable $recaps): ?float
    {
        $percentages = collect($recaps)
            ->filter(fn ($recap): bool => $recap instanceof RekapKehadiranSik
                && $recap->status_kehadiran !== self::STATUS_NO_MEETINGS)
            ->map(fn (RekapKehadiranSik $recap): float => (float) $recap->persentase_kehadiran);

        return $percentages->isNotEmpty() ? round((float) $percentages->avg(), 1) : null;
    }

    /**
     * Ringkasan jumlah baris yang akan berubah bila aturan baru diterapkan.
     * Hanya membaca data; tidak menulis ke database.
     *
     * @return array<string, int|string>
     */
    public function dryRunRow(array $rekapRow): array
    {
        $metric = $this->calculate(
            (int) $rekapRow['anggota_periode_sik_id'],
            (int) $rekapRow['kegiatan_id']
        );

        $currentPercentage = (float) ($rekapRow['persentase_kehadiran'] ?? 0);

        $changes = [];
        if ((int) ($rekapRow['total_pertemuan'] ?? 0) !== $metric['total_pertemuan']) {
            $changes[] = 'total_pertemuan';
        }
        if ((int) ($rekapRow['jumlah_hadir'] ?? 0) !== $metric['jumlah_hadir']) {
            $changes[] = 'jumlah_hadir';
        }
        if ((string) ($rekapRow['status_kehadiran'] ?? '') !== $metric['status']) {
            $changes[] = 'status_kehadiran';
        }
        if (abs($currentPercentage - (float) ($metric['persentase'] ?? 0)) > 0.001) {
            $changes[] = 'persentase_kehadiran';
        }

        return [
            'rekap_id' => (int) $rekapRow['id'],
            'anggota_periode_sik_id' => (int) $rekapRow['anggota_periode_sik_id'],
            'kegiatan_id' => (int) $rekapRow['kegiatan_id'],
            'total_pertemuan_saat_ini' => (int) ($rekapRow['total_pertemuan'] ?? 0),
            'jumlah_hadir_saat_ini' => (int) ($rekapRow['jumlah_hadir'] ?? 0),
            'persentase_saat_ini' => number_format($currentPercentage, 2, ',', '.').'%',
            'status_saat_ini' => (string) ($rekapRow['status_kehadiran'] ?? ''),
            'total_terlaksana' => $metric['total_pertemuan'],
            'jumlah_hadir_simulasi' => $metric['jumlah_hadir'],
            'persentase_simulasi' => $metric['tersedia']
                ? number_format((float) $metric['persentase'], 2, ',', '.').'%'
                : self::STATUS_NO_MEETINGS,
            'status_simulasi' => $metric['status'],
            'perubahan' => $changes === [] ? 'Tidak ada' : implode(', ', $changes),
            'terdampak' => $changes === [] ? 0 : 1,
        ];
    }

    /**
     * Query read-only untuk dry-run rekap lama.
     *
     * @return array<int, array<string, int|string>>
     */
    public function dryRunRows(): array
    {
        return DB::table('rekap_kehadiran_sik')
            ->orderBy('id')
            ->get()
            ->map(fn ($row): array => $this->dryRunRow((array) $row))
            ->all();
    }
}
