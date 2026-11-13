<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataPelajaran extends Model
{
    protected $table = 'mata_pelajaran';

    protected $fillable = [
        'kode_mapel',
        'nama_mapel',
        'sks',
        'semester',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sks' => 'integer',
            'semester' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class);
    }
}
