@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="page-title h3 mb-1">Detail Santri</h1>
            <p class="text-muted mb-0">Informasi santri dan akun SIK yang terhubung.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('santris.edit', $santri) }}" class="btn btn-warning">Edit</a>
            <a href="{{ route('santris.index') }}" class="btn btn-outline-secondary">Kembali</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <dl class="row mb-0">
                <dt class="col-sm-4">Nomor Induk</dt>
                <dd class="col-sm-8">{{ $santri->nomor_induk }}</dd>
                <dt class="col-sm-4">Nama</dt>
                <dd class="col-sm-8">{{ $santri->nama }}</dd>
                <dt class="col-sm-4">Jenis Kelamin</dt>
                <dd class="col-sm-8">{{ $santri->jenis_kelamin }}</dd>
                <dt class="col-sm-4">Fingerprint ID</dt>
                <dd class="col-sm-8">{{ $santri->fingerprint?->fingerprint_id ?? '—' }}</dd>
                <dt class="col-sm-4">Akun Login</dt>
                <dd class="col-sm-8">{{ $santri->user?->email ?? 'Belum terhubung' }}</dd>
                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8">{{ $santri->status }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
