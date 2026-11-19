<?php

namespace App\Services;

use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\Kegiatan;
use App\Models\KelulusanSik;
use App\Models\Pertemuan;
use App\Models\RekapKehadiranSik;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu sumber perhitungan kehadiran dan evaluasi kelulusan SIK.
 *
 * Semua angka dihitung dari pertemuan berstatus Terlaksana dan absensi_sik.
 * Rekap_kehadiran_sik hanya dipakai sebagai cache penulisan, bukan sebagai
 * sumber angka saat data ditampilkan.
 */
class AttendanceRecapService
{
    public const STATUS_NO_MEETINGS = 'Belum Ada Pertemuan Terlaksana';

    public const STATUS_NO_ATTENDANCE = 'Belum Ada Data Kehadiran';

    public const STATUS_MET = 'Memenuhi';

    public const STATUS_UNMET = 'Belum Memenuhi';

    public const GRADUATION_NO_RECORD = 'Belum ada catatan evaluasi';

    public const GRADUATION_NO_MEETINGS = 'Belum ada pertemuan terlaksana';

    public const GRADUATION_UNMET = 'Belum memenuhi ambang kehadiran';

    public const GRADUATION_MET = 'Memenuhi ambang kehadiran';

    public const GRADUATION_PASSED = 'Lulus';

    public const GRADUATION_FAILED = 'Tidak Lulus';

    private const STATUSES_RECORDED = ['Hadir', 'Izin', 'Sakit', 'Alpha', 'Tidak Lengkap'];

    /** @var array<int, array<string, mixed>> */
    private array $anggotaMetricCache = [];

    /**
     * Hitung metrik kehadiran satu anggota untuk satu kegiatan.
     *
     * @return array{
     *     anggota_periode_sik_id: int,
     *     kegiatan_id: int|null,
     *     total_pertemuan: int,
     *     jumlah_absensi: int,
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
            ->whereIn('absensi_sik.status', self::STATUSES_RECORDED)
            ->selectRaw('absensi_sik.status as absensi_status, COUNT(*) as jumlah')
            ->groupBy('absensi_sik.status')
            ->pluck('jumlah', 'absensi_status');

        $jumlahAbsensi = (int) $jumlahPerStatus->sum();
        $jumlahHadir = (int) ($jumlahPerStatus['Hadir'] ?? 0);
        $jumlahIzin = (int) ($jumlahPerStatus['Izin'] ?? 0);
        $jumlahSakit = (int) ($jumlahPerStatus['Sakit'] ?? 0);
        $jumlahAlpha = (int) ($jumlahPerStatus['Alpha'] ?? 0);
        $jumlahTidakLengkap = (int) ($jumlahPerStatus['Tidak Lengkap'] ?? 0);
        $tersedia = $totalPertemuan > 0;
        $memenuhi = $this->meetsThreshold($jumlahHadir, $totalPertemuan);

        return [
            'anggota_periode_sik_id' => $anggotaPeriodeSikId,
            'kegiatan_id' => $kegiatanId,
            'total_pertemuan' => $totalPertemuan,
            'jumlah_absensi' => $jumlahAbsensi,
            'jumlah_hadir' => $jumlahHadir,
            'jumlah_izin' => $jumlahIzin,
            'jumlah_sakit' => $jumlahSakit,
            'jumlah_alpha' => $jumlahAlpha,
            'jumlah_tidak_lengkap' => $jumlahTidakLengkap,
            'persentase' => $tersedia && $jumlahAbsensi > 0
                ? ($jumlahHadir / $totalPertemuan) * 100
                : null,
            'status' => $this->metricStatus($tersedia, $jumlahAbsensi, $memenuhi),
            'memenuhi' => $memenuhi,
            'tersedia' => $tersedia,
        ];
    }

    /**
     * Agregasi lintas kegiatan untuk satu anggota.
     *
     * Kegiatan yang dihitung adalah kegiatan pada periode anggota yang
     * bersifat bersama (kelompok_id null) atau menjadi kelompok anggota.
     * Kegiatan tidak ditebak dari cache rekap maupun absensi.
     *
     * @return array{
     *     anggota_periode_sik_id: int,
     *     kegiatan_id: null,
     *     total_pertemuan: int,
     *     jumlah_absensi: int,
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
    public function calculateForAnggota(int $anggotaPeriodeSikId): array
    {
        if (isset($this->anggotaMetricCache[$anggotaPeriodeSikId])) {
            return $this->anggotaMetricCache[$anggotaPeriodeSikId];
        }

        $anggota = AnggotaPeriodeSik::query()->find($anggotaPeriodeSikId);

        if (! $anggota instanceof AnggotaPeriodeSik) {
            return $this->emptyMetric($anggotaPeriodeSikId);
        }

        $totals = [
            'total_pertemuan' => 0,
            'jumlah_absensi' => 0,
            'jumlah_hadir' => 0,
            'jumlah_izin' => 0,
            'jumlah_sakit' => 0,
            'jumlah_alpha' => 0,
            'jumlah_tidak_lengkap' => 0,
        ];

        foreach ($this->kegiatanForAnggota($anggota) as $kegiatan) {
            $metric = $this->calculate($anggotaPeriodeSikId, (int) $kegiatan->id);

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $metric[$key];
            }
        }

        $tersedia = $totals['total_pertemuan'] > 0;
        $memenuhi = $this->meetsThreshold($totals['jumlah_hadir'], $totals['total_pertemuan']);

        return $this->anggotaMetricCache[$anggotaPeriodeSikId] = [
            'anggota_periode_sik_id' => $anggotaPeriodeSikId,
            'kegiatan_id' => null,
            ...$totals,
            'persentase' => $tersedia && $totals['jumlah_absensi'] > 0
                ? ($totals['jumlah_hadir'] / $totals['total_pertemuan']) * 100
                : null,
            'status' => $this->metricStatus($tersedia, $totals['jumlah_absensi'], $memenuhi),
            'memenuhi' => $memenuhi,
            'tersedia' => $tersedia,
        ];
    }

    /**
     * Baris tampilan rekap untuk anggota aktif, termasuk anggota yang belum
     * memiliki absensi atau baris rekap tersimpan.
     *
     * @param  Collection<int, AnggotaPeriodeSik>  $anggota
     * @return Collection<int, RekapKehadiranSik>
     */
    public function displayRows(Collection $anggota): Collection
    {
        return $anggota->flatMap(function (AnggotaPeriodeSik $item): Collection {
            $kegiatan = $this->kegiatanForAnggota($item);

            if ($kegiatan->isEmpty()) {
                return collect([
                    $this->makeDisplayRow($item, null, $this->calculateForAnggota((int) $item->id)),
                ]);
            }

            return $kegiatan->map(fn (Kegiatan $activity): RekapKehadiranSik => $this->makeDisplayRow(
                $item,
                $activity,
                $this->calculate((int) $item->id, (int) $activity->id)
            ));
        })->values();
    }

