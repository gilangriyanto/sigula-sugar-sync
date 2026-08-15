<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi khusus SIGULA
    |--------------------------------------------------------------------------
    |
    | Nilai-nilai ini sengaja dibaca lewat config (bukan env() langsung di
    | seeder), karena setelah `php artisan config:cache` dijalankan di server
    | production, env() di luar file config akan mengembalikan null.
    |
    */

    // Password awal 3 akun bawaan UserSeeder. WAJIB diganti di production.
    'default_password' => env('SIGULA_DEFAULT_PASSWORD', 'password'),

    // Mengisi data transaksi demo ±6 bulan saat `db:seed`. Set false di production.
    'seed_demo' => env('SIGULA_SEED_DEMO', true),

];
