<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="sik-profile-heading">Pengaturan akun</h2>
            <p class="sik-profile-subheading">Perbarui informasi profil dan kata sandi Anda.</p>
        </div>
    </x-slot>

    @push('styles')
        <link rel="stylesheet" href="{{ asset('Css/profile.css') }}?v={{ filemtime(public_path('Css/profile.css')) }}">
    @endpush

    <div class="py-10 sik-profile-page">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 sik-profile-content">
            <section class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg sik-profile-card">
                <div class="max-w-xl sik-profile-form">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </section>

            <section class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg sik-profile-card">
                <div class="max-w-xl sik-profile-form">
                    @include('profile.partials.update-password-form')
                </div>
            </section>

            <section class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg sik-profile-card sik-profile-card--danger">
                <div class="max-w-xl sik-profile-form">
                    @include('profile.partials.delete-user-form')
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
