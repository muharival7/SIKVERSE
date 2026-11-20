@extends('layouts.app')

@php
    use App\Services\AttendanceRecapService;
@endphp

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1">Rekap Kehadiran Santri</h1>
            @if ($selectedInstructor || ! $isAdmin)
                <p class="text-muted mb-0">
                    @if ($selectedInstructor)
                        Rekap kelompok yang diampu oleh {{ $selectedInstructor->user?->name ?? $selectedInstructor->nama }}.
                    @else
                        Rekap kelompok yang Anda ampu.
                    @endif
                    Ambang kehadiran: {{ $minimumPercentageDisplay }} dari pertemuan Terlaksana.
                </p>
            @endif
        </div>
        <div class="d-flex flex-wrap align-items-end gap-2">
            <form method="GET" action="{{ route('rekap-kehadiran-sik.index') }}" class="d-flex flex-wrap align-items-end gap-2">
                @if ($isAdmin)
                    <label for="instruktur-filter" class="small text-muted">Instruktur
                        <select id="instruktur-filter" name="instruktur_id" class="form-select mt-1">
                            <option value="">Semua instruktur</option>
                            @foreach ($instructors as $instruktur)
                                <option value="{{ $instruktur->id }}" @selected((int) ($filterQuery['instruktur_id'] ?? 0) === (int) $instruktur->id)>{{ $instruktur->user?->name ?? $instruktur->nama }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label for="periode-sik-filter" class="small text-muted">Periode
                    <select id="periode-sik-filter" name="periode_sik_id" class="form-select mt-1">
                        <option value="">Semua periode</option>
                        @foreach ($periods as $periode)
                            <option value="{{ $periode->id }}" @selected($selectedPeriodId === (int) $periode->id)>{{ $periode->nama_periode }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="kelompok-filter" class="small text-muted">Kelompok
                    <select id="kelompok-filter" name="kelompok_id" class="form-select mt-1">
                        <option value="">Semua kelompok</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected($selectedGroupId === (int) $group->id)>{{ $group->nama_kelompok }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn btn-outline-secondary" type="submit">Tampilkan</button>
            </form>
            <a class="btn btn-outline-danger" href="{{ route('rekap-kehadiran-sik.export', $filterQuery) }}">Export Excel</a>
        </div>
    </div>

    {{-- Ringkasan --}}
    @php
        $totalSantri = $rekap->pluck('anggota_periode_sik_id')->unique()->count();
        $totalRekap = $rekap->count();
    @endphp

    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Santri Aktif</div>
                    <h3 class="fw-bold text-primary mb-0">{{ $totalSantri }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Baris Rekap</div>
                    <h3 class="fw-bold text-success mb-0">{{ $totalRekap }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Rata-rata Kehadiran</div>
                    <h3 class="fw-bold text-info mb-0">{{ $rataKehadiranDisplay }}</h3>
                </div>
            </div>
        </div>

    </div>

    {{-- Tabel Rekap --}}
    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0">Daftar Rekap Kehadiran</h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">

                <table class="table table-hover table-bordered align-middle mb-0">

                    <thead class="table-dark">
                        <tr>
                            <th class="text-center">No</th>
                            <th>Nama Santri</th>
                            <th>Nomor Induk</th>
                            <th>Kelompok</th>
                            <th class="text-center">Total Pertemuan Terlaksana</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($rekap as $item)
                            @php
                                $namaSantri = $item->anggotaPeriode?->santri?->nama ?? '-';
                                $nomorInduk = $item->anggotaPeriode?->santri?->nomor_induk ?? '-';
                                $namaKelompok = $item->anggotaPeriode?->kelompok?->nama_kelompok
                                    ?? $item->anggotaPeriode?->kelompok?->nama
                                    ?? '-';
                                $status = $item->status_kehadiran ?? '-';
                                $persentase = (string) $item->persentase_display;
                                $badge = match ($status) {
                                    AttendanceRecapService::STATUS_MET => 'bg-success',
                                    AttendanceRecapService::STATUS_UNMET => 'bg-warning text-dark',
                                    AttendanceRecapService::STATUS_NO_ATTENDANCE => 'bg-info text-dark',
                                    AttendanceRecapService::STATUS_NO_MEETINGS => 'bg-secondary',
                                    default => 'bg-secondary',
                                };
                                $detailParams = array_filter([
                                    'anggota_periode_sik_id' => $item->anggota_periode_sik_id,
                                    'kegiatan_id' => $item->kegiatan_id,
                                    'kegiatan_ids' => $item->kegiatan_ids,
                                ] + $filterQuery, fn ($value): bool => $value !== null && $value !== '' && $value !== []);
                            @endphp

                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td class="fw-semibold">
                                    {{ $namaSantri }}
                                    @if ($item->kegiatan?->nama_kegiatan)
                                        <div class="small fw-normal text-muted">{{ $item->kegiatan->nama_kegiatan }} · {{ $item->anggotaPeriode?->periode?->nama_periode ?? '-' }}</div>
                                    @endif
                                </td>
                                <td>{{ $nomorInduk }}</td>
                                <td>{{ $namaKelompok }}</td>
                                <td class="text-center">{{ $item->total_pertemuan ?? 0 }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $badge }}">{{ $status }}</span>
                                    @if ($persentase !== $status)
                                        <div class="small text-muted mt-1">{{ $persentase }}</div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="{{ route('rekap-kehadiran-sik.detail', $detailParams) }}">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <h5 class="fw-bold text-muted">Belum Ada Anggota Aktif</h5>
                                    <p class="text-muted mb-0">Tidak ada anggota aktif pada filter yang dipilih.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>

            </div>
        </div>

    </div>

</div>
@endsection
