@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4 px-xl-5">
    <a href="{{ route('santris.import') }}" class="small text-decoration-none">← Ganti file</a>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small" style="color:#760d12;letter-spacing:.14em">Pemeriksaan data</span>
            <h1 class="h2 fw-bold mt-1 mb-1">Pratinjau Import Santri</h1>
            <p class="text-muted mb-0">Lengkapi jenis kelamin dan perbaiki baris bermasalah. Data hanya disimpan setelah tombol Import ditekan.</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('santris.index') }}">Batalkan</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Baris valid</div><strong class="fs-3 text-success">{{ number_format($preview['valid']) }}</strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Tidak valid</div><strong class="fs-3 text-warning">{{ number_format($preview['invalid']) }}</strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Duplikat</div><strong class="fs-3 text-secondary">{{ number_format($preview['duplicate']) }}</strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Jumlah baris</div><strong class="fs-3" style="color:#760d12">{{ number_format(count($preview['rows'])) }}</strong></div></div></div>
    </div>

    <div class="alert alert-info" role="note">
        Kelompok baru tidak dibuat otomatis. Pastikan kelompok sudah aktif pada periode yang terdeteksi dan memiliki instruktur. Baris tidak valid atau duplikat akan dilewati; baris valid disimpan dalam satu transaksi.
    </div>

    <form action="{{ route('santris.import.recheck') }}" method="POST" id="santri-import-preview-form">
        @csrf
        <input type="hidden" name="import_token" value="{{ $importToken }}">
        <section class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h5 fw-bold mb-0">Pemeriksaan per baris</h2>
                <span class="small text-muted">Lokasi menampilkan sheet dan nomor baris Excel</span>
            </div>
            <div class="table-responsive" style="max-height:64vh">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light sticky-top"><tr><th>Lokasi</th><th>NIM</th><th>Nama</th><th style="min-width:160px">Jenis Kelamin *</th><th>Program Studi</th><th>Kelompok</th><th>Periode</th><th>Status</th><th>Email akun</th><th style="min-width:260px">Status Pemeriksaan</th></tr></thead>
                    <tbody>
                        @foreach ($preview['rows'] as $index => $row)
                            @php
                                $badgeClass = match ($row['category']) { 'valid' => 'text-bg-success', 'duplicate' => 'text-bg-secondary', default => 'text-bg-warning' };
                                $statusLabel = match ($row['category']) { 'valid' => 'Valid', 'duplicate' => 'Duplikat', default => 'Perlu diperbaiki' };
                            @endphp
                            <tr>
                                <td class="text-nowrap">{{ $row['sheet'] }}!{{ $row['row'] }}</td>
                                <td>{{ $row['data']['nomor_induk'] ?: '—' }}</td>
                                <td>{{ $row['data']['nama'] ?: '—' }}</td>
                                <td>
                                    <select class="form-select form-select-sm" name="corrections[{{ $index }}][jenis_kelamin]" aria-label="Jenis kelamin baris {{ $row['row'] }}">
                                        <option value="" @selected($row['data']['jenis_kelamin'] === '')>Pilih jenis kelamin</option>
                                        <option value="Laki-laki" @selected($row['data']['jenis_kelamin'] === 'Laki-laki')>Laki-laki</option>
                                        <option value="Perempuan" @selected($row['data']['jenis_kelamin'] === 'Perempuan')>Perempuan</option>
                                    </select>
                                </td>
                                <td>{{ $row['data']['program_studi'] ?: '—' }}</td>
                                <td>{{ $row['data']['kelompok'] ?: '—' }}</td>
                                <td>{{ $row['data']['periode_label'] ?: '—' }}</td>
                                <td>{{ $row['data']['status'] }} @if ($row['data']['status_defaulted'])<span class="small text-muted d-block">nilai awal</span>@endif</td>
                                <td>{{ $row['data']['email_akun'] ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                                    @if (count($row['reasons']) > 0)<ul class="small mt-1 mb-0 ps-3">@foreach ($row['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
            <span class="small text-muted">Perubahan jenis kelamin dikirim bersama tombol yang dipilih.</span>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-outline-danger">Periksa ulang</button>
                <button type="submit" class="btn btn-danger" formaction="{{ route('santris.import.commit') }}" onclick="return confirm('Simpan {{ $preview['valid'] }} baris valid dan buatkan akun santri?')" @disabled($preview['valid'] === 0)>Import {{ number_format($preview['valid']) }} Santri Valid</button>
            </div>
        </div>
    </form>
</div>
@endsection
