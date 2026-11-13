<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fingerprint extends Model
{
    protected $table = 'fingerprints';

    protected $fillable = [
        'santri_id',
        'fingerprint_id',
        'tanggal_pendaftaran',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pendaftaran' => 'date',
        ];
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }

    public function logPenggunaan(): HasMany
    {
        return $this->hasMany(LogPenggunaanFingerprint::class);
    }
}
