<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SIKVERSE') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('Css/login.css') }}?v={{ filemtime(public_path('Css/login.css')) }}">
    </head>
    <body class="font-sans antialiased sik-auth-page">
        <main class="sik-auth-shell">
            <aside class="sik-auth-visual" aria-label="Kegiatan Sekolah Islam Kebangsaan">
                <a class="sik-auth-visual-brand" href="{{ route('beranda') }}">
                    <img src="{{ asset('images/logo-fst.png') }}" alt="Logo Fakultas Sains dan Teknologi">
                    <span><strong>SIKVERSE</strong><small>Fakultas Sains &amp; Teknologi</small></span>
                </a>
            </aside>

            <section class="sik-auth-panel" aria-label="Akun SIKVERSE">
                <div class="sik-auth-content">
                    {{ $slot }}
                </div>
            </section>
        </main>
        <script src="{{ asset('js/auth-password-toggle.js') }}?v={{ filemtime(public_path('js/auth-password-toggle.js')) }}" defer></script>
    </body>
</html>
