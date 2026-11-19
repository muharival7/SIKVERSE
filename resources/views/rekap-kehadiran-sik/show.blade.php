@extends('layouts.app')

@php
    use App\Services\AttendanceRecapService;
@endphp

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small"
                  style="color:#760d12;letter-spacing:.14em">
                Detail kehadiran santri
            </span>
            <h1 class="h2 fw-bold mt-1 mb-1">
                {{ $item->anggotaPeriode?->santri?->nama ?? 'Santri' }}
            </h1>
            <p class="text-muted mb-0">
                Nomor induk: {{ $item->anggotaPeriode?->santri?->nomor_induk ?? '—' }}
                · Kelompok: {{ $item->anggotaPeriode?->kelompok?->nama_kelompok ?? $item->anggotaPeriode?->kelompok?->nama ?? '—' }}
            </p>
        </div>

        <a class="btn btn-outline-secondary"
           href="{{ route('rekap-kehadiran-sik.index', $backQuery) }}">
            Kembali ke rekap
        </a>
    </div>

    {{-- Identitas dan konteks --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">Informasi kehadiran</h2>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Nama santri</div>
                    <div class="fw-semibold">
                        {{ $item->anggotaPeriode?->santri?->nama ?? '—' }}
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Nomor induk</div>
                    <div class="fw-semibold">
                        {{ $item->anggotaPeriode?->santri?->nomor_induk ?? '—' }}
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Kelompok</div>
                    <div class="fw-semibold">
                        {{ $item->anggotaPeriode?->kelompok?->nama_kelompok ?? $item->anggotaPeriode?->kelompok?->nama ?? '—' }}
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Periode SIK</div>
                    <div class="fw-semibold">
                        {{ $item->anggotaPeriode?->periode?->nama_periode ?? '—' }}
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Nama kegiatan</div>
                    <div class="fw-semibold">
                        {{ $item->kegiatan?->nama_kegiatan ?? 'Seluruh kegiatan periode' }}
                    </div>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <div class="text-muted small">Status pemenuhan batas kehadiran</div>
                    @php
                        $statusKehadiran = (string) ($item->status_kehadiran ?? AttendanceRecapService::STATUS_NO_MEETINGS);
                        $badge = match ($statusKehadiran) {
                            AttendanceRecapService::STATUS_MET => 'text-bg-success',
                            AttendanceRecapService::STATUS_UNMET => 'text-bg-warning',
                            AttendanceRecapService::STATUS_NO_ATTENDANCE => 'text-bg-info',
                            AttendanceRecapService::STATUS_NO_MEETINGS => 'text-bg-secondary',
                            default => 'text-bg-secondary',
                        };
                    @endphp
                    <div>
                        <span class="badge rounded-pill {{ $badge }}">{{ $statusKehadiran }}</span>
                        <div class="small text-muted mt-1">Ambang {{ $minimumPercentageDisplay }} dari pertemuan Terlaksana.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Ringkasan angka --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['Total Hadir', $item->jumlah_hadir],
            ['Total Izin', $item->jumlah_izin],
            ['Total Sakit', $item->jumlah_sakit],
            ['Total Alpha', $item->jumlah_alpha],
            ['Total Tidak Lengkap', $item->jumlah_tidak_lengkap],
            ['Total pertemuan Terlaksana', $item->total_pertemuan],
            ['Persentase kehadiran', $item->persentase_display],
        ] as [$label, $value])
            <div class="col-6 col-md-4 col-xl-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-4 fw-bold" style="color:#760d12">
                            {{ is_numeric($value) ? number_format($value) : $value }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Riwayat pertemuan --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">Daftar pertemuan dan status kehadiran</h2>
            <p class="small text-muted mb-0">
                Pertemuan Terjadwal tetap ditampilkan sebagai informasi dan tidak dihitung dalam persentase.
            </p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Tanggal pertemuan</th>
                        <th>Pertemuan ke</th>
                        <th>Kegiatan</th>
                        <th>Status kehadiran</th>
                        <th>Status pertemuan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($riwayat as $pertemuan)
                        @php
                            $absensiAnggota = $pertemuan->getAttribute('absensi_anggota');
                            $statusAbsensi = $absensiAnggota?->status;
                            $badgeAbsensi = match ($statusAbsensi) {
                                'Hadir' => 'text-bg-success',
                                'Alpha' => 'text-bg-danger',
                                null => 'text-bg-secondary',
                                default => 'text-bg-warning',
                            };
                            $badgePertemuan = $pertemuan->status === 'Terlaksana'
                                ? 'text-bg-success'
                                : 'text-bg-secondary';
                        @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $pertemuan->tanggal?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $pertemuan->pertemuan_ke ?? '—' }}</td>
                            <td>{{ $pertemuan->kegiatan?->nama_kegiatan ?? '—' }}</td>
                            <td>
                                <span class="badge rounded-pill {{ $badgeAbsensi }}">
                                    {{ $statusAbsensi ?? 'Belum ada catatan kehadiran' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge rounded-pill {{ $badgePertemuan }}">
                                    {{ $pertemuan->status ?? '—' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                Belum ada pertemuan pada kegiatan ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
