<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsensiSik extends Model
{
    protected $table = 'absensi_sik';

    protected $fillable = [
        'anggota_periode_sik_id',
        'pertemuan_id',
        'waktu_masuk',
        'waktu_pulang',
        'metode_absensi',
        'status',
        'dicatat_oleh',
        'keterangan',
    ];

    public function anggotaPeriode(): BelongsTo
    {
        return $this->belongsTo(AnggotaPeriodeSik::class, 'anggota_periode_sik_id');
    }

    public function pertemuan(): BelongsTo
    {
        return $this->belongsTo(Pertemuan::class, 'pertemuan_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturSik::class, 'dicatat_oleh');
    }

    public function buktiKetidakhadiran(): HasMany
    {
        return $this->hasMany(BuktiKetidakhadiran::class, 'absensi_sik_id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatAbsensi::class, 'absensi_sik_id');
    }
}
