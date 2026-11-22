<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPerubahanAbsensi extends Model
{
    public const JENIS_STATUS_ABSENSI = 'status_absensi';

    public const JENIS_STATUS_PERTEMUAN = 'status_pertemuan';

    public const JENIS_TANGGAL_PERTEMUAN = 'tanggal_pertemuan';

    protected $table = 'riwayat_perubahan_absensi';

    protected $fillable = [
        'jenis_perubahan',
        'absensi_sik_id',
        'pertemuan_id',
        'actor_user_id',
        'instruktur_id',
        'status_sebelumnya',
        'status_sesudahnya',
        'tanggal_sebelumnya',
        'tanggal_sesudahnya',
        'alasan_perubahan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_sebelumnya' => 'date',
            'tanggal_sesudahnya' => 'date',
        ];
    }

    public function absensi(): BelongsTo
    {
        return $this->belongsTo(AbsensiSik::class, 'absensi_sik_id');
    }

    public function pertemuan(): BelongsTo
    {
        return $this->belongsTo(Pertemuan::class, 'pertemuan_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function instruktur(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturSik::class, 'instruktur_id');
    }
}
