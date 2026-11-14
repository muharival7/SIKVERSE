<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\BuktiKetidakhadiran;
use App\Models\InfrastrukturSik;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuktiKetidakhadiranController extends Controller
{
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

        return back()->with('success', 'Bukti ketidakhadiran berhasil dikirim dan disimpan pada absensi Anda.');
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
