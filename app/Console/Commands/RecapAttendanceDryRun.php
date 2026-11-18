<?php

namespace App\Console\Commands;

use App\Services\AttendanceRecapService;
use Illuminate\Console\Command;

/**
 * Simulasi perubahan aturan rekap membaca data saja. Perintah ini sengaja
 * tidak memanggil sync, tidak mengubah status kelulusan, dan tidak menyentuh
 * sertifikat maupun cache.
 */
class RecapAttendanceDryRun extends Command
{
    protected $signature = 'sik:recap-dry-run';

    protected $description = 'Simulasi read-only rekap kehadiran berdasarkan pertemuan Terlaksana dan ambang 87,5%';

    public function __construct(private readonly AttendanceRecapService $attendanceRecapService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $rows = $this->attendanceRecapService->dryRunRows();

        if ($rows === []) {
            $this->info('Tidak ada baris rekap kehadiran untuk disimulasikan.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Anggota', 'Kegiatan', 'Total saat ini', 'Hadir saat ini', 'Persentase saat ini', 'Status saat ini', 'Terlaksana', 'Hadir simulasi', 'Persentase simulasi', 'Status simulasi', 'Perubahan'],
            array_map(static function (array $row): array {
                return [
                    $row['rekap_id'],
                    $row['anggota_periode_sik_id'],
                    $row['kegiatan_id'],
                    $row['total_pertemuan_saat_ini'],
                    $row['jumlah_hadir_saat_ini'],
                    $row['persentase_saat_ini'],
                    $row['status_saat_ini'],
                    $row['total_terlaksana'],
                    $row['jumlah_hadir_simulasi'],
                    $row['persentase_simulasi'],
                    $row['status_simulasi'],
                    $row['perubahan'],
                ];
            }, $rows)
        );

        $terdampak = count(array_filter(
            $rows,
            static fn (array $row): bool => (int) $row['terdampak'] === 1
        ));

        $this->newLine();
        $this->info('Jumlah baris rekap : '.count($rows));
        $this->info('Baris terdampak    : '.$terdampak);
        $this->info('Baris tidak berubah: '.(count($rows) - $terdampak));
        $this->comment('Simulasi ini tidak menulis data, tidak mengubah status, dan tidak mengubah sertifikat.');

        return self::SUCCESS;
    }
}
