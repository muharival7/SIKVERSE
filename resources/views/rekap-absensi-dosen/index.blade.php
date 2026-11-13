@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small" style="color:#760d12;letter-spacing:.12em">Administrasi dosen</span>
            <h1 class="h2 fw-bold mb-1">Rekap Absensi Dosen</h1>
            <p class="text-muted mb-0">Catat dan tinjau kehadiran dosen berdasarkan tanggal.</p>
        </div>
        <form method="GET" action="{{ route('rekap-absensi-dosen.index') }}" class="d-flex align-items-end gap-2">
            <label class="small text-muted" for="bulan">Bulan
                <input id="bulan" name="bulan" type="month" value="{{ $bulan }}" class="form-control mt-1">
            </label>
            <button type="submit" class="btn btn-outline-secondary">Tampilkan</button>
        </form>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row g-3 mb-4">
        @foreach ([['Total catatan', $totalCatatan, '#293b17'], ['Hadir', $hadir, '#267653'], ['Izin / sakit', $izin + $sakit, '#ac7a22'], ['Alpha', $alpha, '#a73840']] as [$label, $count, $color])
            <div class="col-6 col-xl-3"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><span class="text-muted">{{ $label }}</span><div class="fs-3 fw-bold" style="color:{{ $color }}">{{ number_format($count) }}</div></div></div></div>
        @endforeach
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
                        @foreach ($instruktur as $dosen)
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
                    <label for="waktu_masuk" class="form-label">Waktu masuk</label>
                    <input id="waktu_masuk" name="waktu_masuk" type="time" value="{{ old('waktu_masuk') }}" class="form-control">
                    @error('waktu_masuk')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-xl-2">
                    <label for="waktu_pulang" class="form-label">Waktu pulang</label>
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
        <div class="card-header bg-white py-3"><h2 class="h5 mb-0">Catatan bulan {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $bulan)->locale('id')->translatedFormat('F Y') }}</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Tanggal</th><th>Nama Dosen</th><th>NIP</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Keterangan</th></tr></thead>
                <tbody>
                    @forelse ($absensi as $catatan)
                        <tr>
                            <td>{{ $catatan->tanggal->format('d/m/Y') }}</td>
                            <td>{{ $catatan->instruktur?->nama ?? 'Dosen' }}</td>
                            <td>{{ $catatan->instruktur?->nip ?: '—' }}</td>
                            <td>{{ $catatan->waktu_masuk ?: '—' }}</td>
                            <td>{{ $catatan->waktu_pulang ?: '—' }}</td>
                            <td><span class="badge rounded-pill {{ $catatan->status === 'Hadir' ? 'text-bg-success' : ($catatan->status === 'Alpha' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $catatan->status }}</span></td>
                            <td>{{ $catatan->keterangan ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">Belum ada catatan absensi dosen untuk bulan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($absensi->hasPages())<div class="card-footer bg-white">{{ $absensi->links() }}</div>@endif
    </div>
</div>
@endsection
