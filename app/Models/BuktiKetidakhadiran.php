<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuktiKetidakhadiran extends Model
{
    protected $table = 'bukti_ketidakhadiran';

    protected $fillable = [
        'absensi_sik_id',
        'jenis_bukti',
        'file_bukti',
        'status_verifikasi',
        'diperiksa_oleh',
        'tanggal_pemeriksaan',
        'catatan_pemeriksaan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pemeriksaan' => 'datetime',
        ];
    }

    public function absensi(): BelongsTo
    {
        return $this->belongsTo(AbsensiSik::class, 'absensi_sik_id');
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(InfrastrukturSik::class, 'diperiksa_oleh');
    }
}
