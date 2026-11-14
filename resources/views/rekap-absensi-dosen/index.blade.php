@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small" style="color:#760d12;letter-spacing:.12em">Administrasi dosen</span>
            <h1 class="h2 fw-bold mb-1">Rekap Absensi Dosen</h1>
            <p class="text-muted mb-0">Rekap kehadiran dihitung dari catatan yang tersimpan di database.</p>
        </div>
        @if ($tableAvailable)
            <a class="btn btn-outline-danger" href="{{ route('rekap-absensi-dosen.export', array_filter($filters)) }}">Unduh CSV</a>
        @endif
    </div>

    @if (! $tableAvailable)
        <div class="alert alert-info" role="status">
            Tabel absensi dosen belum tersedia pada database ini. Jalankan migration absensi dosen setelah membuat backup database untuk mengaktifkan rekap.
        </div>
    @else
        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-warning">{{ session('error') }}</div>@endif

        <form method="GET" action="{{ route('rekap-absensi-dosen.index') }}" class="card border-0 shadow-sm mb-4">
            <div class="card-body row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="periode_sik_id">Periode</label>
                    <select id="periode_sik_id" name="periode_sik_id" class="form-select">
                        <option value="">Semua periode</option>
                        @foreach ($periods as $periode)
                            <option value="{{ $periode->id }}" @selected($filters['periode_sik_id'] === $periode->id)>{{ $periode->nama_periode }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label" for="filter-instruktur">Dosen</label>
                    <select id="filter-instruktur" name="infrastruktur_sik_id" class="form-select">
                        <option value="">Semua dosen</option>
                        @foreach ($instructors as $dosen)
                            <option value="{{ $dosen->id }}" @selected($filters['infrastruktur_sik_id'] === $dosen->id)>{{ $dosen->nama }}{{ $dosen->nip ? ' · '.$dosen->nip : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-danger flex-grow-1">Tampilkan</button>
                    <a href="{{ route('rekap-absensi-dosen.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="row g-3 mb-4">
            @foreach ([['Jumlah catatan', $totalMeetings, '#293b17'], ['Hadir', $totalPresent, '#267653'], ['Tidak hadir', $totalAbsent, '#ac7a22'], ['Persentase hadir', number_format($attendancePercentage, 1).'%', '#760d12']] as [$label, $value, $color])
                <div class="col-6 col-xl-3"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><span class="text-muted">{{ $label }}</span><div class="fs-3 fw-bold" style="color:{{ $color }}">{{ is_numeric($value) ? number_format($value) : $value }}</div></div></div></div>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">Ringkasan per dosen</h2>
                <p class="small text-muted mb-0">Jumlah pertemuan pada tabel ini adalah jumlah catatan absensi yang tersimpan dalam filter terpilih.</p>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Nama Dosen</th><th>NIP</th><th>Pertemuan</th><th>Hadir</th><th>Tidak hadir</th><th>Persentase</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @forelse ($profiles as $dosen)
                            <tr>
                                <td class="fw-semibold">{{ $dosen->nama ?: $dosen->user?->name ?: 'Dosen' }}</td>
                                <td>{{ $dosen->nip ?: '—' }}</td>
                                <td>{{ number_format($dosen->attendance_meetings) }}</td>
                                <td class="text-success fw-semibold">{{ number_format($dosen->attendance_present) }}</td>
                                <td class="text-danger">{{ number_format($dosen->attendance_absent) }}</td>
                                <td>{{ number_format($dosen->attendance_percentage, 1) }}%</td>
                                <td><a class="btn btn-sm btn-outline-danger" href="{{ route('rekap-absensi-dosen.show', [$dosen, ...array_filter(['periode_sik_id' => $filters['periode_sik_id']])]) }}">Detail riwayat</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data dosen.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3"><h2 class="h5 mb-0">Catat absensi dosen</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('rekap-absensi-dosen.store') }}" class="row g-3">
                    @csrf
                    <div class="col-12 col-md-6 col-xl-3">
                        <label for="infrastruktur_sik_id" class="form-label">Dosen *</label>
                        <select id="infrastruktur_sik_id" name="infrastruktur_sik_id" class="form-select" required>
                            <option value="">Pilih dosen</option>
                            @foreach ($activeInstructors as $dosen)
                                <option value="{{ $dosen->id }}" @selected(old('infrastruktur_sik_id') == $dosen->id)>{{ $dosen->nama }}{{ $dosen->nip ? ' · '.$dosen->nip : '' }}</option>
                            @endforeach
                        </select>
                        @error('infrastruktur_sik_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6 col-xl-2">
                        <label for="tanggal" class="form-label">Tanggal *</label>
                        <input id="tanggal" name="tanggal" type="date" value="{{ old('tanggal', now()->toDateString()) }}" class="form-control" required>
                        @error('tanggal')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-xl-2">
                        <label for="waktu_masuk" class="form-label">Jam masuk</label>
                        <input id="waktu_masuk" name="waktu_masuk" type="time" value="{{ old('waktu_masuk') }}" class="form-control">
                        @error('waktu_masuk')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6 col-xl-2">
                        <label for="waktu_pulang" class="form-label">Jam pulang</label>
                        <input id="waktu_pulang" name="waktu_pulang" type="time" value="{{ old('waktu_pulang') }}" class="form-control">
                        @error('waktu_pulang')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label for="status" class="form-label">Status *</label>
                        <select id="status" name="status" class="form-select" required>
                            @foreach (['Hadir', 'Izin', 'Sakit', 'Alpha'] as $status)
                                <option value="{{ $status }}" @selected(old('status', 'Hadir') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="keterangan" class="form-label">Keterangan</label>
                        <input id="keterangan" name="keterangan" type="text" maxlength="1000" value="{{ old('keterangan') }}" class="form-control" placeholder="Opsional">
                        @error('keterangan')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit">Simpan Absensi</button></div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3"><h2 class="h5 mb-0">Riwayat absensi dosen</h2></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Tanggal</th><th>Nama Dosen</th><th>NIP</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        @forelse ($absensi as $catatan)
                            <tr>
                                <td>{{ $catatan->tanggal->format('d/m/Y') }}</td>
                                <td>{{ $catatan->instruktur?->nama ?? $catatan->instruktur?->user?->name ?? 'Dosen' }}</td>
                                <td>{{ $catatan->instruktur?->nip ?: '—' }}</td>
                                <td>{{ $catatan->waktu_masuk ?: '—' }}</td>
                                <td>{{ $catatan->waktu_pulang ?: '—' }}</td>
                                <td><span class="badge rounded-pill {{ $catatan->status === 'Hadir' ? 'text-bg-success' : ($catatan->status === 'Alpha' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $catatan->status }}</span></td>
                                <td>{{ $catatan->keterangan ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5">Belum ada data kehadiran dosen untuk filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($absensi->hasPages())<div class="card-footer bg-white">{{ $absensi->links() }}</div>@endif
        </div>
    @endif
</div>
@endsection
