<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Frontend SIGULA (React/Vite) berjalan di origin yang berbeda dengan API,
    | jadi origin-nya didaftarkan lewat env (bisa lebih dari satu, dipisah
    | koma). Hindari '*' di production.
    |
    | - FRONTEND_URL           origin utama (mis. domain production).
    | - CORS_ALLOWED_ORIGINS   origin tambahan, opsional — dipakai untuk origin
    |                          dev lokal (mis. http://localhost:8080) tanpa
    |                          perlu mengubah FRONTEND_URL. Kosongkan di
    |                          production.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter(array_map(
        'trim',
        array_merge(
            explode(',', (string) env('FRONTEND_URL', 'http://localhost:3000,http://localhost:5173,http://localhost:8080')),
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
        )
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Supaya frontend bisa membaca nama file dari respons export CSV.
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    // Token Bearer tidak butuh cookie; set true hanya kalau pindah ke Sanctum SPA mode.
    'supports_credentials' => false,

];
