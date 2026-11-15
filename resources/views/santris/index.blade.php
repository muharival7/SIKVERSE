@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4 px-xl-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small" style="color:#760d12;letter-spacing:.14em">Pengelolaan peserta SIK</span>
            <h1 class="h2 fw-bold mt-1 mb-1">Data Santri</h1>
            <p class="text-muted mb-0">Kelola identitas, kelompok, periode, status, dan akun santri dalam satu halaman.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('kelompok.index') }}" class="btn btn-outline-secondary">Kelola Kelompok</a>
            <a href="{{ route('periode-sik.index') }}" class="btn btn-outline-secondary">Periode SIK</a>
            <a href="{{ route('santris.import') }}" class="btn btn-outline-danger"><x-sik-icon name="upload" class="me-1" /> Import Excel</a>
            <a href="{{ route('santris.create') }}" class="btn btn-primary">+ Tambah Santri</a>
        </div>
    </div>

    @if (session('success'))<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>@endif
    @if (session('error'))<div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>@endif

    @if ($failure = session('import_failure'))
        <div class="alert alert-danger"><strong>Import dibatalkan.</strong> {{ $failure['message'] }} Jumlah baris gagal disimpan: {{ number_format($failure['failed']) }}.</div>
    @endif

    @if ($result = session('import_result'))
        <section class="card border-0 shadow-sm mb-4" aria-labelledby="import-result-title">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <span class="text-uppercase fw-bold small text-success" style="letter-spacing:.12em">Import selesai</span>
                        <h2 id="import-result-title" class="h5 fw-bold mt-1">Ringkasan hasil import</h2>
                        <p class="text-muted mb-0">Santri baru sudah terhubung ke kelompok aktif dan periode yang dikenali pada file.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge text-bg-success p-2">Berhasil {{ number_format($result['created']) }}</span>
                        <span class="badge text-bg-warning p-2">Tidak valid {{ number_format($result['invalid']) }}</span>
                        <span class="badge text-bg-secondary p-2">Duplikat {{ number_format($result['duplicate']) }}</span>
                        <span class="badge text-bg-danger p-2">Gagal {{ number_format($result['failed']) }}</span>
                    </div>
                </div>
                @if ($result['email_failures'] > 0)<div class="alert alert-warning mt-3 mb-0">{{ $result['email_failures'] }} email verifikasi belum berhasil dikirim. Periksa konfigurasi email dan kirim ulang verifikasi dari akun terkait.</div>@endif
                @if ($result['credentials_available'])
                    <div class="alert alert-info mt-3 mb-0 d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <span>File kredensial sementara hanya dapat diunduh satu kali. Simpan dan bagikan dengan aman; santri diminta mengganti kata sandi saat masuk.</span>
                        <a class="btn btn-sm btn-outline-primary text-nowrap" href="{{ route('santris.import.credentials', session('santri_credentials_token')) }}">Unduh kredensial CSV</a>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Total Santri</div><div class="fs-3 fw-bold" style="color:#760d12">{{ number_format($totalSantri) }}</div></div></div></div>
        <div class="col-sm-6 col-xl-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Santri Aktif</div><div class="fs-3 fw-bold text-success">{{ number_format($santriAktif) }}</div></div></div></div>
        <div class="col-sm-6 col-xl-4"><div class="card h-100 border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Santri Tidak Aktif</div><div class="fs-3 fw-bold text-secondary">{{ number_format($totalSantri - $santriAktif) }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="GET" action="{{ route('santris.index') }}">
                <div class="col-12 col-lg-4"><label for="santri-search" class="form-label">Cari santri</label><input id="santri-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="NIM, nama, atau email"></div>
                <div class="col-6 col-lg-2"><label for="santri-status" class="form-label">Status</label><select id="santri-status" name="status" class="form-select"><option value="">Semua</option><option value="Aktif" @selected(($filters['status'] ?? '') === 'Aktif')>Aktif</option><option value="Tidak Aktif" @selected(($filters['status'] ?? '') === 'Tidak Aktif')>Tidak Aktif</option></select></div>
                <div class="col-6 col-lg-3"><label for="santri-period" class="form-label">Periode</label><select id="santri-period" name="periode_sik_id" class="form-select"><option value="">Semua periode</option>@foreach ($periodes as $periode)<option value="{{ $periode->id }}" @selected((string) ($filters['periode_sik_id'] ?? '') === (string) $periode->id)>{{ $periode->nama_periode }}</option>@endforeach</select></div>
                <div class="col-8 col-lg-2"><label for="santri-group" class="form-label">Kelompok</label><select id="santri-group" name="kelompok_id" class="form-select"><option value="">Semua kelompok</option>@foreach ($kelompoks as $kelompok)<option value="{{ $kelompok->id }}" @selected((string) ($filters['kelompok_id'] ?? '') === (string) $kelompok->id)>{{ $kelompok->nama_kelompok }} · {{ $kelompok->periode?->nama_periode }}</option>@endforeach</select></div>
                <div class="col-4 col-lg-1 d-flex gap-2"><button class="btn btn-danger flex-grow-1" type="submit">Cari</button></div>
                @if (count(array_filter($filters, fn ($value) => filled($value))) > 0)<div class="col-12"><a href="{{ route('santris.index') }}" class="small">Hapus filter</a></div>@endif
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center gap-2"><h2 class="h5 fw-bold mb-0">Daftar Santri</h2><span class="small text-muted">{{ number_format($santris->total()) }} data</span></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background:#fbf4f3;color:#760d12"><tr><th>No</th><th>Nomor Induk</th><th>Nama Santri</th><th>Program Studi</th><th>Kelompok</th><th>Periode</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($santris as $santri)
                        @php($membership = $santri->anggotaPeriodeTerbaru)
                        <tr>
                            <td>{{ $santris->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold">{{ $santri->nomor_induk }}</td>
                            <td>{{ $santri->nama }}</td>
                            <td>{{ $santri->program_studi ?: '—' }}</td>
                            <td>{{ $membership?->kelompok?->nama_kelompok ?? 'Belum terdaftar' }}</td>
                            <td>{{ $membership?->periode?->nama_periode ?? '—' }}</td>
                            <td><span class="badge {{ $santri->status === 'Aktif' ? 'bg-success' : 'bg-secondary' }}">{{ $santri->status }}</span></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary"
                                href="{{ route('santris.show', $santri) }}">Detail</a>

                                <a class="btn btn-sm btn-outline-warning"
                                href="{{ route('santris.edit', $santri) }}">Edit</a>
                            </td>
                    @empty
                        <tr><td colspan="8" class="text-center py-5"><div class="fw-semibold">Tidak ada data santri yang cocok</div><div class="text-muted">Ubah filter atau tambahkan santri baru.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($santris->hasPages())<div class="card-footer bg-white">{{ $santris->links() }}</div>@endif
    </div>
</div>
@endsection