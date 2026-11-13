<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\InfrastrukturSik;
use App\Models\KelulusanSik;
use App\Models\RekapKehadiranSik;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RekapKehadiranSikController extends Controller
{
    /**
     * Menampilkan rekap kehadiran santri untuk instruktur SIK.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $isAdmin = $user->role === UserRole::Admin;
        $instruktur = $isAdmin ? null : $user->infrastrukturSik;
        $selectedInstructor = null;

        if (! $isAdmin && ! ($instruktur instanceof InfrastrukturSik)) {
            abort(403, 'Data instruktur tidak ditemukan.');
        }

        $rekapQuery = RekapKehadiranSik::with([
            'anggotaPeriode.santri',
            'anggotaPeriode.kelompok',
            'kegiatan',
        ])
            ->latest();

        if ($instruktur instanceof InfrastrukturSik) {
            $rekapQuery->whereHas('anggotaPeriode.kelompok', function ($query) use ($instruktur): void {
                $query->where('instruktur_id', $instruktur->id);
            });
        } elseif ($isAdmin && $request->filled('instruktur_id')) {
            $selectedInstructor = InfrastrukturSik::with('user')->findOrFail($request->integer('instruktur_id'));
            $rekapQuery->whereHas('anggotaPeriode.kelompok', function ($query) use ($selectedInstructor): void {
                $query->where('instruktur_id', $selectedInstructor->id);
            });
        }

        $rekap = $rekapQuery->get();

        $totalPertemuan = (int) config('sik.attendance.meeting_count', 16);
        $minimumHadir = (int) config('sik.attendance.minimum_present', 14);

        $rekap->each(function (RekapKehadiranSik $item) use ($totalPertemuan, $minimumHadir): void {
            $jumlahHadir = min((int) $item->jumlah_hadir, $totalPertemuan);
            $item->setAttribute('total_pertemuan', $totalPertemuan);
            $item->setAttribute(
                'persentase_kehadiran',
                round(($jumlahHadir / $totalPertemuan) * 100, 2)
            );
            $item->setAttribute(
                'status_kehadiran',
                (int) $item->jumlah_hadir >= $minimumHadir ? 'Memenuhi' : 'Belum Memenuhi'
            );
        });

        return view('rekap-kehadiran-sik.index', compact('rekap', 'isAdmin', 'selectedInstructor'));
    }

    /**
     * Menampilkan laporan SIK untuk Ka-Lab Mutu.
     * Ka-Lab Mutu hanya memiliki akses melihat laporan.
     */
    public function laporan(): View
    {
        $kelulusanTahunan = KelulusanSik::query()
            ->join('anggota_periode_sik as anggota', 'anggota.id', '=', 'kelulusan_sik.anggota_periode_sik_id')
            ->join('periode_sik as periode', 'periode.id', '=', 'anggota.periode_sik_id')
            ->select('periode.tahun')
            ->selectRaw('COUNT(*) as total_kelulusan')
            ->selectRaw("SUM(CASE WHEN kelulusan_sik.status = 'Lulus' THEN 1 ELSE 0 END) as jumlah_lulus")
            ->selectRaw("SUM(CASE WHEN kelulusan_sik.status = 'Tidak Lulus' THEN 1 ELSE 0 END) as jumlah_tidak_lulus")
            ->selectRaw("SUM(CASE WHEN kelulusan_sik.status = 'Belum Lulus' THEN 1 ELSE 0 END) as jumlah_belum_ditetapkan")
            ->groupBy('periode.tahun')
            ->orderByDesc('periode.tahun')
            ->get();

        $totalPeserta = (int) $kelulusanTahunan->sum('total_kelulusan');
        $totalLulus = (int) $kelulusanTahunan->sum('jumlah_lulus');
        $persentaseKelulusan = $totalPeserta > 0
            ? round(($totalLulus / $totalPeserta) * 100, 2)
            : 0;

        return view('laporan-sik.index', compact(
            'kelulusanTahunan',
            'totalPeserta',
            'totalLulus',
            'persentaseKelulusan'
        ));
    }
}
