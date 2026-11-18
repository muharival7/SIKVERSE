@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small"
                  style="color:#760d12;letter-spacing:.14em">
                Pratinjau Impor Kehadiran
            </span>
            <h1 class="h2 fw-bold mt-1 mb-1">
                {{ $pertemuan->pertemuan_ke }} · {{ $pertemuan->tanggal?->format('d/m/Y') }}
            </h1>
            <p class="text-muted mb-0">
                {{ $pertemuan->kegiatan?->nama_kegiatan }} · Semua Kelompok
            </p>
        </div>

        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('rabu-mengaji.import.commit') }}" class="d-inline">
                @csrf
                <input type="hidden" name="import_token" value="{{ $importToken }}">
                <button class="btn btn-success" type="submit" {{ $analysis['valid'] === 0 ? 'disabled' : '' }}>
                    Simpan {{ $analysis['valid'] }} Data Valid
                </button>
            </form>
            <a class="btn btn-outline-secondary" href="{{ route('rabu-mengaji.import') }}">
                Unggah Lagi
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('import_result'))
        <div class="alert alert-success">
            Disimpan: <strong>{{ session('import_result.created') }}</strong> data,
            dilewati: <strong>{{ session('import_result.skipped') }}</strong> data.
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted small">Total baris</div>
                    <div class="fs-4 fw-bold">{{ $analysis['valid'] + $analysis['invalid'] + $analysis['duplicate'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-success small">Valid</div>
                    <div class="fs-4 fw-bold text-success">{{ $analysis['valid'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-warning small">Duplikat</div>
                    <div class="fs-4 fw-bold text-warning">{{ $analysis['duplicate'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-danger small">Tidak valid</div>
                    <div class="fs-4 fw-bold text-danger">{{ $analysis['invalid'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0">Tabel Pratinjau</h2>
        </div>
        <div class="card-body">
            @if (empty($analysis['rows']))
                <div class="alert alert-info">
                    Tidak ada baris data untuk ditampilkan.
                </div>
            @else
                <form method="POST" action="{{ route('rabu-mengaji.import.recheck') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="import_token" value="{{ $importToken }}">
                    <button class="btn btn-outline-secondary btn-sm" type="submit">Periksa Ulang</button>
                </form>

                <div class="table-responsive mt-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px">Baris</th>
                                <th>NIM</th>
                                <th>Nama Santri</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                                <th class="text-center">Kategori</th>
                                <th class="text-center">Alasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($analysis['rows'] as $row)
                                @php
                                    $badgeClass = match ($row['category']) {
                                        'valid' => 'bg-success',
                                        'duplicate' => 'bg-warning',
                                        default => 'bg-danger',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $row['row'] }}</td>
                                    <td class="fw-semibold">{{ $row['data']['nim'] ?? '—' }}</td>
                                    <td>{{ $row['data']['nama_santri'] ?? '—' }}</td>
                                    <td>{{ $row['data']['tanggal'] ?? '—' }}</td>
                                    <td>{{ $row['data']['status_kehadiran'] ?? '—' }}</td>
                                    <td>{{ $row['data']['keterangan'] ?? '—' }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $badgeClass }}">{{ ucfirst($row['category']) }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if ($row['reasons'] !== [])
                                            <ul class="list-unstyled mb-0 small text-danger">
                                                @foreach ($row['reasons'] as $reason)
                                                    <li>{{ $reason }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="text-success">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
