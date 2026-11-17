@php
    $user = Auth::user();
    $role = $user?->resolvedRole();
    $usesSidebarLayout = in_array($role, [
        \App\Enums\UserRole::Admin,
        \App\Enums\UserRole::InfrastrukturSik,
    ], true);

    $mainNavItems = [];
    $secondaryNavItems = [];

if ($role === \App\Enums\UserRole::Admin) {
    $mainNavItems = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'matches' => ['dashboard*'], 'icon' => 'dashboard'],
        ['label' => 'Data Santri', 'route' => 'santris.index', 'matches' => ['santris.*'], 'icon' => 'students'],

        // Menu baru
        ['label' => 'Kelola Status Kelulusan', 'route' => 'kelulusan-sik.index', 'matches' => ['kelulusan-sik.*'], 'icon' => 'report'],

        ['label' => 'Data Dosen', 'route' => 'users.index', 'matches' => ['users.*'], 'icon' => 'users'],
        ['label' => 'Rekap Absensi', 'route' => 'rekap-kehadiran-sik.index', 'matches' => ['rekap-kehadiran-sik.*'], 'icon' => 'report'],
        ['label' => 'Rabu Mengaji', 'route' => 'rabu-mengaji.index', 'matches' => ['rabu-mengaji.*'], 'icon' => 'attendance'],
    ];
    } elseif ($role === \App\Enums\UserRole::InfrastrukturSik) {
        $mainNavItems = [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'matches' => ['dashboard*'], 'icon' => 'dashboard'],
            ['label' => 'Mahasiswa', 'route' => 'instruktur.anggota.index', 'matches' => ['instruktur.anggota.*'], 'icon' => 'students'],
            ['label' => 'Absen', 'route' => 'absensi-sik.index', 'matches' => ['absensi-sik.*'], 'icon' => 'attendance'],
            ['label' => 'Nilai', 'route' => 'nilai.index', 'matches' => ['nilai.*'], 'icon' => 'book'],
            ['label' => 'Rekap Absen', 'route' => 'rekap-kehadiran-sik.index', 'matches' => ['rekap-kehadiran-sik.*'], 'icon' => 'report'],
            ['label' => 'Rabu Mengaji', 'route' => 'rabu-mengaji.index', 'matches' => ['rabu-mengaji.*'], 'icon' => 'attendance'],
        ];

        $secondaryNavItems = [
            ['label' => 'Kelompok Saya', 'route' => 'instruktur.kelompok.index', 'matches' => ['instruktur.kelompok.*'], 'icon' => 'group'],
        ];
    }

    $secondaryNavIsActive = collect($secondaryNavItems)
        ->contains(fn (array $item): bool => request()->routeIs(...$item['matches']));
@endphp

@if ($usesSidebarLayout)
    <header class="sik-topbar">
        <button class="sik-sidebar-toggle" type="button" data-sidebar-toggle aria-controls="sik-sidebar" aria-expanded="false" aria-label="Buka menu">
            <span></span><span></span><span></span>
        </button>

        <a class="sik-brand" href="{{ route('dashboard') }}" aria-label="SIKVERSE, Dashboard">
            <img src="{{ asset('images/logo-fst.png') }}" alt="" width="48" height="48">
            <span class="sik-brand-copy">
                <strong>SIKVERSE</strong>
                <small>Fakultas Sains &amp; Teknologi</small>
            </span>
        </a>

        <div class="sik-user-chip" aria-label="Pengguna {{ $user->name }}">
            <span class="sik-user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <span class="sik-user-copy">
                <strong>{{ $role?->label() }}</strong>
                <small>{{ $user->name }}</small>
            </span>
        </div>
    </header>

    <button class="sik-sidebar-backdrop" type="button" data-sidebar-backdrop aria-label="Tutup menu"></button>

    <aside class="sik-sidebar" id="sik-sidebar" aria-label="Navigasi utama">
        <div class="sik-sidebar-section-label">MENU UTAMA</div>
        <nav class="sik-sidebar-nav">
            @foreach ($mainNavItems as $item)
                @php($isActive = request()->routeIs(...$item['matches']))
                <a class="sik-sidebar-link {{ $isActive ? 'is-active' : '' }}" href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif>
                    <x-sik-icon :name="$item['icon']" class="sik-sidebar-icon" />
                    <span>{{ $item['label'] }}</span>
                    @if ($isActive)<span class="sik-sidebar-active-dot" aria-hidden="true"></span>@endif
                </a>
            @endforeach

            @if (count($secondaryNavItems) > 0)
                <details class="sik-sidebar-accordion" @if ($secondaryNavIsActive) open @endif>
                    <summary>
                        <x-sik-icon name="grid" class="sik-sidebar-icon" />
                        <span>Menu lainnya</span>
                        <x-sik-icon name="chevron" class="sik-accordion-chevron" />
                    </summary>
                    <div class="sik-sidebar-subnav">
                        @foreach ($secondaryNavItems as $item)
                            @php($isActive = request()->routeIs(...$item['matches']))
                            <a class="sik-sidebar-link {{ $isActive ? 'is-active' : '' }}" href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif>
                                <x-sik-icon :name="$item['icon']" class="sik-sidebar-icon" />
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endif
        </nav>

    </aside>

    @push('scripts')
        <script src="{{ asset('js/app-sidebar.js') }}" defer></script>
    @endpush
@else
<nav class="navbar navbar-light shadow-sm sd-app-navbar">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="{{ route('dashboard') }}" aria-label="SIKVERSE, Dashboard">
            <img src="{{ asset('images/logo-fst.png') }}" alt="" width="44" height="44" class="sd-navbar-logo">
            <span><strong>SIKVERSE</strong><small>Fakultas Sains &amp; Teknologi</small></span>
        </a>
        <div class="sd-app-user" aria-label="{{ $role?->label() }}">
            <span class="sd-app-user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <span><strong>{{ $role?->label() }}</strong><small>{{ $user->name }}</small></span>
        </div>
    </div>
</nav>
@endif
