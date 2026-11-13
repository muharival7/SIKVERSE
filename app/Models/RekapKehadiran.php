<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekapKehadiran extends Model
{
    protected $table = 'rekap_kehadiran';

    protected $fillable = [
        'santri_id',
        'total_pertemuan',
        'jumlah_hadir',
        'jumlah_izin',
        'jumlah_sakit',
        'jumlah_alpha',
        'persentase_kehadiran',
        'status_ujian',
        'tahun_ajaran',
        'semester',
    ];

    protected function casts(): array
    {
        return [
            'persentase_kehadiran' => 'decimal:2',
        ];
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }
}
