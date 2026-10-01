<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | This is a public-facing render API meant to be called from any
    | frontend that integrates it (e.g. nhs-portal-1 on Vercel, or a local
    | dev build of it) — including the generated video files themselves,
    | not just the JSON endpoints under api/*. There's no session/cookie
    | auth here, so a wide-open origin list carries no real risk.
    |
    */

    'paths' => ['api/*', 'storage/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
