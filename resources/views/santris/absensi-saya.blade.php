
@extends('layouts.app')

@section('content')
<div class="container py-4">

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    {{-- Header --}}
    <div class="mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">Riwayat Absensi SIK</h2>
            <p class="text-muted mb-0">
                Riwayat kehadiran kegiatan SIK kamu.
            </p>
        </div>

    </div>

    {{-- Informasi Santri --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <small class="text-muted">Nama Santri</small>
                    <h5 class="fw-bold mb-1">
                        {{ $santri->nama }}
                    </h5>

                    <small class="text-muted">
                        Nomor Induk: {{ $santri->nomor_induk }}
                    </small>
                </div>

                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <small class="text-muted">Total Catatan Absensi</small>
                    <h3 class="fw-bold text-primary mb-0">
                        {{ $totalAbsensi }}
                    </h3>
                </div>
            </div>
        </div>
    </div>

    {{-- Rekap dan syarat kehadiran --}}
    <div class="mb-4">
        <h3 class="h5 fw-bold mb-3">Rekap dan Syarat Kehadiran</h3>
        <div class="row g-3">
            @forelse ($rekap as $item)
                @php
                    $statusKehadiran = $item->status_kehadiran;
                    $badgeKehadiran = $statusKehadiran === 'Memenuhi' ? 'bg-success' : 'bg-warning text-dark';
                @endphp
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <h4 class="h6 fw-bold mb-1">{{ $item->kegiatan?->nama_kegiatan ?? 'Kegiatan SIK' }}</h4>
                                    <div class="small text-muted">{{ $item->anggotaPeriode?->periode?->nama_periode ?? 'Periode SIK' }}</div>
                                </div>
                                <span class="badge {{ $badgeKehadiran }}">{{ $statusKehadiran }}</span>
                            </div>
                            <div class="mt-3">Hadir <strong>{{ $item->jumlah_hadir }} dari {{ $item->total_pertemuan }}</strong> pertemuan</div>
                            <div class="text-muted">{{ number_format((float) $item->persentase_kehadiran, 2) }}% · minimal {{ config('sik.attendance.minimum_present', 14) }} kali hadir</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="alert alert-info mb-0">Rekap kehadiran akan tampil setelah absensi pertama dicatat.</div></div>
            @endforelse
        </div>
    </div>

    {{-- Tabel Riwayat --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0">Daftar Riwayat Kehadiran</h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center">No</th>
                            <th>Tanggal</th>
                            <th>Kegiatan</th>
                            <th>Pertemuan</th>
                            <th>Waktu Masuk</th>
                            <th>Waktu Pulang</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                            <th>Bukti Izin/Sakit</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($absensi as $item)
                            @php
                                $status = $item->status;

                                $badgeClass = match ($status) {
                                    'Hadir' => 'bg-success',
                                    'Izin' => 'bg-info text-dark',
                                    'Sakit' => 'bg-warning text-dark',
                                    'Alpha' => 'bg-danger',
                                    'Tidak Lengkap' => 'bg-secondary',
                                    default => 'bg-secondary',
                                };

                                $tanggal = $item->pertemuan?->tanggal;

                                $namaKegiatan =
                                    $item->pertemuan?->kegiatan?->nama
                                    ?? $item->pertemuan?->kegiatan?->nama_kegiatan
                                    ?? $item->pertemuan?->kegiatan?->judul
                                    ?? 'Kegiatan SIK';
                            @endphp

                            <tr>
                                <td class="text-center">
                                    {{ $absensi->firstItem() + $loop->index }}
                                </td>

                                <td>
                                    {{ $tanggal ? $tanggal->format('d-m-Y') : '-' }}
                                </td>

                                <td>
                                    {{ $namaKegiatan }}
                                </td>

                                <td>
                                    Pertemuan
                                    {{ $item->pertemuan?->pertemuan_ke ?? '-' }}
                                </td>

                                <td>
                                    {{ $item->waktu_masuk ?? '-' }}
                                </td>

                                <td>
                                    {{ $item->waktu_pulang ?? '-' }}
                                </td>

                                <td>
                                    {{ $item->metode_absensi ?? '-' }}
                                </td>

                                <td>
                                    <span class="badge {{ $badgeClass }}">
                                        {{ $status }}
                                    </span>
                                </td>

                                <td>
                                    {{ $item->keterangan ?? '-' }}
                                </td>

                                <td style="min-width:220px">
                                    @php($proof = $item->buktiKetidakhadiran->sortByDesc('id')->first())
                                    @if ($proof)
                                        <div class="mb-2"><span class="badge {{ $proof->status_verifikasi === 'Diterima' ? 'bg-success' : ($proof->status_verifikasi === 'Ditolak' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $proof->status_verifikasi }}</span></div>
                                        <a class="btn btn-sm btn-outline-secondary mb-2" href="{{ route('bukti-ketidakhadiran.download', $proof) }}">Unduh Bukti</a>
                                        @if ($proof->status_verifikasi === 'Ditolak')<div class="small text-danger mb-2">{{ $proof->catatan_pemeriksaan }}</div>@endif
                                    @endif

                                    @if (in_array($status, ['Izin', 'Sakit'], true) && (! $proof || $proof->status_verifikasi === 'Ditolak'))
                                        <form action="{{ route('absensi-saya.bukti.store', $item) }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                                            @csrf
                                            <input type="hidden" name="jenis_bukti" value="{{ $status }}">
                                            <input class="form-control form-control-sm" type="file" name="file_bukti" accept=".pdf,.jpg,.jpeg,.png" aria-label="Unggah bukti {{ strtolower($status) }}" required>
                                            <button class="btn btn-sm btn-outline-primary" type="submit">{{ $proof ? 'Kirim Ulang Bukti' : 'Kirim Bukti' }}</button>
                                        </form>
                                        <div class="small text-muted mt-1">PDF/JPG/PNG, maksimal 10 MB.</div>
                                    @elseif (! $proof && ! in_array($status, ['Izin', 'Sakit'], true))
                                        —
                                    @endif
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <div class="text-muted">
                                        <h5 class="fw-bold">
                                            Belum Ada Riwayat Absensi
                                        </h5>

                                        <p class="mb-0">
                                            Data kehadiran kegiatan SIK kamu
                                            belum tersedia.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        @if ($absensi->hasPages())
            <div class="card-footer bg-white">
                {{ $absensi->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

</div>
@endsection
