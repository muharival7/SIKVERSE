<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekapKehadiranSik extends Model
{
    protected $table = 'rekap_kehadiran_sik';

    protected $fillable = [
        'anggota_periode_sik_id',
        'kegiatan_id',
        'total_pertemuan',
        'jumlah_hadir',
        'jumlah_izin',
        'jumlah_sakit',
        'jumlah_alpha',
        'jumlah_tidak_lengkap',
        'persentase_kehadiran',
        'status_kehadiran',
    ];

    protected function casts(): array
    {
        return [
            'persentase_kehadiran' => 'decimal:2',
        ];
    }

    public function anggotaPeriode(): BelongsTo
    {
        return $this->belongsTo(AnggotaPeriodeSik::class, 'anggota_periode_sik_id');
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_id');
    }
}
