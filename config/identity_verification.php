<?php

return [
    'provider' => env('IDENTITY_VERIFICATION_PROVIDER', 'idenfy'),
    'required' => filter_var(
        env('IDENTITY_VERIFICATION_REQUIRED', env('APP_ENV') === 'production'),
        FILTER_VALIDATE_BOOL
    ),
    'session_lifetime' => (int) env('IDENTITY_VERIFICATION_SESSION_LIFETIME', 900),
    'session_length' => (int) env('IDENTITY_VERIFICATION_SESSION_LENGTH', 600),
];
