<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sertifikat extends Model
{
    protected $table = 'sertifikat';

    protected $fillable = [
        'santri_id',
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

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }
}
