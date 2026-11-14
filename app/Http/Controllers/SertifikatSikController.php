<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\KelulusanSik;
use App\Models\SertifikatSik;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SertifikatSikController extends Controller
{
    public function index(): View
    {
        $sertifikat = SertifikatSik::with([
            'kelulusan.anggotaPeriodeSik.santri',
            'kelulusan.anggotaPeriodeSik.periode',
        ])->latest()->paginate(10);

        return view('sertifikat-sik.index', compact('sertifikat'));
    }

    public function create(): View
    {
        $kelulusan = $this->eligibleGraduations()->get();

        return view('sertifikat-sik.create', compact('kelulusan'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $filePath = $request->file('file_sertifikat')->store('sertifikat-sik', 'local');

        if (! is_string($filePath)) {
            return back()->withInput()->withErrors([
                'file_sertifikat' => 'Berkas sertifikat gagal disimpan. Silakan coba unggah kembali.',
            ]);
        }

        try {
            $validated['file_sertifikat'] = $filePath;
            SertifikatSik::create($validated);
        } catch (QueryException) {
            Storage::disk('local')->delete($filePath);

            return back()->withInput()->withErrors([
                'kelulusan_sik_id' => 'Sertifikat atau nomor ini sudah tercatat.',
            ]);
        }

        return redirect()->route('sertifikat-sik.index')
            ->with('success', 'Sertifikat berhasil disimpan.');
    }

    public function edit(SertifikatSik $sertifikatSik): View
    {
        $kelulusan = $this->eligibleGraduations($sertifikatSik)->get();

        return view('sertifikat-sik.edit', [
            'sertifikat' => $sertifikatSik,
            'kelulusan' => $kelulusan,
        ]);
    }

    public function update(Request $request, SertifikatSik $sertifikatSik): RedirectResponse
    {
        $validated = $this->validatedData($request, $sertifikatSik);
        $newFilePath = null;

        if ($request->hasFile('file_sertifikat')) {
            $storedFilePath = $request->file('file_sertifikat')->store('sertifikat-sik', 'local');

            if (! is_string($storedFilePath)) {
                return back()->withInput()->withErrors([
                    'file_sertifikat' => 'Berkas sertifikat gagal disimpan. Silakan coba unggah kembali.',
                ]);
            }

            $newFilePath = $storedFilePath;
        }
        $oldFilePath = $sertifikatSik->file_sertifikat;

        if ($newFilePath === null) {
            unset($validated['file_sertifikat']);
        }

        try {
            if ($newFilePath !== null) {
                $validated['file_sertifikat'] = $newFilePath;
            }

            $sertifikatSik->update($validated);
        } catch (QueryException) {
            if ($newFilePath !== null) {
                Storage::disk('local')->delete($newFilePath);
            }

            return back()->withInput()->withErrors([
                'kelulusan_sik_id' => 'Sertifikat atau nomor ini sudah tercatat.',
            ]);
        }

        if ($newFilePath !== null && $oldFilePath !== $newFilePath) {
            Storage::disk('local')->delete($oldFilePath);
        }

        return redirect()->route('sertifikat-sik.index')
            ->with('success', 'Sertifikat berhasil diperbarui.');
    }

    public function destroy(SertifikatSik $sertifikatSik): RedirectResponse
    {
        $filePath = $sertifikatSik->file_sertifikat;
        $sertifikatSik->delete();
        Storage::disk('local')->delete($filePath);

        return redirect()->route('sertifikat-sik.index')
            ->with('success', 'Sertifikat berhasil dihapus.');
    }

    public function sertifikatSantri(Request $request): View
    {
        $santri = $request->user()->santri;
        abort_unless($santri !== null, 403, 'Data santri tidak ditemukan untuk akun ini.');

        $sertifikat = SertifikatSik::with('kelulusan.anggotaPeriodeSik.periode')
            ->whereHas('kelulusan.anggotaPeriodeSik', fn ($query) => $query->where('santri_id', $santri->id))
            ->latest()
            ->get();

        return view('santris.sertifikat-saya', compact('sertifikat'));
    }

    public function download(Request $request, SertifikatSik $sertifikatSik): StreamedResponse
    {
        if ($request->user()->role === UserRole::Santri) {
            $belongsToSantri = $sertifikatSik->kelulusan()
                ->whereHas('anggotaPeriodeSik.santri', fn ($query) => $query->where('santris.user_id', $request->user()->id))
                ->exists();

            abort_unless($belongsToSantri, 403);
        }

        abort_unless(Storage::disk('local')->exists($sertifikatSik->file_sertifikat), 404);

        return Storage::disk('local')->download(
            $sertifikatSik->file_sertifikat,
            basename($sertifikatSik->file_sertifikat)
        );
    }

    /**
     * @return Builder<KelulusanSik>
     */
    private function eligibleGraduations(?SertifikatSik $sertifikat = null): Builder
    {
        return KelulusanSik::with(['anggotaPeriodeSik.santri', 'anggotaPeriodeSik.periode'])
            ->where('status', 'Lulus')
            ->meetsAttendanceThreshold()
            ->where(function ($query) use ($sertifikat): void {
                $query->whereDoesntHave('sertifikat');

                if ($sertifikat !== null) {
                    $query->orWhereKey($sertifikat->kelulusan_sik_id);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?SertifikatSik $sertifikat = null): array
    {
        $validated = $request->validate([
            'kelulusan_sik_id' => [
                'required',
                Rule::exists('kelulusan_sik', 'id')->where('status', 'Lulus'),
                Rule::unique('sertifikat_sik', 'kelulusan_sik_id')->ignore($sertifikat?->id),
            ],
            'nomor_sertifikat' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sertifikat_sik', 'nomor_sertifikat')->ignore($sertifikat?->id),
            ],
            'file_sertifikat' => $sertifikat === null
                ? ['required', 'file', 'mimes:pdf', 'max:10240']
                : ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'tanggal_terbit' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $isEligible = KelulusanSik::query()
            ->whereKey($validated['kelulusan_sik_id'])
            ->where('status', 'Lulus')
            ->meetsAttendanceThreshold()
            ->exists();

        if (! $isEligible) {
            throw ValidationException::withMessages([
                'kelulusan_sik_id' => 'Sertifikat hanya dapat diterbitkan untuk santri yang memenuhi ambang kehadiran.',
            ]);
        }

        return $validated;
    }
}
