<?php

namespace App\Http\Controllers;

use App\Models\AbsensiDosen;
use App\Models\InfrastrukturSik;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RekapAbsensiDosenController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
        ]);
        $month = $validated['bulan'] ?? now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $query = AbsensiDosen::query()
            ->with('instruktur')
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->latest('tanggal')
            ->latest('id');
        $absensi = $query->paginate(15)->withQueryString();
        $statusCounts = AbsensiDosen::query()
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('rekap-absensi-dosen.index', [
            'absensi' => $absensi,
            'instruktur' => InfrastrukturSik::query()
                ->where('status', 'Aktif')
                ->orderBy('nama')
                ->get(),
            'bulan' => $month,
            'totalCatatan' => $statusCounts->sum(),
            'hadir' => (int) $statusCounts->get('Hadir', 0),
            'izin' => (int) $statusCounts->get('Izin', 0),
            'sakit' => (int) $statusCounts->get('Sakit', 0),
            'alpha' => (int) $statusCounts->get('Alpha', 0),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'infrastruktur_sik_id' => [
                'required',
                Rule::exists('infrastruktur_siks', 'id')->where('status', 'Aktif'),
            ],
            'tanggal' => ['required', 'date'],
            'waktu_masuk' => ['nullable', 'date_format:H:i'],
            'waktu_pulang' => ['nullable', 'date_format:H:i', 'after_or_equal:waktu_masuk'],
            'status' => ['required', Rule::in(['Hadir', 'Izin', 'Sakit', 'Alpha'])],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $alreadyRecorded = AbsensiDosen::query()
            ->where('infrastruktur_sik_id', $validated['infrastruktur_sik_id'])
            ->whereDate('tanggal', $validated['tanggal'])
            ->exists();

        if ($alreadyRecorded) {
            return back()->withInput()->withErrors([
                'tanggal' => 'Absensi dosen ini sudah dicatat untuk tanggal tersebut.',
            ]);
        }

        try {
            AbsensiDosen::create([
                ...$validated,
                'dicatat_oleh' => $request->user()->id,
            ]);
        } catch (QueryException) {
            return back()->withInput()->withErrors([
                'tanggal' => 'Absensi dosen ini sudah tercatat. Muat ulang halaman lalu periksa rekapnya.',
            ]);
        }

        return redirect()->route('rekap-absensi-dosen.index', [
            'bulan' => Carbon::parse($validated['tanggal'])->format('Y-m'),
        ])->with('success', 'Absensi dosen berhasil dicatat.');
    }
}
