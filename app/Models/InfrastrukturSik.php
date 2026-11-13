<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InfrastrukturSik extends Model
{
    protected $table = 'infrastruktur_siks';

    protected $fillable = [
        'user_id',
        'nama',
        'nip',
        'program_studi',
        'email',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class);
    }

    public function kelompok(): HasMany
    {
        return $this->hasMany(Kelompok::class, 'instruktur_id');
    }

    public function absensiDosen(): HasMany
    {
        return $this->hasMany(AbsensiDosen::class, 'infrastruktur_sik_id');
    }

    public function pertemuanPengganti(): HasMany
    {
        return $this->hasMany(Pertemuan::class, 'instruktur_pengganti_id');
    }
}
