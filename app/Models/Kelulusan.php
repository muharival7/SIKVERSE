<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kelulusan extends Model
{
    protected $table = 'kelulusan';

    protected $fillable = [
        'santri_id',
        'status',
        'tahun_lulus',
        'tanggal_kelulusan',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tahun_lulus' => 'integer',
            'tanggal_kelulusan' => 'date',
        ];
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }
}
