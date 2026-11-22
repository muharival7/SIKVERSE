<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKeputusanKelulusan extends Model
{
    protected $table = 'riwayat_keputusan_kelulusan';

    protected $fillable = [
        'kelulusan_sik_id',
        'actor_user_id',
        'status_sebelumnya',
        'status_sesudahnya',
        'alasan',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
