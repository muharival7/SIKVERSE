<?php

return [
    'auth' => [
        // Local development may use placeholder emails; enable verification before production.
        'require_email_verification' => env(
            'SIK_REQUIRE_EMAIL_VERIFICATION',
            env('APP_ENV', 'production') !== 'local'
        ),
    ],
    'attendance' => [
        // Legacy fixed-count settings. Dipertahankan untuk kompatibilitas
        // mundur pada fitur yang masih menggunakan jumlah maksimal pertemuan
        // (misalnya validasi formulir pertemuan). Sumber kebenaran tunggal
        // untuk ambang kehadiran adalah minimum_percentage di bawah ini.
        // JANGAN menggunakan minimum_present atau meeting_count untuk
        // perhitungan kelayakan kelulusan atau sertifikat.
        'meeting_count' => 16,
        'minimum_present' => 14,

        // Single source of truth for the attendance threshold. The service
        // compares Hadir * 1000 >= Terlaksana * (minimum_percentage * 10).
        'minimum_percentage' => 87.5,
    ],
    'bootstrap_admin' => [
        'name' => env('SIK_BOOTSTRAP_ADMIN_NAME', 'Admin SIK'),
        'email' => env('SIK_BOOTSTRAP_ADMIN_EMAIL'),
        'password' => env('SIK_BOOTSTRAP_ADMIN_PASSWORD'),
    ],
];
