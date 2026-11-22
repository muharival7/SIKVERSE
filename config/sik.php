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
        // Keputusan pengguna: ambang dihitung dari pertemuan yang benar-benar
        // Terlaksana. Pertemuan Dibatalkan/Terjadwal tidak menjadi penyebut,
        // dan tidak ada jumlah pertemuan tetap yang dijadikan kebijakan.
        'minimum_percentage' => 85,
    ],
    'bootstrap_admin' => [
        'name' => env('SIK_BOOTSTRAP_ADMIN_NAME', 'Admin SIK'),
        'email' => env('SIK_BOOTSTRAP_ADMIN_EMAIL'),
        'password' => env('SIK_BOOTSTRAP_ADMIN_PASSWORD'),
    ],
];
