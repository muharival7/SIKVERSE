<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\BuktiKetidakhadiran;
use App\Models\InfrastrukturSik;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuktiKetidakhadiranController extends Controller
{
    public function index(Request $request): View
    {
        $isAdmin = $request->user()->role === UserRole::Admin;
        $instruktur = $isAdmin ? null : $this->currentInstructor($request);

        $buktiQuery = BuktiKetidakhadiran::with([
            'absensi.anggotaPeriode.santri',
            'absensi.pertemuan.kegiatan',
            'pemeriksa',
        ])->latest();

        if ($instruktur instanceof InfrastrukturSik) {
            $this->scopeToInstructor($buktiQuery, $instruktur);
        }

        return view('bukti-ketidakhadiran.index', [
            'bukti' => $buktiQuery->paginate(10),
            'isAdmin' => $isAdmin,
        ]);
    }

    public function store(Request $request, AbsensiSik $absensiSik): RedirectResponse
    {
        $santri = $request->user()->santri;
        abort_unless($santri !== null, 403, 'Data santri tidak ditemukan untuk akun ini.');

        $absensiSik->load('anggotaPeriode');
        abort_unless((int) $absensiSik->anggotaPeriode?->santri_id === (int) $santri->id, 403);

        if (! in_array($absensiSik->status, ['Izin', 'Sakit'], true)) {
            throw ValidationException::withMessages([
                'jenis_bukti' => 'Bukti hanya dapat dikirim untuk absensi berstatus Izin atau Sakit.',
            ]);
        }

        $validated = $request->validate([
            'jenis_bukti' => ['required', Rule::in(['Izin', 'Sakit'])],
            'file_bukti' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        if ($validated['jenis_bukti'] !== $absensiSik->status) {
            throw ValidationException::withMessages([
                'jenis_bukti' => 'Jenis bukti harus sama dengan status absensi.',
            ]);
        }

        $existing = $absensiSik->buktiKetidakhadiran()->latest()->first();

        if ($existing !== null && $existing->status_verifikasi !== 'Ditolak') {
            return back()->withErrors([
                'file_bukti' => 'Bukti yang masih menunggu atau sudah diterima tidak dapat diganti.',
            ]);
        }

        $newFilePath = $request->file('file_bukti')->store('bukti-ketidakhadiran', 'local');
        $oldFilePath = $existing?->file_bukti;

        try {
            DB::transaction(function () use ($absensiSik, $existing, $validated, $newFilePath): void {
                $data = [
                    'jenis_bukti' => $validated['jenis_bukti'],
                    'file_bukti' => $newFilePath,
                    'status_verifikasi' => 'Menunggu',
                    'diperiksa_oleh' => null,
                    'tanggal_pemeriksaan' => null,
                    'catatan_pemeriksaan' => null,
                ];

                if ($existing !== null) {
                    $existing->update($data);
                } else {
                    $absensiSik->buktiKetidakhadiran()->create($data);
                }
            });
        } catch (QueryException $exception) {
            Storage::disk('local')->delete($newFilePath);

            throw $exception;
        }

        if ($oldFilePath !== null) {
            Storage::disk('local')->delete($oldFilePath);
        }

        return back()->with('success', 'Bukti ketidakhadiran berhasil dikirim untuk diperiksa.');
    }

    public function update(Request $request, BuktiKetidakhadiran $buktiKetidakhadiran): RedirectResponse
    {
        $isAdmin = $request->user()->role === UserRole::Admin;
        $instruktur = $isAdmin ? null : $this->currentInstructor($request);

        $buktiKetidakhadiran->load('absensi.anggotaPeriode.kelompok', 'absensi.pertemuan.kelompokHost');
        $this->authorizeReview($buktiKetidakhadiran, $isAdmin, $instruktur);

        if ($buktiKetidakhadiran->status_verifikasi !== 'Menunggu') {
            return back()->withErrors([
                'status_verifikasi' => 'Bukti ini sudah diperiksa.',
            ]);
        }

        $validated = $request->validate([
            'status_verifikasi' => ['required', Rule::in(['Diterima', 'Ditolak'])],
            'catatan_pemeriksaan' => $request->input('status_verifikasi') === 'Ditolak'
                ? ['required', 'string', 'max:1000']
                : ['nullable', 'string', 'max:1000'],
        ]);

        $buktiKetidakhadiran->update([
            ...$validated,
            'diperiksa_oleh' => $instruktur?->id,
            'tanggal_pemeriksaan' => now(),
        ]);

        return back()->with('success', 'Status bukti ketidakhadiran berhasil diperbarui.');
    }

    public function download(Request $request, BuktiKetidakhadiran $buktiKetidakhadiran): StreamedResponse
    {
        $isAdmin = $request->user()->role === UserRole::Admin;
        $buktiKetidakhadiran->load('absensi.anggotaPeriode.santri', 'absensi.anggotaPeriode.kelompok', 'absensi.pertemuan.kelompokHost');

        if ($request->user()->role === UserRole::Santri) {
            abort_unless(
                (int) $buktiKetidakhadiran->absensi?->anggotaPeriode?->santri?->user_id === (int) $request->user()->id,
                403
            );
        } elseif (! $isAdmin) {
            $instruktur = $this->currentInstructor($request);
            $this->authorizeReview($buktiKetidakhadiran, false, $instruktur);
        }

        abort_unless(Storage::disk('local')->exists($buktiKetidakhadiran->file_bukti), 404);

        return Storage::disk('local')->download(
            $buktiKetidakhadiran->file_bukti,
            basename($buktiKetidakhadiran->file_bukti)
        );
    }

    private function currentInstructor(Request $request): InfrastrukturSik
    {
        $instruktur = $request->user()->infrastrukturSik;
        abort_unless($instruktur instanceof InfrastrukturSik, 403);

        return $instruktur;
    }

    private function scopeToInstructor(Builder $query, InfrastrukturSik $instruktur): void
    {
        $query->whereHas('absensi', function (Builder $absensiQuery) use ($instruktur): void {
            $absensiQuery->whereHas(
                'anggotaPeriode.kelompok',
                fn (Builder $groupQuery) => $groupQuery->where('instruktur_id', $instruktur->id)
            )->orWhereHas('pertemuan', function (Builder $meetingQuery) use ($instruktur): void {
                $meetingQuery->where('instruktur_pengganti_id', $instruktur->id)
                    ->orWhereHas(
                        'kelompokHost',
                        fn (Builder $groupQuery) => $groupQuery->where('instruktur_id', $instruktur->id)
                    )
                    ->orWhereHas(
                        'kegiatan.kelompok',
                        fn (Builder $groupQuery) => $groupQuery->where('instruktur_id', $instruktur->id)
                    );
            });
        });
    }

    private function authorizeReview(
        BuktiKetidakhadiran $bukti,
        bool $isAdmin,
        ?InfrastrukturSik $instruktur
    ): void {
        if ($isAdmin) {
            return;
        }

        $absensi = $bukti->absensi;
        $isAssigned = (int) $absensi?->anggotaPeriode?->kelompok?->instruktur_id === (int) $instruktur?->id
            || (int) $absensi?->pertemuan?->kelompokHost?->instruktur_id === (int) $instruktur?->id
            || (int) $absensi?->pertemuan?->instruktur_pengganti_id === (int) $instruktur?->id;

        abort_unless($isAssigned, 403);
    }
}
