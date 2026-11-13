<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnggotaPeriodeSik extends Model
{
    protected $table = 'anggota_periode_sik';

    protected $fillable = [
        'santri_id',
        'periode_sik_id',
        'kelompok_id',
        'tanggal_masuk',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
        ];
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeSik::class, 'periode_sik_id');
    }

    public function kelompok(): BelongsTo
    {
        return $this->belongsTo(Kelompok::class, 'kelompok_id');
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(AbsensiSik::class, 'anggota_periode_sik_id');
    }

    public function rekapKehadiran(): HasMany
    {
        return $this->hasMany(RekapKehadiranSik::class, 'anggota_periode_sik_id');
    }

    public function kelulusan(): HasOne
    {
        return $this->hasOne(KelulusanSik::class, 'anggota_periode_sik_id');
    }
}
