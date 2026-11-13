@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4">
        <h1 class="page-title h3 mb-1">Sertifikat SIK</h1>
        <p class="text-muted mb-0">Sertifikat kelulusan yang diterbitkan untuk akunmu.</p>
    </div>

    <div class="row g-4">
        @forelse ($sertifikat as $item)
            <div class="col-md-6 col-lg-4"><div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-primary text-white py-3"><h2 class="h5 mb-0 fw-bold">{{ $item->nomor_sertifikat }}</h2></div>
                <div class="card-body">
                    <div class="mb-3"><small class="text-muted">Status Kelulusan</small><div class="fw-semibold">{{ $item->kelulusan?->status ?? '—' }}</div></div>
                    <div class="mb-3"><small class="text-muted">Periode</small><div class="fw-semibold">{{ $item->kelulusan?->anggotaPeriodeSik?->periode?->nama_periode ?? '—' }}</div></div>
                    <div class="mb-3"><small class="text-muted">Tanggal Terbit</small><div class="fw-semibold">{{ $item->tanggal_terbit?->format('d-m-Y') ?? '—' }}</div></div>
                    <div><small class="text-muted">Keterangan</small><div>{{ $item->keterangan ?: '—' }}</div></div>
                </div>
                <div class="card-footer bg-white border-0 pb-3"><a href="{{ route('sertifikat-sik.download', $item) }}" class="btn btn-primary w-100">Unduh PDF</a></div>
            </div></div>
        @empty
            <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-center py-5"><h2 class="h5 fw-bold">Belum Ada Sertifikat</h2><p class="text-muted mb-0">Sertifikat akan muncul setelah kelulusan dinyatakan dan berkas diterbitkan.</p></div></div></div>
        @endforelse
    </div>
</div>
@endsection
