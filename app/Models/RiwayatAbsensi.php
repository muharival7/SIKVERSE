<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatAbsensi extends Model
{
    protected $table = 'riwayat_absensi';

    protected $fillable = [
        'absensi_sik_id',
        'instruktur_id',
        'status_sebelumnya',
        'status_sesudahnya',
        'alasan_perubahan',
    ];

    public function absensi(): BelongsTo
    {
        return $this->belongsTo(AbsensiSik::class, 'absensi_sik_id');
    }

    public function instruktur(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturSik::class, 'instruktur_id');
    }
}
