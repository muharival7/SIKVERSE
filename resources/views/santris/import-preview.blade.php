@extends('layouts.app')

@push('styles')
<style>
    .santri-import-scroll { max-height: 65vh; overflow: auto; }
    .santri-import-table { min-width: 1580px; }
    .santri-import-table > :not(caption) > * > * { padding: .85rem .75rem; vertical-align: top; }
    .santri-import-table .cell-nowrap { white-space: nowrap; }
    .santri-import-table .reason-list { white-space: normal; word-break: break-word; line-height: 1.45; font-size: .8125rem; }
    .santri-import-table .reason-list li + li { margin-top: .25rem; }
    .santri-import-table .excel-hint { white-space: normal; word-break: break-word; font-size: .75rem; }
    @media (max-width: 1200px) {
        .santri-import-table > :not(caption) > * > * { padding: .7rem .6rem; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-4 px-3 px-xl-4">
    <a href="{{ route('santris.import') }}" class="small text-decoration-none">← Ganti file</a>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small" style="color:#760d12;letter-spacing:.14em">Pemeriksaan data</span>
            <h1 class="h2 fw-bold mt-1 mb-1">Pratinjau Import Santri</h1>
            <p class="text-muted mb-0">Periksa setiap baris, pilih kelompok yang tersedia, lalu tekan Periksa ulang. Data hanya disimpan setelah tombol Import ditekan.</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('santris.index') }}">Batalkan</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Baris valid</div><strong class="fs-3 text-success">{{ number_format($preview['valid']) }}</strong></div></div></div>
        <div class="col-6 col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Tidak valid</div><strong class="fs-3 text-warning">{{ number_format($preview['invalid']) }}</strong></div></div></div>
        <div class="col-6 col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Duplikat</div><strong class="fs-3 text-secondary">{{ number_format($preview['duplicate']) }}</strong></div></div></div>
        <div class="col-6 col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Total baris</div><strong class="fs-3" style="color:#760d12">{{ number_format($preview['total']) }}</strong></div></div></div>
    </div>
    <p class="small text-muted mb-4">
        Ringkasan: {{ number_format($preview['valid']) }} valid + {{ number_format($preview['invalid']) }} tidak valid + {{ number_format($preview['duplicate']) }} duplikat = {{ number_format($preview['total']) }} baris.
        Hanya baris berstatus <strong>Valid</strong> yang disimpan.
    </p>

    <div class="alert alert-info" role="note">
        Kelompok baru tidak dibuat otomatis dan kelompok yang sudah ada tidak diganti tanpa pilihan Anda. Pilih kelompok Aktif yang sesuai dengan periode pada baris Excel, lalu tekan <strong>Periksa ulang</strong>. Jika kelompok belum tersedia, baris tetap ditandai <strong>Perlu diperbaiki</strong> dan tidak dapat diimpor.
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert"><strong>Koreksi belum dapat diproses.</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ route('santris.import.recheck') }}" method="POST" id="santri-import-preview-form">
        @csrf
        <input type="hidden" name="import_token" value="{{ old('import_token', $importToken) }}">
        <section class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h5 fw-bold mb-0">Pemeriksaan per baris</h2>
                <span class="small text-muted">Geser tabel secara horizontal untuk melihat semua kolom; header tetap terlihat saat bergulir vertikal.</span>
            </div>
            <div class="table-responsive santri-import-scroll">
                <table class="table table-hover align-middle mb-0 santri-import-table">
                    <colgroup>
                        <col style="width:110px">
                        <col style="width:125px">
                        <col style="width:190px">
                        <col style="width:170px">
                        <col style="width:230px">
                        <col style="width:150px">
                        <col style="width:115px">
                        <col style="width:220px">
                        <col style="width:370px">
                    </colgroup>
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Lokasi</th>
                            <th>NIM</th>
                            <th>Nama santri</th>
                            <th>Program Studi</th>
                            <th>Koreksi Kelompok</th>
                            <th>Periode</th>
                            <th>Status</th>
                            <th>Email akun</th>
                            <th>Status Pemeriksaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview['rows'] as $index => $row)
                            @php
                                $badgeClass = match ($row['category']) { 'valid' => 'text-bg-success', 'duplicate' => 'text-bg-secondary', default => 'text-bg-warning' };
                                $statusLabel = match ($row['category']) { 'valid' => 'Valid', 'duplicate' => 'Duplikat', default => 'Perlu diperbaiki' };
                                $selectedKelompokId = old('corrections.'.$index.'.kelompok', $row['data']['selected_kelompok_id']);
                            @endphp
                            <tr>
                                <td class="cell-nowrap small text-muted">{{ $row['sheet'] }}!{{ $row['row'] }}</td>
                                <td class="cell-nowrap font-monospace small">{{ $row['data']['nomor_induk'] ?: '—' }}</td>
                                <td>{{ $row['data']['nama'] ?: '—' }}</td>
                                <td>{{ $row['data']['program_studi'] ?: '—' }}</td>
                                <td>
                                    @if ($row['data']['kelompok_options'] === [])
                                        <span class="small text-danger">Kelompok belum tersedia untuk periode ini.</span>
                                        <input type="hidden" name="corrections[{{ $index }}][kelompok]" value="">
                                    @else
                                        <select class="form-select form-select-sm" name="corrections[{{ $index }}][kelompok]" aria-label="Koreksi kelompok baris {{ $row['row'] }}">
                                            <option value="">— Pilih kelompok —</option>
                                            @foreach ($row['data']['kelompok_options'] as $option)
                                                <option value="{{ $option['id'] }}" @selected((string) $option['id'] === (string) $selectedKelompokId)>{{ $option['nama'] }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @if ($row['data']['kelompok_excel'] !== '')
                                        <div class="excel-hint text-muted mt-1">Dari Excel: {{ $row['data']['kelompok_excel'] }}</div>
                                    @endif
                                </td>
                                <td>{{ $row['data']['periode_label'] ?: '—' }}</td>
                                <td>{{ $row['data']['status'] }} @if ($row['data']['status_defaulted'])<span class="small text-muted d-block">nilai awal</span>@endif</td>
                                <td class="cell-nowrap small">{{ $row['data']['email_akun'] ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                                    @if ($row['reasons'] !== [])
                                        <ul class="reason-list text-danger mt-2 mb-0 ps-3">
                                            @foreach ($row['reasons'] as $reason)
                                                <li>{{ $reason }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4">
            <span class="small text-muted">Perubahan kelompok dikirim bersama tombol yang dipilih.</span>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-outline-danger">Periksa ulang</button>
                <button type="submit" class="btn btn-danger" formaction="{{ route('santris.import.commit') }}" onclick="return confirm('Simpan {{ $preview['valid'] }} baris valid dan buatkan akun santri?')" @disabled($preview['valid'] === 0)>Import {{ number_format($preview['valid']) }} Santri Valid</button>
            </div>
        </div>
    </form>
</div>
@endsection
