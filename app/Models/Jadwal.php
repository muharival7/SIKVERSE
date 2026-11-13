<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jadwal extends Model
{
    protected $table = 'jadwal';

    protected $fillable = [
        'mata_pelajaran_id',
        'infrastruktur_sik_id',
        'hari',
        'waktu_mulai',
        'waktu_selesai',
        'ruang',
        'status',
    ];

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function infrastrukturSik(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturSik::class);
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }
}
