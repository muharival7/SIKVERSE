@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <span class="text-uppercase fw-bold small" style="color:#760d12;letter-spacing:.14em">Data santri</span>
        <h1 class="h2 fw-bold mt-1 mb-1">Edit Data Santri</h1>
        <p class="text-muted mb-0">Perbarui identitas dan status akun santri.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><h2 class="h5 fw-bold mb-0">{{ $santri->nama }}</h2></div>
        <div class="card-body p-4">
            <form action="{{ route('santris.update', $santri) }}" method="POST">
                @csrf
                @method('PUT')
                @include('santris.partials.form-fields', ['santri' => $santri])
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                    <a href="{{ route('santris.index') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-danger">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
