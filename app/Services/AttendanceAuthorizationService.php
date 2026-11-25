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

    public function isSharedPertemuan(Pertemuan $pertemuan): bool
    {
        $kegiatan = $pertemuan->relationLoaded('kegiatan')
            ? $pertemuan->kegiatan
            : $pertemuan->kegiatan()->first();

        return $kegiatan instanceof Kegiatan && $this->isSharedActivity($kegiatan);
    }

    /**
     * @param  Builder<Kegiatan>  $query
     * @return Builder<Kegiatan>
     */
    public function scopeSharedActivity(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(nama_kegiatan) LIKE ?', ['%mengaji%']);
    }

    /**
     * @param  Builder<Pertemuan>  $query
     * @return Builder<Pertemuan>
     */
    public function scopeSharedPertemuan(Builder $query): Builder
    {
        return $query->whereHas('kegiatan', fn (Builder $kegiatanQuery): Builder => $this->scopeSharedActivity($kegiatanQuery));
    }

    public function isActiveInstructor(InfrastrukturSik $instruktur): bool
    {
        return $instruktur->isActive();
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

        $this->assertActiveInstructor($instruktur);

        $kegiatan = $pertemuan->kegiatan;

        if (! $kegiatan instanceof Kegiatan) {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Kegiatan pertemuan tidak ditemukan.',
            ]);
        }

        if ($this->isSharedActivity($kegiatan)) {
            $this->assertSharedActivityShape($kegiatan, $pertemuan);
            $this->assertSharedPertemuanAssignedToInstructor($pertemuan, $instruktur);
            $this->assertSharedMembership($anggota, $kegiatan);

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

        if ($targetGroup->status !== 'Aktif') {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Kelompok target pertemuan tidak aktif.',
            ]);
        }

        if ($anggota->status !== 'Aktif') {
            throw ValidationException::withMessages([
                'anggota_periode_sik_id' => 'Anggota periode SIK tidak aktif.',
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
    }

    /**
     * Otorisasi pengelolaan satu pertemuan. Input absensi dibuka untuk seluruh
     * kelompok aktif melalui authorize(), tetapi penandaan selesai dan aksi
     * pengelolaan lainnya tetap pada pemilik kelompok, Dosen pengganti yang
     * ditunjuk, atau Admin. Untuk kegiatan bersama yang tanpa kelompok host,
     * yang berwenang adalah Dosen pengganti yang ditunjuk atau Admin.
     */
    public function authorizePertemuanAccess(Pertemuan $pertemuan, ?InfrastrukturSik $instruktur): void
    {
        if ($pertemuan->status === 'Dibatalkan') {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Pertemuan yang dibatalkan tidak menerima absensi.',
            ]);
        }

        $this->assertActiveInstructor($instruktur);

        $kegiatan = $pertemuan->kegiatan;

        if (! $kegiatan instanceof Kegiatan) {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Kegiatan pertemuan tidak ditemukan.',
            ]);
        }

        if ($this->isSharedActivity($kegiatan)) {
            $this->assertSharedActivityShape($kegiatan, $pertemuan);
            $this->assertSharedPertemuanAssignedToInstructor($pertemuan, $instruktur);

            return;
        }

        $targetGroup = $pertemuan->kelompokHost ?? $kegiatan->kelompok;

        if (! $targetGroup instanceof Kelompok) {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Target kelompok pertemuan belum ditentukan. Isi kelompok host pertemuan atau kelompok kegiatan terlebih dahulu.',
            ]);
        }

        if ($targetGroup->status !== 'Aktif') {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Kelompok target pertemuan tidak aktif.',
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
     * Otorisasi perubahan tanggal pertemuan. Izin ini sengaja dipisahkan dari
     * authorizePertemuanAccess(): untuk pertemuan reguler, semua instruktur
     * aktif boleh mengubah tanggal pertemuan pada kelompok aktif mana pun,
     * sedangkan Tandai Selesai tetap menjadi hak penanggung jawab atau
     * instruktur pengganti. Untuk pertemuan shared, hanya Admin atau
     * instruktur pengganti yang ditetapkan yang boleh mengubah tanggal.
     *
     * Admin dilewati melalui $instruktur null. Parameter kelompok/periode
     * opsional dipakai untuk menolak manipulasi nilai tersembunyi dari form.
     */
    public function authorizePertemuanDateChange(
        Pertemuan $pertemuan,
        ?InfrastrukturSik $instruktur,
        ?int $expectedKelompokId = null,
        ?int $expectedPeriodeId = null
    ): void {
        if ($pertemuan->status === 'Dibatalkan') {
            throw ValidationException::withMessages([
                'tanggal_baru' => 'Tanggal pertemuan yang dibatalkan tidak dapat diubah.',
            ]);
        }

        $this->assertActiveInstructor($instruktur);

        $kegiatan = $pertemuan->kegiatan;

        if (! $kegiatan instanceof Kegiatan) {
            throw ValidationException::withMessages([
                'pertemuan_id' => 'Kegiatan pertemuan tidak ditemukan.',
            ]);
        }

        if ($this->isSharedActivity($kegiatan)) {
            $this->assertSharedActivityShape($kegiatan, $pertemuan);
            $this->assertSharedPertemuanAssignedToInstructor($pertemuan, $instruktur);

            return;
        }

        $hostGroup = $pertemuan->kelompokHost;
        $activityGroup = $kegiatan->kelompok;

        if ($hostGroup instanceof Kelompok && $activityGroup instanceof Kelompok
            && (int) $hostGroup->id !== (int) $activityGroup->id) {
            throw ValidationException::withMessages([
                'kelompok_id' => 'Kelompok host pertemuan dan kelompok kegiatan tidak konsisten.',
            ]);
        }

        $targetGroup = $hostGroup ?? $activityGroup;

        if (! $targetGroup instanceof Kelompok) {
            throw ValidationException::withMessages([
                'kelompok_id' => 'Target kelompok pertemuan belum ditentukan.',
            ]);
        }

        if ($targetGroup->status !== 'Aktif') {
            throw ValidationException::withMessages([
                'kelompok_id' => 'Kelompok target pertemuan tidak aktif.',
            ]);
        }

        $periodeIds = array_map('intval', array_filter([
            $kegiatan->periode_sik_id,
            $targetGroup->periode_sik_id,
            $hostGroup?->periode_sik_id,
            $activityGroup?->periode_sik_id,
        ], static fn ($value): bool => $value !== null));

        if (count(array_unique($periodeIds)) > 1) {
            throw ValidationException::withMessages([
                'periode_sik_id' => 'Periode kegiatan dan kelompok pertemuan harus sama.',
            ]);
        }

        if ($expectedKelompokId !== null && (int) $targetGroup->id !== $expectedKelompokId) {
            throw ValidationException::withMessages([
                'kelompok_id' => 'Kelompok pertemuan tidak sesuai dengan data yang akan diubah.',
            ]);
        }

        if ($expectedPeriodeId !== null && (int) $kegiatan->periode_sik_id !== $expectedPeriodeId) {
            throw ValidationException::withMessages([
                'periode_sik_id' => 'Periode pertemuan tidak sesuai dengan data yang akan diubah.',
            ]);
        }
    }

    public function isAssignedSharedPertemuan(Pertemuan $pertemuan, InfrastrukturSik $instruktur): bool
    {
        return $pertemuan->instruktur_pengganti_id !== null
            && (int) $pertemuan->instruktur_pengganti_id === (int) $instruktur->id;
    }

    /**
     * Admin dilewati melalui $instruktur null. Untuk pertemuan shared,
     * instruktur hanya berwenang jika ditetapkan sebagai instruktur_pengganti_id.
     */
    public function assertSharedPertemuanAssignedToInstructor(
        Pertemuan $pertemuan,
        ?InfrastrukturSik $instruktur
    ): void {
        if (! $instruktur instanceof InfrastrukturSik) {
            return;
        }

        abort_unless(
            $this->isAssignedSharedPertemuan($pertemuan, $instruktur),
            403,
            'Anda tidak berwenang mengakses pertemuan bersama ini.'
        );
    }



    public function assertSharedActivityShape(Kegiatan $kegiatan, Pertemuan $pertemuan): void
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

    private function assertActiveInstructor(?InfrastrukturSik $instruktur): void
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
