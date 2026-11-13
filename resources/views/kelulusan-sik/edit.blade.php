@extends('layouts.app')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="mb-4"><h1 class="page-title h3 mb-1">Edit Kelulusan</h1><p class="text-muted mb-0">Perbarui hasil kelulusan peserta.</p></div>
    <div class="card shadow-sm"><div class="card-body p-4">
        <form action="{{ route('kelulusan-sik.update', $kelulusan) }}" method="POST">@csrf @method('PUT')
            @include('kelulusan-sik.partials.form', ['item' => $kelulusan])
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary">Simpan Perubahan</button><a class="btn btn-outline-secondary" href="{{ route('kelulusan-sik.index') }}">Batal</a></div>
        </form>
    </div></div>
</div>
@endsection
