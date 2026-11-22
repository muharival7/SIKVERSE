<?php

namespace App\Services;

use App\Models\AnggotaPeriodeSik;
use App\Models\Kegiatan;
use App\Models\Santri;

class ExamEligibilityService
{
    public function __construct(private readonly AttendanceRecapService $attendanceRecapService) {}

    /**
     * Kelayakan mengikuti ujian memakai keanggotaan pada Kegiatan SIK yang
     * dituju bila konteks kegiatan tersedia, dengan perhitungan kehadiran
     * yang sama dengan rekap/laporan.
     *
     * @return array{
     *     eligible: bool,
     *     metric: array<string, mixed>|null,
     *     alasan: string
     * }
     */
    public function forSantri(int $santriId, ?int $kegiatanId = null): array
    {
        $santri = Santri::query()->with('anggotaPeriodeTerbaru')->find($santriId);

        if (! $santri instanceof Santri) {
            return [
                'eligible' => false,
                'metric' => null,
                'alasan' => 'Santri tidak ditemukan.',
            ];
        }

        $anggota = $kegiatanId === null
            ? $santri->anggotaPeriodeTerbaru
            : $this->anggotaUntukKegiatan($santriId, $kegiatanId);

        if ($anggota === null) {
            return [
                'eligible' => false,
                'metric' => null,
                'alasan' => $kegiatanId === null
                    ? 'Santri tidak memiliki keanggotaan periode terbaru.'
                    : 'Santri tidak memiliki keanggotaan aktif pada kegiatan ini.',
            ];
        }

        $metric = $this->attendanceRecapService->calculateForAnggota((int) $anggota->id);

        return [
            'eligible' => (bool) $metric['memenuhi'],
            'metric' => $metric,
            'alasan' => (bool) $metric['memenuhi']
                ? 'Memenuhi ambang kehadiran ujian.'
                : $this->attendanceRecapService->thresholdRequirementMessage($metric),
        ];
    }

    public function rejectionMessage(int $santriId, ?int $kegiatanId = null): string
    {
        $minimum = $this->attendanceRecapService->formatPercentage(
            $this->attendanceRecapService->minimumPercentage()
        ).'%';

        return 'Santri belum memenuhi syarat kehadiran '.$minimum.' untuk mengikuti ujian. '
            .$this->forSantri($santriId, $kegiatanId)['alasan'];
    }

    private function anggotaUntukKegiatan(int $santriId, int $kegiatanId): ?AnggotaPeriodeSik
    {
        $kegiatan = Kegiatan::query()->find($kegiatanId);

        if (! $kegiatan instanceof Kegiatan) {
            return null;
        }

        return AnggotaPeriodeSik::query()
            ->where('santri_id', $santriId)
            ->where('periode_sik_id', $kegiatan->periode_sik_id)
            ->where('kelompok_id', $kegiatan->kelompok_id)
            ->where('status', 'Aktif')
            ->orderByDesc('id')
            ->first();
    }
}
