@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="mb-4"><span class="text-uppercase fw-bold small" style="color:#760d12;letter-spacing:.14em">Data dosen</span><h1 class="h2 fw-bold mt-1">Tambah Dosen</h1><p class="text-muted">Buat akun dosen dan lengkapi informasi akademiknya.</p></div>
    <div class="card border-0 shadow-sm"><div class="card-body p-4">
        <form action="{{ route('users.store') }}" method="POST">
            @csrf
            <div class="row g-3">
                @foreach ([['name','Nama Lengkap','text'],['email','Email','email'],['nip','NIP','text'],['program_studi','Program Studi','text']] as [$field,$label,$type])
                    <div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" required>@error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @endforeach
            </div>
            <p class="form-text mt-3">Sistem membuat password sementara dan meminta dosen menggantinya saat pertama kali masuk.</p>
            <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan Dosen</button><a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Batal</a></div>
        </form>
    </div></div>
</div>
@endsection
