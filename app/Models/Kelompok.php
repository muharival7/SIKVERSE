<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelompok extends Model
{
    protected $table = 'kelompok';

    protected $fillable = [
        'periode_sik_id',
        'instruktur_id',
        'nama_kelompok',
        'keterangan',
        'status',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeSik::class, 'periode_sik_id');
    }

    public function instruktur(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturSik::class, 'instruktur_id');
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(AnggotaPeriodeSik::class, 'kelompok_id');
    }

    public function kegiatan(): HasMany
    {
        return $this->hasMany(Kegiatan::class, 'kelompok_id');
    }
}
