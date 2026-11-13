
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SIKVERSE') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">

    {{-- Bootstrap --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Tailwind Laravel --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')

    <link rel="stylesheet" href="{{ asset('Css/app-shell.css') }}?v={{ filemtime(public_path('Css/app-shell.css')) }}">
</head>

<body class="font-sans antialiased">

    @php
        $layoutRole = Auth::user()?->role;
        $usesSidebarLayout = in_array($layoutRole, [
            \App\Enums\UserRole::Admin,
            \App\Enums\UserRole::InfrastrukturSik,
        ], true);
    @endphp

    <div class="min-h-screen {{ $usesSidebarLayout ? 'sik-app-frame sik-app-frame--sidebar' : '' }}">

        @include('layouts.navigation')

        <main class="{{ $usesSidebarLayout ? 'sik-app-main--sidebar' : '' }}">
            @isset($header)
                <header class="bg-white shadow-sm sik-page-slot-header">
                    <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset
        </main>

        @unless ($usesSidebarLayout)
            <footer class="sd-app-footer {{ $layoutRole === \App\Enums\UserRole::KaLabMutu ? 'sd-app-footer--start' : '' }}">
                <div class="container">
                    <form method="POST" action="{{ route('logout') }}" class="mb-0">
                        @csrf
                        <button class="sd-app-logout" type="submit">
                            <x-sik-icon name="logout" class="sik-icon" />
                            <span>Keluar</span>
                        </button>
                    </form>
                </div>
            </footer>
        @endunless

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')

</body>
</html>
