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
        'meeting_count' => 16,
        'minimum_present' => 14,
    ],
    'bootstrap_admin' => [
        'name' => env('SIK_BOOTSTRAP_ADMIN_NAME', 'Admin SIK'),
        'email' => env('SIK_BOOTSTRAP_ADMIN_EMAIL'),
        'password' => env('SIK_BOOTSTRAP_ADMIN_PASSWORD'),
    ],
];
