<?php

namespace App\Services;

use App\Models\AnggotaPeriodeSik;

class ExamEligibilityService
{
    public function __construct(private readonly AttendanceRecapService $attendanceRecapService) {}

    /**
     * Kelayakan ujian dihitung berdasarkan seluruh kehadiran SIK santri dalam
     * konteks anggota periode yang eksplisit. Perhitungan memakai metrik rekap
     * kehadiran yang sama: hanya pertemuan Terlaksana, kegiatan reguler
     * kelompok santri dan kegiatan bersama (kelompok_id NULL) dalam periode
     * yang relevan; kegiatan kelompok lain tidak dihitung.
     *
     * @return array{
     *     eligible: bool,
     *     metric: array<string, mixed>|null,
     *     alasan: string
     * }
     */
    public function forAnggotaPeriode(AnggotaPeriodeSik $anggota): array
    {
        if (! $this->isEligibleMembership($anggota)) {
            return [
                'eligible' => false,
                'metric' => null,
                'alasan' => 'Konteks keanggotaan periode santri tidak valid atau tidak aktif.',
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

    public function rejectionMessageForAnggotaPeriode(AnggotaPeriodeSik $anggota): string
    {
        $minimum = $this->attendanceRecapService->formatPercentage(
            $this->attendanceRecapService->minimumPercentage()
        ).'%';

        return 'Santri belum memenuhi syarat kehadiran '.$minimum.' untuk mengikuti ujian. '
            .$this->forAnggotaPeriode($anggota)['alasan'];
    }

    private function isEligibleMembership(AnggotaPeriodeSik $anggota): bool
    {
        return $anggota->exists
            && (int) $anggota->santri_id > 0
            && (int) $anggota->periode_sik_id > 0
            && (int) $anggota->kelompok_id > 0
            && $anggota->status === 'Aktif';
    }
}
