<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SertifikatSik extends Model
{
    protected $table = 'sertifikat_sik';

    protected $fillable = [
        'kelulusan_sik_id',
        'nomor_sertifikat',
        'file_sertifikat',
        'tanggal_terbit',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_terbit' => 'date',
        ];
    }

    public function kelulusan(): BelongsTo
    {
        return $this->belongsTo(KelulusanSik::class, 'kelulusan_sik_id');
    }
}
