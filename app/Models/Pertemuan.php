<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pertemuan extends Model
{
    protected $table = 'pertemuan';

    protected $fillable = [
        'kegiatan_id',
        'pertemuan_ke',
        'tanggal',
        'instruktur_pengganti_id',
        'kelompok_host_id',
        'waktu_mulai',
        'waktu_selesai',
        'status',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'kegiatan_id');
    }

    public function instrukturPengganti(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturSik::class, 'instruktur_pengganti_id');
    }

    public function kelompokHost(): BelongsTo
    {
        return $this->belongsTo(Kelompok::class, 'kelompok_host_id');
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(AbsensiSik::class, 'pertemuan_id');
    }
}
