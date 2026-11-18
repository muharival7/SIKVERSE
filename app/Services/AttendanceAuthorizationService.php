<?php

namespace App\Services;

use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\Pertemuan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AttendanceAuthorizationService
{
    public function isSharedActivityName(string $namaKegiatan): bool
    {
        return str_contains(mb_strtolower($namaKegiatan), 'mengaji');
    }

    public function isSharedActivity(Kegiatan $kegiatan): bool
    {
        return $this->isSharedActivityName((string) $kegiatan->nama_kegiatan);
    }

    /**
     * @param  Builder<Kegiatan>  $query
     * @return Builder<Kegiatan>
     */
    public function scopeSharedActivity(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(nama_kegiatan) LIKE ?', ['%mengaji%']);
    }

    public function isActiveInstructor(InfrastrukturSik $instruktur): bool
    {
        return $instruktur->status === 'Aktif';
    }

    public function authorize(
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

        if ($this->isSharedActivity($kegiatan)) {
            $this->assertSharedActivityShape($kegiatan, $pertemuan);
            $this->assertSharedMembership($anggota, $kegiatan);
            $this->assertSharedInstructor($instruktur);

            return;
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

    /**
     * Otorisasi membuka halaman input kehadiran satu pertemuan. Admin selalu
     * diizinkan. Instruktur SIK aktif mencakup seluruh kelompok untuk kegiatan bersama,
     * sedangkan kegiatan reguler tetap dibatasi pada kelompok penanggung jawab.
     */
    public function authorizePertemuanAccess(Pertemuan $pertemuan, ?InfrastrukturSik $instruktur): void
    {
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

        if ($this->isSharedActivity($kegiatan)) {
            $this->assertSharedActivityShape($kegiatan, $pertemuan);
            $this->assertSharedInstructor($instruktur);

            return;
        }

        $targetGroup = $pertemuan->kelompokHost ?? $kegiatan->kelompok;

        if (! $targetGroup instanceof Kelompok) {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Target kelompok pertemuan belum ditentukan. Isi kelompok host pertemuan atau kelompok kegiatan terlebih dahulu.',
            ]);
        }

        if (! $instruktur instanceof InfrastrukturSik) {
            return;
        }

        $isAssigned = (int) $targetGroup->instruktur_id === (int) $instruktur->id
            || (int) $pertemuan->instruktur_pengganti_id === (int) $instruktur->id;

        abort_unless($isAssigned, 403, 'Anda tidak berwenang menginput kehadiran pertemuan ini.');
    }

    /**
     * Otorisasi perubahan tanggal pertemuan. Admin ($instruktur null) selalu
     * diizinkan; instruktur hanya untuk pertemuan yang menjadi tanggung jawabnya.
     */
    public function authorizePertemuanDateChange(Pertemuan $pertemuan, ?InfrastrukturSik $instruktur): void
    {
        if ($pertemuan->status === 'Dibatalkan') {
            throw ValidationException::withMessages([
                'tanggal_baru' => 'Tanggal pertemuan yang dibatalkan tidak dapat diubah.',
            ]);
        }

        if (! $instruktur instanceof InfrastrukturSik) {
            return;
        }

        $isAssigned = (int) $pertemuan->kelompokHost?->instruktur_id === (int) $instruktur->id
            || (int) $pertemuan->kegiatan?->kelompok?->instruktur_id === (int) $instruktur->id
            || (int) $pertemuan->instruktur_pengganti_id === (int) $instruktur->id;

        abort_unless($isAssigned, 403, 'Anda tidak berwenang mengubah tanggal pertemuan ini.');
    }

    private function assertSharedActivityShape(Kegiatan $kegiatan, Pertemuan $pertemuan): void
    {
        if ($kegiatan->kelompok_id !== null || $pertemuan->kelompok_host_id !== null) {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Kegiatan bersama tidak boleh terikat pada kelompok atau kelompok host.',
            ]);
        }
    }

    private function assertSharedMembership(AnggotaPeriodeSik $anggota, Kegiatan $kegiatan): void
    {
        if ($anggota->status !== 'Aktif') {
            throw ValidationException::withMessages([
                'anggota_periode_sik_id' => 'Anggota periode SIK tidak aktif.',
            ]);
        }

        $kelompok = $anggota->kelompok;

        if (! $kelompok instanceof Kelompok || $kelompok->status !== 'Aktif') {
            throw ValidationException::withMessages([
                'anggota_periode_sik_id' => 'Kelompok asal santri tidak ditemukan atau tidak aktif.',
            ]);
        }

        $periodeIds = array_map('intval', array_filter([
            $anggota->periode_sik_id,
            $kegiatan->periode_sik_id,
            $kelompok->periode_sik_id,
        ], static fn ($value): bool => $value !== null));

        if (count(array_unique($periodeIds)) > 1) {
            throw ValidationException::withMessages([
                'anggota_periode_sik_id' => 'Santri, kelompok asal, dan kegiatan harus berada pada periode SIK yang sama.',
            ]);
        }
    }

    private function assertSharedInstructor(?InfrastrukturSik $instruktur): void
    {
        if (! $instruktur instanceof InfrastrukturSik) {
            return;
        }

        abort_unless(
            $this->isActiveInstructor($instruktur),
            403,
            'Instruktur tidak aktif tidak berwenang menginput kehadiran.'
        );
    }
}
