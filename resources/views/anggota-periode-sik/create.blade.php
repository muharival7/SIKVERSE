@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4"><h1 class="page-title h3 mb-1">Tambah Anggota Periode</h1><p class="text-muted mb-0">Daftarkan santri ke satu kelompok dalam satu periode SIK.</p></div>
    <div class="card shadow-sm"><div class="card-body p-4">
        <form action="{{ route('anggota-periode-sik.store') }}" method="POST">@csrf
            @include('anggota-periode-sik.partials.form', ['item' => null])
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('anggota-periode-sik.index') }}">Batal</a></div>
        </form>
    </div></div>
</div>
@endsection
