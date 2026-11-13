<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Santri = 'santri';
    case InfrastrukturSik = 'infrastruktur_sik';
    case KaLabMutu = 'ka_lab_mutu';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Santri => 'Santri',
            self::InfrastrukturSik => 'Infrastruktur SIK',
            self::KaLabMutu => 'Ka-Lab Mutu',
        };
    }

    public function dashboardRoute(): string
    {
        return 'dashboard.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public function plannedModules(): array
    {
        return match ($this) {
            self::Admin => [
                'Pengguna dan peran',
                'Data santri',
                'Mata pelajaran dan jadwal',
                'Infrastruktur SIK',
                'Rekap dan laporan',
            ],
            self::Santri => [
                'Profil santri',
                'Jadwal pelajaran',
                'Absensi fingerprint',
                'Rekap kehadiran',
            ],
            self::InfrastrukturSik => [
                'Perangkat fingerprint',
                'Log penggunaan fingerprint',
                'Monitoring perangkat',
            ],
            self::KaLabMutu => [
                'Rekap kehadiran',
                'Laporan evaluasi',
                'Perbandingan laporan tahunan',
            ],
        };
    }
}
