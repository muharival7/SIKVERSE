@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4"><h1 class="page-title h3 mb-1">Catat Kelulusan</h1><p class="text-muted mb-0">Pilih anggota aktif yang belum memiliki catatan kelulusan.</p></div>
    <div class="card shadow-sm"><div class="card-body p-4">
        <form action="{{ route('kelulusan-sik.store') }}" method="POST">@csrf
            @include('kelulusan-sik.partials.form', ['item' => null])
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('kelulusan-sik.index') }}">Batal</a></div>
        </form>
    </div></div>
</div>
@endsection
