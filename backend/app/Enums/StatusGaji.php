<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromInput;

/** Status pembayaran gaji mingguan karyawan. */
enum StatusGaji: string
{
    use ResolvesFromInput;

    case BELUM_DIBAYAR = 'belum_dibayar';
    case SUDAH_DIBAYAR = 'sudah_dibayar';

    public function label(): string
    {
        return match ($this) {
            self::BELUM_DIBAYAR => 'Belum Dibayar',
            self::SUDAH_DIBAYAR => 'Sudah Dibayar',
        };
    }
}
