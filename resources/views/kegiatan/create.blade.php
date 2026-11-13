@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4">
        <h1 class="page-title h3 mb-1">Tambah Kegiatan</h1>
        <p class="text-muted mb-0">Catat kegiatan untuk periode SIK yang dipilih.</p>
    </div>

    <div class="card shadow-sm"><div class="card-body p-4">
        <form method="POST" action="{{ route('kegiatan.store') }}">
            @csrf
            @include('kegiatan.partials.form', ['kegiatan' => null])
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">Simpan Kegiatan</button>
                <a class="btn btn-outline-secondary" href="{{ route('kegiatan.index') }}">Batal</a>
            </div>
        </form>
    </div></div>
</div>
@endsection
