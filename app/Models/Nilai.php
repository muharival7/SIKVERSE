<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nilai extends Model
{
    protected $table = 'nilai';

    protected $fillable = [
        'santri_id',
        'mata_pelajaran_id',
        'infrastruktur_sik_id',
        'semester',
        'tahun_ajaran',
        'nilai',
        'keterangan',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function infrastrukturSik(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturSik::class);
    }
}
