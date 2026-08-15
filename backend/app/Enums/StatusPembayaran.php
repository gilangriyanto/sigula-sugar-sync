<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromInput;

/** Status pembayaran transaksi pembelian maupun penjualan. */
enum StatusPembayaran: string
{
    use ResolvesFromInput;

    case LUNAS = 'lunas';
    case BELUM_LUNAS = 'belum_lunas';

    public function label(): string
    {
        return match ($this) {
            self::LUNAS => 'Lunas',
            self::BELUM_LUNAS => 'Belum Lunas',
        };
    }
}
