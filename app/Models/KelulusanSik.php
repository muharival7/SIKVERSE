<?php

namespace App\Models;

use App\Services\AttendanceRecapService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KelulusanSik extends Model
{
    protected $table = 'kelulusan_sik';

    protected $fillable = [
        'anggota_periode_sik_id',
        'status',
        'tanggal_kelulusan',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_kelulusan' => 'date',
        ];
    }

    // Relasi ke anggota periode SIK
    public function anggotaPeriode(): BelongsTo
    {
        return $this->belongsTo(
            AnggotaPeriodeSik::class,
            'anggota_periode_sik_id'
        );
    }

    // Alias relasi yang digunakan oleh controller
    public function anggotaPeriodeSik(): BelongsTo
    {
        return $this->belongsTo(
            AnggotaPeriodeSik::class,
            'anggota_periode_sik_id'
        );
    }

    // Relasi ke sertifikat
    public function sertifikat(): HasOne
    {
        return $this->hasOne(
            SertifikatSik::class,
            'kelulusan_sik_id'
        );
    }

    /**
     * Kelayakan kehadiran ditentukan sepenuhnya oleh AttendanceRecapService.
     *
     * Scope ini hanya membatasi baris pada anggota yang memenuhi syarat
     * kehadiran untuk mengikuti ujian. Ia tidak menentukan status kelulusan.
     * Service diambil dari container karena scope Eloquent tidak dapat
     * menerima constructor injection.
     */
    public function scopeMeetsAttendanceThreshold(Builder $query): Builder
    {
        $eligibleAnggotaIds = app(AttendanceRecapService::class)
            ->eligibleAnggotaIds()
            ->all();

        return $query->whereIn('anggota_periode_sik_id', $eligibleAnggotaIds);
    }
}
