<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Task
|--------------------------------------------------------------------------
| Aktifkan dengan satu cron entry di server:
|   * * * * * cd /path/ke/backend && php artisan schedule:run >> /dev/null 2>&1
*/

// Backup database otomatis setiap hari pukul 02:00, disimpan 14 hari terakhir.
Schedule::command('sigula:backup-db --simpan=14')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// Membersihkan token Sanctum yang sudah kedaluwarsa.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
