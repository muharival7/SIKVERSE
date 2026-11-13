@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4"><h1 class="page-title h3 mb-1">Tambah Pertemuan</h1><p class="text-muted mb-0">Isi kegiatan, nomor pertemuan, tanggal, dan status.</p></div>
    <div class="card shadow-sm"><div class="card-body p-4">
        <form action="{{ route('pertemuan.store') }}" method="POST">@csrf
            @include('pertemuan.partials.form', ['item' => null])
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('pertemuan.index') }}">Batal</a></div>
        </form>
    </div></div>
</div>
@endsection
