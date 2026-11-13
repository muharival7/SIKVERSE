@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small" style="color:#760d12;letter-spacing:.14em">Data dan informasi dosen</span>
            <h1 class="h2 fw-bold mt-1 mb-1">Data Dosen</h1>
            <p class="text-muted mb-0">Kelola identitas dosen dan pantau rekap kehadiran kelompok yang diampu.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('users.create') }}">+ Tambah Dosen</a>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if ($account = session('account_created'))
        <div class="alert alert-info">
            <strong>Akun sementara untuk {{ $account['name'] }}:</strong>
            <div>Username/email: <code>{{ $account['email'] }}</code></div>
            <div>Password sementara: <code>{{ $account['password'] }}</code></div>
            <div class="small mt-1">Sampaikan kredensial ini secara aman. Dosen diminta mengganti password saat masuk pertama kali.</div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4"><div class="card h-100 border-0 shadow-sm" style="background:#f8e7e7"><div class="card-body d-flex align-items-center gap-3"><span class="sik-stat-icon sik-stat-icon--rose"><x-sik-icon name="users" /></span><div><div class="text-muted">Total Dosen</div><strong class="fs-3" style="color:#760d12">{{ number_format($totalDosen) }}</strong></div></div></div></div>
        <div class="col-sm-6 col-xl-4"><div class="card h-100 border-0 shadow-sm" style="background:#edf6ef"><div class="card-body d-flex align-items-center gap-3"><span class="sik-stat-icon sik-stat-icon--green"><x-sik-icon name="check" /></span><div><div class="text-muted">Dosen Aktif</div><strong class="fs-3 text-success">{{ number_format($dosenAktif) }}</strong></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead style="background:#fbf4f3;color:#760d12"><tr><th>No</th><th>Nama Dosen</th><th>NIP</th><th>Program Studi</th><th>Status</th><th>Rekap Absensi</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        @php($profile = $user->infrastrukturSik)
                        <tr>
                            <td>{{ $users->firstItem() + $loop->index }}</td>
                            <td><strong>{{ $user->name }}</strong><span class="d-block small text-muted">{{ $user->email }}</span></td>
                            <td>{{ $profile?->nip ?: '—' }}</td>
                            <td>{{ $profile?->program_studi ?: '—' }}</td>
                            <td><span class="badge rounded-pill {{ $profile?->status === 'Aktif' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $profile?->status ?? 'Belum diatur' }}</span></td>
                            <td style="min-width:210px">
                                @if ($user->average_present !== null)
                                    <div class="d-flex justify-content-between small mb-1"><span>{{ $user->average_present }}/{{ $user->recap_meeting_count }} pertemuan</span><span>{{ number_format($user->average_percentage, 1) }}%</span></div>
                                    <div class="progress" role="progressbar" aria-label="Rata-rata kehadiran kelompok {{ $user->name }}" aria-valuenow="{{ $user->average_percentage }}" aria-valuemin="0" aria-valuemax="100" style="height:8px"><div class="progress-bar bg-success" style="width:{{ min(100, $user->average_percentage) }}%"></div></div>
                                    <span class="small text-muted">Rata-rata dari {{ $user->recap_count }} rekap santri</span>
                                    @if ($profile)<a class="d-block small fw-semibold mt-1" href="{{ route('rekap-kehadiran-sik.index', ['instruktur_id' => $profile->id]) }}">Buka rekap absensi</a>@endif
                                @else
                                    <span class="text-muted">Belum ada rekap</span>
                                    @if ($profile)<a class="d-block small fw-semibold mt-1" href="{{ route('rekap-kehadiran-sik.index', ['instruktur_id' => $profile->id]) }}">Buka rekap absensi</a>@endif
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('users.edit', $user) }}">Edit</a>
                                <form class="d-inline" action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('Hapus data dosen ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">Belum ada data dosen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())<div class="card-footer bg-white">{{ $users->links() }}</div>@endif
    </div>
</div>
@endsection
