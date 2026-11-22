<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Santri extends Model
{
    protected $table = 'santris';

    protected $fillable = [
        'user_id',
        'nomor_induk',
        'nama',
        'program_studi',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'alamat',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fingerprint(): HasOne
    {
        return $this->hasOne(Fingerprint::class);
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class);
    }

    public function anggotaPeriode(): HasMany
    {
        return $this->hasMany(AnggotaPeriodeSik::class, 'santri_id')
            ->orderByDesc('tanggal_masuk')
            ->orderByDesc('id');
    }

    public function anggotaPeriodeTerbaru(): HasOne
    {
        return $this->hasOne(AnggotaPeriodeSik::class, 'santri_id')
            ->orderByDesc('tanggal_masuk')
            ->orderByDesc('id');
    }

    public function kelulusan(): HasOne
    {
        return $this->hasOne(Kelulusan::class);
    }

    public function sertifikat(): HasMany
    {
        return $this->hasMany(Sertifikat::class);
    }

    public function rekapKehadiran(): HasMany
    {
        return $this->hasMany(RekapKehadiran::class);
    }
}
