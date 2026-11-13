@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="mb-4"><span class="text-uppercase fw-bold small" style="color:#760d12;letter-spacing:.14em">Data dosen</span><h1 class="h2 fw-bold mt-1">Edit Data Dosen</h1><p class="text-muted">Perbarui identitas, program studi, status, atau password dosen.</p></div>
    <div class="card border-0 shadow-sm"><div class="card-body p-4">
        <form action="{{ route('users.update', $user) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="name">Nama Lengkap</label><input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="email">Email</label><input class="form-control @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="nip">NIP</label><input class="form-control @error('nip') is-invalid @enderror" id="nip" name="nip" value="{{ old('nip', $user->infrastrukturSik?->nip) }}">@error('nip')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="program_studi">Program Studi</label><input class="form-control @error('program_studi') is-invalid @enderror" id="program_studi" name="program_studi" value="{{ old('program_studi', $user->infrastrukturSik?->program_studi) }}">@error('program_studi')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="status">Status</label><select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required><option value="Aktif" @selected(old('status', $status) === 'Aktif')>Aktif</option><option value="Tidak Aktif" @selected(old('status', $status) === 'Tidak Aktif')>Tidak Aktif</option></select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="password">Password baru (opsional)</label><input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" autocomplete="new-password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="password_confirmation">Konfirmasi password baru</label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"></div>
            </div>
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">Simpan Perubahan</button><a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Batal</a></div>
        </form>
    </div></div>
</div>
@endsection