    /**
     * Satu baris tampilan untuk detail rekap. Kegiatan harus masih menjadi
     * bagian dari periode dan kelompok anggota; baris boleh dibuat tanpa
     * catatan rekap tersimpan.
     */
    public function displayRowForAnggota(AnggotaPeriodeSik $anggota, ?int $kegiatanId): ?RekapKehadiranSik
    {
        if ($kegiatanId === null) {
            return $this->makeDisplayRow($anggota, null, $this->calculateForAnggota((int) $anggota->id));
        }

        $kegiatan = $this->kegiatanForAnggota($anggota)->firstWhere('id', $kegiatanId);

        if (! $kegiatan instanceof Kegiatan) {
            return null;
        }

        return $this->makeDisplayRow(
            $anggota,
            $kegiatan,
            $this->calculate((int) $anggota->id, (int) $kegiatan->id)
        );
    }

    /**
     * Riwayat pertemuan untuk detail rekap. Pertemuan Terjadwal tetap tampil
     * sebagai informasi, tetapi tidak menyumbang angka kehadiran.
     *
     * @return Collection<int, Pertemuan>
     */
    public function pertemuanForAnggota(AnggotaPeriodeSik $anggota, ?int $kegiatanId): Collection
    {
        $kegiatanIds = $this->kegiatanForAnggota($anggota)
            ->when(
                $kegiatanId !== null,
                fn (Collection $items): Collection => $items->where('id', $kegiatanId)
            )
            ->modelKeys();

        if ($kegiatanIds === []) {
            return collect();
        }

        return Pertemuan::query()
            ->with([
                'absensi' => fn ($query) => $query->where('anggota_periode_sik_id', (int) $anggota->id),
                'kegiatan',
            ])
            ->whereIn('kegiatan_id', $kegiatanIds)
            ->orderBy('tanggal')
            ->orderBy('pertemuan_ke')
            ->orderBy('id')
            ->get();
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
     * Satu format persentase untuk seluruh tampilan. Pembulatan hanya terjadi
     * di sini, sehingga tidak ada pembulatan berantai di controller atau Blade.
     */
    public function formatPercentage(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        $formatted = number_format($value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    /**
     * Pesan kegagalan ambang yang dipakai oleh validasi kelulusan dan sertifikat.
     *
     * @param  array<string, mixed>  $metric
     */
    public function thresholdRequirementMessage(array $metric): string
    {
        $minimum = $this->formatPercentage($this->minimumPercentage()).'%';

        if (! (bool) $metric['tersedia']) {
            return 'Belum ada pertemuan Terlaksana yang dapat dijadikan dasar evaluasi; ambang '.$minimum.'.';
        }

        if ($metric['status'] === self::STATUS_NO_ATTENDANCE) {
            return 'Belum ada catatan kehadiran pada pertemuan Terlaksana; ambang '.$minimum.'.';
        }

        return 'Kehadiran '.$this->formatPercentage($metric['persentase'] === null ? null : (float) $metric['persentase']).'% dari pertemuan Terlaksana; ambang '.$minimum.'.';
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
     * Daftar anggota aktif yang memenuhi ambang kehadiran untuk kelulusan.
     *
     * @return Collection<int, int>
     */
    public function eligibleAnggotaIds(): Collection
    {
        return AnggotaPeriodeSik::query()
            ->where('status', 'Aktif')
            ->pluck('id')
            ->filter(fn ($anggotaId): bool => $this->calculateForAnggota((int) $anggotaId)['memenuhi'])
            ->values();
    }

    /**
     * Evaluasi kelulusan yang dipakai bersama oleh admin, santri, dan Ka-Lab Mutu.
     *
     * Catatan kelulusan yang tersimpan tidak diubah; status yang ditampilkan
     * selalu mengikuti ambang kehadiran yang dihitung ulang dari absensi.
     *
     * @return array{
     *     code: string,
     *     label: string,
     *     alasan: string,
     *     catatan_tersimpan: string|null,
     *     keterangan: string|null,
     *     metric: array<string, mixed>,
     *     memenuhi_ambang: bool
     * }
     */
    public function graduationEvaluation(?KelulusanSik $kelulusan, ?AnggotaPeriodeSik $anggota = null): array
    {
        $anggota ??= $kelulusan?->anggotaPeriode;
        $metric = $anggota instanceof AnggotaPeriodeSik
            ? $this->calculateForAnggota((int) $anggota->id)
            : $this->emptyMetric(0);
        $catatanTersimpan = $kelulusan?->status;
        $persentase = $this->formatPercentage($metric['persentase']);

        if ($kelulusan === null) {
            $code = self::GRADUATION_NO_RECORD;
        } elseif (! $metric['tersedia']) {
            $code = self::GRADUATION_NO_MEETINGS;
        } elseif (! $metric['memenuhi']) {
            $code = self::GRADUATION_UNMET;
        } elseif ($catatanTersimpan === 'Lulus') {
            $code = self::GRADUATION_PASSED;
        } elseif ($catatanTersimpan === 'Tidak Lulus') {
            $code = self::GRADUATION_FAILED;
        } else {
            $code = self::GRADUATION_MET;
        }

        $alasan = match ($code) {
            self::GRADUATION_NO_RECORD => 'Belum ada catatan evaluasi kelulusan untuk peserta ini.',
            self::GRADUATION_NO_MEETINGS => $this->thresholdRequirementMessage($metric),
            self::GRADUATION_UNMET => $this->thresholdRequirementMessage($metric),
            self::GRADUATION_MET => 'Kehadiran '.$persentase.' memenuhi ambang '.$this->formatPercentage($this->minimumPercentage()).'%; status kelulusan belum ditetapkan.',
            self::GRADUATION_PASSED => 'Kehadiran '.$persentase.' memenuhi ambang '.$this->formatPercentage($this->minimumPercentage()).'%; status Lulus telah dicatat.',
            self::GRADUATION_FAILED => 'Status Tidak Lulus telah dicatat pada catatan evaluasi.',
            default => 'Status evaluasi belum dapat ditentukan.',
        };

        return [
            'code' => $code,
            'label' => $code,
            'alasan' => $alasan,
            'catatan_tersimpan' => $catatanTersimpan,
            'keterangan' => $kelulusan?->keterangan,
            'metric' => $metric,
            'memenuhi_ambang' => (bool) $metric['memenuhi'],
        ];
    }

    /**
     * Evaluasi kelulusan untuk seluruh catatan yang tersedia.
     *
     * @return Collection<int, array{kelulusan: KelulusanSik, anggota: AnggotaPeriodeSik|null, tahun: int, evaluasi: array<string, mixed>}>
     */
    public function graduationEvaluations(?int $periodeSikId = null): Collection
    {
        $query = KelulusanSik::query()->with([
            'anggotaPeriodeSik.santri',
            'anggotaPeriodeSik.periode',
            'anggotaPeriodeSik.kelompok',
            'sertifikat',
        ]);

        if ($periodeSikId !== null) {
            $query->whereHas(
                'anggotaPeriodeSik',
                fn ($memberQuery) => $memberQuery->where('periode_sik_id', $periodeSikId)
            );
        }

        return $query->latest()->orderByDesc('id')->get()->map(function (KelulusanSik $kelulusan): array {
            $anggota = $kelulusan->anggotaPeriodeSik;

            return [
                'kelulusan' => $kelulusan,
                'anggota' => $anggota,
                'tahun' => (int) ($anggota?->periode?->tahun ?? 0),
                'evaluasi' => $this->graduationEvaluation($kelulusan, $anggota),
            ];
        })->values();
    }

    /**
     * Ringkasan kelulusan per tahun dengan status turunan yang sama seperti
     * yang dipakai dashboard admin dan dashboard santri.
     *
     * @return Collection<int, array<string, int>>
     */
    public function graduationAnnualSummary(?int $periodeSikId = null): Collection
    {
        $kodeStatus = [
            self::GRADUATION_NO_RECORD,
            self::GRADUATION_NO_MEETINGS,
            self::GRADUATION_UNMET,
            self::GRADUATION_MET,
            self::GRADUATION_PASSED,
            self::GRADUATION_FAILED,
        ];

        return $this->graduationEvaluations($periodeSikId)
            ->groupBy('tahun')
            ->map(function (Collection $rows, int $tahun) use ($kodeStatus): array {
                $summary = [
                    'tahun' => $tahun,
                    'total' => $rows->count(),
                    'lulus' => 0,
                    'tidak_lulus' => 0,
                    'belum_ada_catatan_evaluasi' => 0,
                    'belum_ada_pertemuan_terlaksana' => 0,
                    'belum_memenuhi_ambang' => 0,
                    'memenuhi_ambang' => 0,
                    'belum_ditetapkan' => 0,
                ];

                foreach ($kodeStatus as $kode) {
                    $count = $rows->filter(fn (array $row): bool => $row['evaluasi']['code'] === $kode)->count();

                    $summary[match ($kode) {
                        self::GRADUATION_PASSED => 'lulus',
                        self::GRADUATION_FAILED => 'tidak_lulus',
                        self::GRADUATION_NO_RECORD => 'belum_ada_catatan_evaluasi',
                        self::GRADUATION_NO_MEETINGS => 'belum_ada_pertemuan_terlaksana',
                        self::GRADUATION_UNMET => 'belum_memenuhi_ambang',
                        self::GRADUATION_MET => 'memenuhi_ambang',
                    }] = $count;
                }

                $summary['belum_ditetapkan'] = $summary['belum_ada_catatan_evaluasi']
                    + $summary['belum_ada_pertemuan_terlaksana']
                    + $summary['belum_memenuhi_ambang']
                    + $summary['memenuhi_ambang'];

                return $summary;
            })
            ->sortBy('tahun')
            ->values();
    }

    /**
     * Tambahkan atribut tampilan yang dihitung dari satu sumber metrik.
     *
     * @param  iterable<RekapKehadiranSik>  $recaps
     */
    public function prepareDisplay(iterable $recaps): void
    {
        foreach ($recaps as $recap) {
            if (! $recap instanceof RekapKehadiranSik) {
                continue;
            }

            $metric = $recap->getAttribute('attendance_metric');

            if (! is_array($metric)) {
                $metric = $this->metricForDisplayRow($recap);
            }

            $showPercentage = $metric['tersedia'] && $metric['status'] !== self::STATUS_NO_ATTENDANCE;

            $recap->setAttribute('tersedia', (bool) $metric['tersedia']);
            $recap->setAttribute('memenuhi', (bool) $metric['memenuhi']);
            $recap->setAttribute('attendance_metric', $metric);
            $recap->setAttribute('status_kehadiran', (string) $metric['status']);
            $recap->setAttribute(
                'persentase_display',
                $showPercentage
                    ? $this->formatPercentage($metric['persentase']).'%'
                    : (string) $metric['status']
            );
        }
    }

    /**
     * Hitung rata-rata persentase tanpa pembulatan antara.
     *
     * @param  iterable<RekapKehadiranSik>  $recaps
     */
    public function averagePercentage(iterable $recaps): ?float
    {
        $percentages = collect($recaps)
            ->filter(fn ($recap): bool => $recap instanceof RekapKehadiranSik)
            ->map(function (RekapKehadiranSik $recap): ?float {
                $metric = $recap->getAttribute('attendance_metric');

                if (! is_array($metric)) {
                    $metric = $this->metricForDisplayRow($recap);
                }

                return $metric['persentase'] === null ? null : (float) $metric['persentase'];
            })
            ->filter();

        return $percentages->isNotEmpty() ? (float) $percentages->avg() : null;
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
            'persentase_saat_ini' => $this->formatPercentage($currentPercentage),
            'status_saat_ini' => (string) ($rekapRow['status_kehadiran'] ?? ''),
            'total_terlaksana' => $metric['total_pertemuan'],
            'jumlah_hadir_simulasi' => $metric['jumlah_hadir'],
            'persentase_simulasi' => $metric['persentase'] === null
                ? (string) $metric['status']
                : $this->formatPercentage($metric['persentase']),
            'status_simulasi' => (string) $metric['status'],
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

    /** @return Collection<int, Kegiatan> */
    private function kegiatanForAnggota(AnggotaPeriodeSik $anggota): Collection
    {
        return Kegiatan::query()
            ->where('periode_sik_id', $anggota->periode_sik_id)
            ->when($anggota->kelompok_id !== null, function ($query) use ($anggota): void {
                $query->where(function ($groupQuery) use ($anggota): void {
                    $groupQuery->whereNull('kelompok_id')
                        ->orWhere('kelompok_id', $anggota->kelompok_id);
                });
            })
            ->orderBy('id')
            ->get();
    }

    /** @param array<string, mixed> $metric */
    private function makeDisplayRow(
        AnggotaPeriodeSik $anggota,
        ?Kegiatan $kegiatan,
        array $metric
    ): RekapKehadiranSik {
        $row = new RekapKehadiranSik([
            'anggota_periode_sik_id' => (int) $anggota->id,
            'kegiatan_id' => $kegiatan?->id,
            'total_pertemuan' => $metric['total_pertemuan'],
            'jumlah_hadir' => $metric['jumlah_hadir'],
            'jumlah_izin' => $metric['jumlah_izin'],
            'jumlah_sakit' => $metric['jumlah_sakit'],
            'jumlah_alpha' => $metric['jumlah_alpha'],
            'jumlah_tidak_lengkap' => $metric['jumlah_tidak_lengkap'],
            'persentase_kehadiran' => $metric['persentase'] ?? 0,
            'status_kehadiran' => $metric['status'],
        ]);

        $row->setRelation('anggotaPeriode', $anggota);
        $row->setRelation('kegiatan', $kegiatan);
        $row->setAttribute('attendance_metric', $metric);

        return $row;
    }

    /** @return array<string, mixed> */
    private function metricForDisplayRow(RekapKehadiranSik $recap): array
    {
        $kegiatanId = (int) $recap->kegiatan_id;
        $anggotaId = (int) $recap->anggota_periode_sik_id;

        return $kegiatanId > 0
            ? $this->calculate($anggotaId, $kegiatanId)
            : $this->calculateForAnggota($anggotaId);
    }

    /** @return array<string, mixed> */
    private function emptyMetric(int $anggotaPeriodeSikId): array
    {
        return [
            'anggota_periode_sik_id' => $anggotaPeriodeSikId,
            'kegiatan_id' => null,
            'total_pertemuan' => 0,
            'jumlah_absensi' => 0,
            'jumlah_hadir' => 0,
            'jumlah_izin' => 0,
            'jumlah_sakit' => 0,
            'jumlah_alpha' => 0,
            'jumlah_tidak_lengkap' => 0,
            'persentase' => null,
            'status' => self::STATUS_NO_MEETINGS,
            'memenuhi' => false,
            'tersedia' => false,
        ];
    }

    private function metricStatus(bool $tersedia, int $jumlahAbsensi, bool $memenuhi): string
    {
        if (! $tersedia) {
            return self::STATUS_NO_MEETINGS;
        }

        if ($jumlahAbsensi === 0) {
            return self::STATUS_NO_ATTENDANCE;
        }

        return $memenuhi ? self::STATUS_MET : self::STATUS_UNMET;
    }
}
