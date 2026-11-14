@props(['name'])

<svg {{ $attributes->merge(['class' => 'sik-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('dashboard')
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" />
            @break
        @case('grid')
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" />
            @break
        @case('students')
            <circle cx="9" cy="8" r="3" />
            <path d="M3.5 19v-1.2A4.8 4.8 0 0 1 8.3 13h1.4a4.8 4.8 0 0 1 4.8 4.8V19z" />
            <path d="M16 5.2a3 3 0 0 1 0 5.7M17 13.2a4.7 4.7 0 0 1 3.5 4.6V19" />
            @break
        @case('users')
            <circle cx="9" cy="8" r="3" />
            <path d="M3.5 19v-1a5.5 5.5 0 0 1 11 0v1zM16 5.5a3 3 0 0 1 0 5.5M17.5 13.5a4.5 4.5 0 0 1 3 4.3V19" />
            @break
        @case('attendance')
            <rect x="4" y="3.5" width="16" height="17" rx="2" />
            <path d="M8 2.5v3M16 2.5v3M4 8.5h16M8 12.5l1.5 1.5 3-3M8 17h8" />
            @break
        @case('report')
            <path d="M4 20V5M4 20h17" />
            <path d="m7 15 4-4 3 2 5-6" />
            <circle cx="19" cy="7" r="1" />
            @break
        @case('chart')
            <path d="M4 20V5M4 20h17" />
            <path d="m7 15 4-4 3 2 5-6" />
            @break
        @case('location')
            <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" />
            <circle cx="12" cy="10" r="2.5" />
            @break
        @case('calendar')
            <rect x="3.5" y="5" width="17" height="16" rx="2" />
            <path d="M7.5 3v4M16.5 3v4M3.5 9.5h17M8 13h2M14 13h2M8 17h2" />
            @break
        @case('group')
            <circle cx="8" cy="8" r="2.5" />
            <circle cx="16.5" cy="9" r="2" />
            <path d="M3 19v-1a5 5 0 0 1 10 0v1zM14 14a4 4 0 0 1 6.5 3.1V19h-5" />
            @break
        @case('book')
            <path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v17H6.5A2.5 2.5 0 0 0 4 21z" />
            <path d="M4 4.5v14A2.5 2.5 0 0 1 6.5 16H20M8 6h8M8 9h7" />
            @break
        @case('certificate')
            <path d="M6 3.5h12v16l-3-2-3 2-3-2-3 2z" />
            <circle cx="12" cy="9" r="2.5" />
            <path d="m10.5 11-1 3 2.5-1 2.5 1-1-3" />
            @break
        @case('check')
            <path d="M20 11.5V12a8 8 0 1 1-4.7-7.3" />
            <path d="m8.5 11.5 3 3L21 5" />
            @break
        @case('user')
            <circle cx="12" cy="7.5" r="3.5" />
            <path d="M4.5 20a7.5 7.5 0 0 1 15 0" />
            @break
        @case('lock')
            <rect x="5" y="10" width="14" height="11" rx="2" />
            <path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" />
            @break
        @case('lock-keyhole')
            <rect x="5" y="10" width="14" height="11" rx="2" />
            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
            <circle cx="12" cy="15" r="1.2" />
            <path d="M12 16.2V18" />
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="m4 7 8 6 8-6" />
            @break
        @case('id-card')
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <circle cx="8" cy="11" r="2" />
            <path d="M5.5 16a2.8 2.8 0 0 1 5 0M13 10h5M13 14h5" />
            @break
        @case('eye')
            <path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z" />
            <circle cx="12" cy="12" r="2.5" />
            @break
        @case('eye-off')
            <path d="m3 3 18 18M10.6 6.2A10.8 10.8 0 0 1 12 6c6.1 0 9.5 6 9.5 6a15 15 0 0 1-3 3.6M6.2 6.7C3.8 8.2 2.5 12 2.5 12s3.4 6 9.5 6a10.8 10.8 0 0 0 2.1-.2" />
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 7v5l3.5 2" />
            @break
        @case('home')
            <path d="m3.5 10 8.5-7 8.5 7M5.5 9v11h13V9M9 20v-6h6v6" />
            @break
        @case('target')
            <circle cx="12" cy="12" r="8.5" />
            <circle cx="12" cy="12" r="4.5" />
            <circle cx="12" cy="12" r="1" />
            @break
        @case('graduation')
            <path d="m2.5 9 9.5-5 9.5 5-9.5 5z" />
            <path d="M6 11v5c3.7 3 8.3 3 12 0v-5M21.5 9v6" />
            @break
        @case('award')
            <circle cx="12" cy="9" r="5.5" />
            <path d="m8.5 13.2-1 7 4.5-2.5 4.5 2.5-1-7M12 6v6M9.5 9h5" />
            @break
        @case('arrow-up-right')
            <path d="M7 17 17 7M8 7h9v9" />
            @break
        @case('arrow-right')
            <path d="M4 12h15M13 6l6 6-6 6" />
            @break
        @case('download')
            <path d="M12 3v11m-5-5 5 5 5-5M5 20h14" />
            @break
        @case('upload')
            <path d="M12 16V4m-5 5 5-5 5 5M5 15v4a1.5 1.5 0 0 0 1.5 1.5h11A1.5 1.5 0 0 0 19 19v-4" />
            @break
        @case('logout')
            <path d="M10 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H10" />
            <path d="M14 16l4-4-4-4M18 12H9" />
            @break
        @case('chevron')
            <path d="m7 10 5 5 5-5" />
            @break
        @default
            <rect x="4" y="4" width="6" height="6" rx="1" />
            <rect x="14" y="4" width="6" height="6" rx="1" />
            <rect x="4" y="14" width="6" height="6" rx="1" />
            <rect x="14" y="14" width="6" height="6" rx="1" />
    @endswitch
</svg>
