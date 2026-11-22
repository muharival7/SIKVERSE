<?php

namespace App\Services;

use App\Models\InfrastrukturSik;
use App\Models\RiwayatPerubahanAbsensi;
use App\Models\User;

class AttendanceHistoryService
{
    public function recordAbsensiStatusChange(
        int $absensiSikId,
        int $pertemuanId,
        ?string $statusSebelumnya,
        string $statusSesudahnya,
        ?User $actor,
        ?InfrastrukturSik $instruktur,
        ?string $alasan
    ): void {
        if ($statusSebelumnya === $statusSesudahnya) {
            return;
        }

        $this->record([
            'jenis_perubahan' => RiwayatPerubahanAbsensi::JENIS_STATUS_ABSENSI,
            'absensi_sik_id' => $absensiSikId,
            'pertemuan_id' => $pertemuanId,
            'status_sebelumnya' => $statusSebelumnya,
            'status_sesudahnya' => $statusSesudahnya,
            'actor_user_id' => $actor?->id,
            'instruktur_id' => $instruktur?->id,
            'alasan_perubahan' => $alasan,
        ]);
    }

    public function recordPertemuanStatusChange(
        int $pertemuanId,
        ?string $statusSebelumnya,
        string $statusSesudahnya,
        ?User $actor,
        ?InfrastrukturSik $instruktur,
        ?string $alasan
    ): void {
        if ($statusSebelumnya === $statusSesudahnya) {
            return;
        }

        $this->record([
            'jenis_perubahan' => RiwayatPerubahanAbsensi::JENIS_STATUS_PERTEMUAN,
            'pertemuan_id' => $pertemuanId,
            'status_sebelumnya' => $statusSebelumnya,
            'status_sesudahnya' => $statusSesudahnya,
            'actor_user_id' => $actor?->id,
            'instruktur_id' => $instruktur?->id,
            'alasan_perubahan' => $alasan,
        ]);
    }

    public function recordPertemuanDateChange(
        int $pertemuanId,
        ?string $tanggalSebelumnya,
        string $tanggalSesudahnya,
        ?User $actor,
        ?InfrastrukturSik $instruktur,
        ?string $alasan
    ): void {
        if ($tanggalSebelumnya === $tanggalSesudahnya) {
            return;
        }

        $this->record([
            'jenis_perubahan' => RiwayatPerubahanAbsensi::JENIS_TANGGAL_PERTEMUAN,
            'pertemuan_id' => $pertemuanId,
            'tanggal_sebelumnya' => $tanggalSebelumnya,
            'tanggal_sesudahnya' => $tanggalSesudahnya,
            'actor_user_id' => $actor?->id,
            'instruktur_id' => $instruktur?->id,
            'alasan_perubahan' => $alasan,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(array $attributes): void
    {
        RiwayatPerubahanAbsensi::query()->create($attributes);
    }
}
