
@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">

    {{-- Header --}}
    <div class="mb-4">
        <div>
            <h2 class="fw-bold mb-1">{{ $selectedInstructor ? 'Rekap Absensi Dosen' : 'Rekap Kehadiran Santri' }}</h2>
            <p class="text-muted mb-0">
                @if ($selectedInstructor)
                    Menampilkan rekap kelompok yang diampu oleh {{ $selectedInstructor->user?->name ?? $selectedInstructor->nama }}.
                @elseif ($isAdmin)
                    Menampilkan rekap seluruh kelompok dan kegiatan SIK.
                @else
                    Menampilkan rekap kelompok yang Anda ampu.
                @endif
                Kehadiran dihitung dari {{ config('sik.attendance.meeting_count', 16) }} pertemuan; minimal {{ config('sik.attendance.minimum_present', 14) }} kali hadir untuk memenuhi syarat.
            </p>
        </div>

        @if ($selectedInstructor)
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary mt-2">Kembali ke Data Dosen</a>
        @endif
    </div>

    {{-- Ringkasan --}}
    @php
        $totalSantri = $rekap->pluck('anggota_periode_sik_id')->unique()->count();
        $totalRekap = $rekap->count();
        $rataKehadiran = $totalRekap > 0
            ? $rekap->avg('persentase_kehadiran')
            : 0;
    @endphp

    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Santri</div>
                    <h3 class="fw-bold text-primary mb-0">
                        {{ $totalSantri }}
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Data Rekap</div>
                    <h3 class="fw-bold text-success mb-0">
                        {{ $totalRekap }}
                    </h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Rata-rata Kehadiran</div>
                    <h3 class="fw-bold text-info mb-0">
                        {{ number_format($rataKehadiran, 2) }}%
                    </h3>
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
                            <th>Kegiatan</th>
                            <th class="text-center">Total Pertemuan</th>
                            <th class="text-center">Hadir</th>
                            <th class="text-center">Izin</th>
                            <th class="text-center">Sakit</th>
                            <th class="text-center">Alpha</th>
                            <th class="text-center">Tidak Lengkap</th>
                            <th class="text-center">Persentase</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($rekap as $item)

                            @php
                                $namaSantri = $item->anggotaPeriode?->santri?->nama ?? '-';

                                $nomorInduk = $item->anggotaPeriode?->santri?->nomor_induk ?? '-';

                                $namaKelompok = $item->anggotaPeriode?->kelompok?->nama
                                    ?? $item->anggotaPeriode?->kelompok?->nama_kelompok
                                    ?? '-';

                                $namaKegiatan = $item->kegiatan?->nama
                                    ?? $item->kegiatan?->nama_kegiatan
                                    ?? $item->kegiatan?->judul
                                    ?? '-';

                                $status = $item->status_kehadiran ?? '-';

                                $badge = match (strtolower($status)) {
                                    'lulus', 'memenuhi', 'memenuhi syarat' => 'bg-success',
                                    'belum memenuhi' => 'bg-warning text-dark',
                                    'tidak lulus', 'tidak memenuhi' => 'bg-danger',
                                    default => 'bg-secondary',
                                };
                            @endphp

                            <tr>
                                <td class="text-center">
                                    {{ $loop->iteration }}
                                </td>

                                <td class="fw-semibold">
                                    {{ $namaSantri }}
                                </td>

                                <td>{{ $nomorInduk }}</td>

                                <td>{{ $namaKelompok }}</td>

                                <td>{{ $namaKegiatan }}</td>

                                <td class="text-center">
                                    {{ $item->total_pertemuan ?? 0 }}
                                </td>

                                <td class="text-center text-success fw-semibold">
                                    {{ $item->jumlah_hadir ?? 0 }}
                                </td>

                                <td class="text-center">
                                    {{ $item->jumlah_izin ?? 0 }}
                                </td>

                                <td class="text-center">
                                    {{ $item->jumlah_sakit ?? 0 }}
                                </td>

                                <td class="text-center text-danger">
                                    {{ $item->jumlah_alpha ?? 0 }}
                                </td>

                                <td class="text-center">
                                    {{ $item->jumlah_tidak_lengkap ?? 0 }}
                                </td>

                                <td class="text-center fw-bold">
                                    {{ number_format((float) $item->persentase_kehadiran, 2) }}%
                                </td>

                                <td class="text-center">
                                    <span class="badge {{ $badge }}">
                                        {{ $status }}
                                    </span>
                                </td>
                            </tr>

                        @empty

                            <tr>
                                <td colspan="13" class="text-center py-5">
                                    <h5 class="fw-bold text-muted">
                                        Belum Ada Data Rekap
                                    </h5>

                                    <p class="text-muted mb-0">
                                        Data rekap kehadiran santri belum tersedia.
                                    </p>
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
