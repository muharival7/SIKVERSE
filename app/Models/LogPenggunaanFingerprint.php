<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogPenggunaanFingerprint extends Model
{
    protected $table = 'log_penggunaan_fingerprint';

    protected $fillable = [
        'fingerprint_id',
        'waktu_scan',
        'status',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'waktu_scan' => 'datetime',
        ];
    }

    public function fingerprint(): BelongsTo
    {
        return $this->belongsTo(Fingerprint::class);
    }
}
