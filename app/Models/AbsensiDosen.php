<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsensiDosen extends Model
{
    protected $table = 'absensi_dosen';

    protected $fillable = [
        'infrastruktur_sik_id',
        'tanggal',
        'waktu_masuk',
        'waktu_pulang',
        'status',
        'keterangan',
        'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function instruktur(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturSik::class, 'infrastruktur_sik_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
