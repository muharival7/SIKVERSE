<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeSik extends Model
{
    protected $table = 'periode_sik';

    protected $fillable = [
        'nama_periode',
        'tahun',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function kelompok(): HasMany
    {
        return $this->hasMany(Kelompok::class, 'periode_sik_id');
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(AnggotaPeriodeSik::class, 'periode_sik_id');
    }

    public function kegiatan(): HasMany
    {
        return $this->hasMany(Kegiatan::class, 'periode_sik_id');
    }
}
