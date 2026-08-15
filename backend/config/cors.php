<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Frontend SIGULA (React/Vite) berjalan di origin yang berbeda dengan API,
    | jadi origin-nya didaftarkan lewat env FRONTEND_URL (bisa lebih dari satu,
    | dipisah koma). Hindari '*' di production.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_URL', 'http://localhost:3000,http://localhost:5173,http://localhost:8080'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Token Bearer tidak butuh cookie; set true hanya kalau pindah ke Sanctum SPA mode.
    'supports_credentials' => false,

];
