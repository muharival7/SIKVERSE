
@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 px-4">

    {{-- Header halaman --}}
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="text-uppercase fw-bold small"
                  style="color:#760d12;letter-spacing:.14em">
                Data dan informasi dosen
            </span>

            <h1 class="h2 fw-bold mt-1 mb-1">Data Dosen</h1>

            <p class="text-muted mb-0">
                Kelola identitas dosen dan lihat rekap kehadiran dosen dari database.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary"
               href="{{ route('users.create') }}">
                + Tambah Dosen
            </a>
        </div>
    </div>

    {{-- Pemberitahuan --}}
    @unless ($absensiDosenAvailable)
        <div class="alert alert-info">
            Tabel rekap absensi dosen belum tersedia pada database ini.
            Jalankan migration absensi dosen untuk mengaktifkannya.
        </div>
    @endunless

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if ($account = session('account_created'))
        <div class="alert alert-info">
            <strong>Akun sementara untuk {{ $account['name'] }}:</strong>

            <div>
                Username/email:
                <code>{{ $account['email'] }}</code>
            </div>

            <div>
                Password sementara:
                <code>{{ $account['password'] }}</code>
            </div>

            <div class="small mt-1">
                Sampaikan kredensial ini secara aman.
                Dosen diminta mengganti password saat masuk pertama kali.
            </div>
        </div>
    @endif

    {{-- Statistik --}}
    <div class="row g-3 mb-4">

        <div class="col-sm-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm"
                 style="background:#f8e7e7">

                <div class="card-body d-flex align-items-center gap-3">
                    <span class="sik-stat-icon sik-stat-icon--rose">
                        <x-sik-icon name="users" />
                    </span>

                    <div>
                        <div class="text-muted">Total Dosen</div>

                        <strong class="fs-3" style="color:#760d12">
                            {{ number_format($totalDosen) }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm"
                 style="background:#edf6ef">

                <div class="card-body d-flex align-items-center gap-3">
                    <span class="sik-stat-icon sik-stat-icon--green">
                        <x-sik-icon name="check" />
                    </span>

                    <div>
                        <div class="text-muted">Dosen Aktif</div>

                        <strong class="fs-3 text-success">
                            {{ number_format($dosenAktif) }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Pencarian dan filter --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">

            <form class="row g-3 align-items-end"
                  method="GET"
                  action="{{ route('users.index') }}">

                <div class="col-12 col-lg-8">
                    <label for="dosen-search" class="form-label">
                        Cari dosen
                    </label>

                    <input
                        id="dosen-search"
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        class="form-control"
                        placeholder="Nama, NIP, email, program studi, atau kelompok">
                </div>

                <div class="col-8 col-lg-3">
                    <label for="dosen-status" class="form-label">
                        Status
                    </label>

                    <select id="dosen-status"
                            name="status"
                            class="form-select">

                        <option value="">Semua</option>

                        <option value="Aktif"
                            @selected(($filters['status'] ?? '') === 'Aktif')>
                            Aktif
                        </option>

                        <option value="Tidak Aktif"
                            @selected(($filters['status'] ?? '') === 'Tidak Aktif')>
                            Tidak Aktif
                        </option>

                    </select>
                </div>

                <div class="col-4 col-lg-1 d-flex">
                    <button class="btn btn-danger w-100" type="submit">
                        Cari
                    </button>
                </div>

            </form>
        </div>
    </div>

    {{-- Tabel data dosen --}}
    <div class="card border-0 shadow-sm">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead style="background:#fbf4f3;color:#760d12">
                    <tr>
                        <th>No</th>
                        <th>Nama Dosen</th>
                        <th>NIP</th>
                        <th>Kelompok</th>
                        <th>Status</th>
                        <th>Rekap Kehadiran Dosen</th>
                        <th>Rekap Kelompok Santri</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($users as $user)

                        @php($profile = $user->infrastrukturSik)

                        <tr>

                            {{-- Nomor --}}
                            <td>
                                {{ $users->firstItem() + $loop->index }}
                            </td>

                            {{-- Identitas dosen --}}
                            <td>
                                <strong>{{ $user->name }}</strong>

                                <span class="d-block small text-muted">
                                    {{ $user->email }}
                                </span>
                            </td>

                            {{-- NIP --}}
                            <td>
                                {{ $profile?->nip ?: '—' }}
                            </td>

                            {{-- Kelompok yang diampu --}}
                            <td style="min-width:180px">

                                @forelse ($user->dosen_kelompok as $namaKelompok)

                                    <span class="badge rounded-pill sik-kelompok-badge">
                                        {{ $namaKelompok }}
                                    </span>

                                @empty

                                    <span class="small text-muted">
                                        Belum memiliki kelompok
                                    </span>

                                @endforelse

                            </td>

                            {{-- Status --}}
                            <td>
                                <span class="badge rounded-pill
                                    {{ $profile?->status === 'Aktif'
                                        ? 'text-bg-success'
                                        : 'text-bg-secondary' }}">

                                    {{ $profile?->status ?? 'Belum diatur' }}

                                </span>
                            </td>

                            {{-- Rekap kehadiran dosen --}}
                            <td style="min-width:190px">

                                @if ($absensiDosenAvailable && $profile)

                                    <div class="small">
                                        {{ number_format($user->dosen_attendance_present) }} dari {{ number_format($user->dosen_attendance_count) }} catatan hadir
                                    </div>

                                    <div class="small text-muted">
                                        {{ $user->dosen_attendance_percentage !== null
                                            ? number_format($user->dosen_attendance_percentage, 1).'%' 
                                            : 'Belum ada data' }}
                                    </div>

                                    <a class="btn btn-sm btn-outline-danger mt-1"
                                       href="{{ route('rekap-absensi-dosen.show', $profile) }}">
                                        Detail Riwayat
                                    </a>

                                @else

                                    <span class="small text-muted">
                                        Belum tersedia
                                    </span>

                                @endif

                            </td>

                            {{-- Rekap kelompok santri --}}
                            <td style="min-width:210px">

                                @if ($user->average_present !== null)

                                    <div class="d-flex justify-content-between small mb-1">

                                        <span>
                                            {{ $user->average_present }}/{{ $user->recap_meeting_count }}
                                            pertemuan
                                        </span>

                                        <span>
                                            {{ number_format($user->average_percentage, 1) }}%
                                        </span>

                                    </div>

                                    <div class="progress"
                                         role="progressbar"
                                         aria-label="Rata-rata kehadiran kelompok {{ $user->name }}"
                                         aria-valuenow="{{ $user->average_percentage }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100"
                                         style="height:8px">

                                        <div class="progress-bar bg-success"
                                             style="width:{{ min(100, $user->average_percentage) }}%">
                                        </div>

                                    </div>

                                    <span class="small text-muted">
                                        Rata-rata dari {{ $user->recap_count }} rekap santri
                                    </span>

                                @else

                                    <span class="text-muted small">
                                        Belum ada rekap
                                    </span>

                                @endif

                                @if ($profile)

                                    <div class="mt-2">

                                        <a class="btn btn-sm btn-outline-success"
                                           href="{{ route('kelompok.index') }}">

                                            Buka Kelompok Santri

                                        </a>

                                    </div>

                                @endif

                            </td>

                            {{-- Aksi --}}
                            <td class="text-end text-nowrap">

                                <a class="btn btn-sm btn-outline-primary"
                                   href="{{ route('users.edit', $user) }}">

                                    Edit

                                </a>

                                <form class="d-inline"
                                      action="{{ route('users.destroy', $user) }}"
                                      method="POST"
                                      onsubmit="return confirm('Hapus data dosen ini?')">

                                    @csrf
                                    @method('DELETE')

                                    <button class="btn btn-sm btn-outline-danger"
                                            type="submit">

                                        Hapus

                                    </button>

                                </form>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="8"
                                class="text-center text-muted py-5">

                                Belum ada data dosen.

                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        {{-- Pagination --}}
        @if ($users->hasPages())

            <div class="card-footer bg-white">
                {{ $users->links() }}
            </div>

        @endif

    </div>

</div>
@endsection
