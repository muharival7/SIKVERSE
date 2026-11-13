@props(['active' => ''])

<header class="navbar">
    <a href="{{ route('beranda') }}#beranda" class="brand" aria-label="SIKVERSE, Beranda">
        <img
            src="{{ asset('images/logo-fst.png') }}"
            alt="Logo Fakultas Sains dan Teknologi"
            class="brand-logo"
        >

        <div class="brand-text">
            <h2>SIKVERSE</h2>
            <small>Fakultas Sains &amp; Teknologi</small>
        </div>
    </a>

    <nav class="nav-menu" aria-label="Navigasi utama">
        <a href="{{ route('beranda') }}#beranda" class="nav-link {{ $active === 'beranda' ? 'active' : '' }}" @if ($active === 'beranda') aria-current="page" @endif>Beranda</a>
        <a href="{{ route('beranda') }}#tentang" class="nav-link {{ $active === 'tentang' ? 'active' : '' }}" @if ($active === 'tentang') aria-current="location" @endif>Tentang SIK</a>
        <a href="{{ route('beranda') }}#layanan" class="nav-link {{ $active === 'layanan' ? 'active' : '' }}" @if ($active === 'layanan') aria-current="location" @endif>Layanan</a>
        <a href="{{ route('login') }}" class="btn-login"><x-sik-icon name="user" class="nav-login-icon" />Login</a>
    </nav>
</header>
