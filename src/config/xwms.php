<?php

return [
    "client_id" => env("XWMS_CLIENT_ID"),
    "client_secret" => env("XWMS_CLIENT_SECRET"),
    "client_redirect" => env("XWMS_REDIRECT_URI"),
    "xwms_api_url" => env("XWMS_API_URI", 'https://xwms.nl/api/'),
    "xwms_api_timeout" => 10,
    "account_url" => env("XWMS_ACCOUNT_URL", 'https://xwms.nl/account/general'),

    "locale" => [
        "locales" => ['nl', 'en', 'ar', 'bn', 'de', 'es', 'id', 'pt', 'ru', 'zh'],
        "default" => "en"
    ],
    "google" => [
        "MapsApiKey" => env('GOOGLE_MAPS_API_KEY', null)
    ],
    'models' => [
        'Session' => \App\Models\Session::class,
        'User' => \App\Models\User::class,
        'CodeVerification' => \App\Models\CodeVerification::class,
        'XwmsConnection' => \XWMS\Package\Models\XwmsConnection::class,
    ],
    'user_field_map' => [
        'name' => 'name',
        'email' => 'email',
        'img' => 'picture',
    ],
    'user_field_transforms' => [
    ]
];
