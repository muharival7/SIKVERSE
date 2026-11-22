<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\KelulusanSik;
use App\Models\SertifikatSik;
use App\Services\AttendanceRecapService;
use App\Services\GraduationDecisionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SertifikatSikController extends Controller
{
    private const FROZEN_MESSAGE = 'Sertifikat yang sudah diterbitkan bersifat final dan tidak dapat diubah atau dihapus.';

    public function __construct(
        private readonly AttendanceRecapService $attendanceRecapService,
        private readonly GraduationDecisionService $graduationDecisionService
    ) {}

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
        $kelulusan = $this->eligibleGraduations();

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
        $this->abortFrozenCertificate();
    }

    public function update(Request $request, SertifikatSik $sertifikatSik): RedirectResponse
    {
        $this->abortFrozenCertificate();
    }

    public function destroy(SertifikatSik $sertifikatSik): RedirectResponse
    {
        $this->abortFrozenCertificate();
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

    public function preview(SertifikatSik $sertifikatSik)
    {
        abort_unless(
            Storage::disk('local')->exists($sertifikatSik->file_sertifikat),
            404
        );

        return response()->file(
            Storage::disk('local')->path($sertifikatSik->file_sertifikat),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.basename($sertifikatSik->file_sertifikat).'"',
            ]
        );
    }

    /**
     * Catatan kelulusan yang layak mendapat sertifikat ditentukan oleh
     * keputusan Admin terbaru, bukan oleh persentase kehadiran.
     *
     * @return Collection<int, KelulusanSik>
     */
    private function eligibleGraduations(): Collection
    {
        return KelulusanSik::query()
            ->with([
                'anggotaPeriodeSik.santri',
                'anggotaPeriodeSik.periode',
                'sertifikat',
            ])
            ->latest()
            ->orderByDesc('id')
            ->get()
            ->map(fn (KelulusanSik $kelulusan): KelulusanSik => $this->decorateGraduation($kelulusan))
            ->filter(fn (KelulusanSik $kelulusan): bool => $this->graduationDecisionService
                ->isLatestDecisionPassed((int) $kelulusan->id)
                && $kelulusan->sertifikat === null)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'kelulusan_sik_id' => [
                'required',
                Rule::exists('kelulusan_sik', 'id'),
                Rule::unique('sertifikat_sik', 'kelulusan_sik_id'),
            ],
            'nomor_sertifikat' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sertifikat_sik', 'nomor_sertifikat'),
            ],
            'file_sertifikat' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'tanggal_terbit' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string'],
        ]);

        if (! $this->graduationDecisionService->isLatestDecisionPassed((int) $validated['kelulusan_sik_id'])) {
            throw ValidationException::withMessages([
                'kelulusan_sik_id' => 'Sertifikat hanya dapat diterbitkan setelah Admin membuat keputusan kelulusan terbaru dengan status Lulus.',
            ]);
        }

        return $validated;
    }

    private function abortFrozenCertificate(): never
    {
        abort(403, self::FROZEN_MESSAGE);
    }

    private function decorateGraduation(KelulusanSik $kelulusan): KelulusanSik
    {
        $evaluasi = $this->attendanceRecapService->graduationEvaluation(
            $kelulusan,
            $kelulusan->anggotaPeriodeSik
        );

        $kelulusan->setAttribute('evaluasi', $evaluasi);
        $kelulusan->setAttribute('evaluasi_label', $evaluasi['label']);
        $kelulusan->setAttribute('alasan_kehadiran', $evaluasi['alasan']);
        $kelulusan->setAttribute('attendance', $evaluasi['metric']);

        return $kelulusan;
    }
}
