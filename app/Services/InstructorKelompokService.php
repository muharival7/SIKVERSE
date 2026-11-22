<?php

namespace App\Services;

use App\Models\InfrastrukturSik;
use App\Models\Kelompok;
use Illuminate\Support\Collection;

class InstructorKelompokService
{
    /**
     * Seluruh kelompok aktif yang dapat dipakai untuk absensi. Hak input
     * absensi tidak bergantung pada kepemilikan kelompok, tetapi status
     * kelompok dan periodenya tetap menjadi batas data yang valid.
     *
     * @return Collection<int, Kelompok>
     */
    public function activeKelompoks(?int $periodeId = null): Collection
    {
        return Kelompok::query()
            ->with('periode')
            ->where('status', 'Aktif')
            ->when(
                $periodeId !== null,
                fn ($query) => $query->where('periode_sik_id', $periodeId)
            )
            ->orderBy('nama_kelompok')
            ->orderBy('id')
            ->get();
    }

    /**
     * Sumber tunggal daftar kelompok instruktur: kelompok.instruktur_id.
     * Dipakai baik oleh absensi maupun nilai agar keduanya melihat kelompok
     * yang sama tanpa relasi pivot atau penempatan ganda.
     *
     * @return Collection<int, int>
     */
    public function ownedGroupIds(InfrastrukturSik $instruktur): Collection
    {
        return Kelompok::query()
            ->where('instruktur_id', $instruktur->id)
            ->orderBy('nama_kelompok')
            ->orderBy('id')
            ->pluck('id');
    }

    /**
     * Kelompok aktif yang menjadi tanggung jawab instruktur. Dipakai oleh
     * halaman nilai agar daftar kelompok, data santri, dan validasi
     * menggunakan batas yang sama. ownedGroupIds() sengaja tidak diubah
     * karena absensi masih memakainya dengan kebijakan tersendiri.
     *
     * @param  Collection<int, int>|null  $ownedGroupIds  opsional agar tidak mengulang query
     * @return Collection<int, int>
     */
    public function activeOwnedGroupIds(InfrastrukturSik $instruktur, ?Collection $ownedGroupIds = null): Collection
    {
        $ownedGroupIds ??= $this->ownedGroupIds($instruktur);

        if ($ownedGroupIds->isEmpty()) {
            return collect();
        }

        return Kelompok::query()
            ->whereIn('id', $ownedGroupIds)
            ->where('status', 'Aktif')
            ->orderBy('nama_kelompok')
            ->orderBy('id')
            ->pluck('id');
    }

    /**
     * Normalisasi filter kelompok dari query string. Menerima nilai tunggal
     * (kompatibel dengan tautan lama) maupun array dari select multiple.
     * Pilihan "Semua kelompok" (string kosong) berarti tanpa filter.
     *
     * @param  Collection<int, int>|null  $allowedGroupIds  null berarti tanpa pembatasan (admin)
     * @return array<int, int>
     */
    public function resolveSelectedGroupIds(mixed $raw, ?Collection $allowedGroupIds = null): array
    {
        $values = is_array($raw) ? $raw : [$raw];

        if (collect($values)->contains(static fn ($value): bool => $value === '' || $value === null)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($value): int => is_scalar($value) ? (int) $value : 0, $values),
            static fn (int $id): bool => $id > 0
        )));

        if ($allowedGroupIds !== null) {
            foreach ($ids as $id) {
                abort_unless(
                    $allowedGroupIds->contains($id),
                    403,
                    'Anda tidak berwenang mengakses kelompok yang dipilih.'
                );
            }
        }

        return $ids;
    }
}
