<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kegiatan extends Model
{
    protected $table = 'kegiatan';

    protected $fillable = [
        'periode_sik_id',
        'kelompok_id',
        'nama_kegiatan',
        'jenis_kegiatan',
        'keterangan',
        'status',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeSik::class, 'periode_sik_id');
    }

    public function kelompok(): BelongsTo
    {
        return $this->belongsTo(Kelompok::class, 'kelompok_id');
    }

    public function pertemuan(): HasMany
    {
        return $this->hasMany(Pertemuan::class, 'kegiatan_id');
    }

    public function rekapKehadiran(): HasMany
    {
        return $this->hasMany(RekapKehadiranSik::class, 'kegiatan_id');
    }
}
