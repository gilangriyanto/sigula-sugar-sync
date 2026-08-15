<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromInput;

/** Arah pergerakan stok pada kartu stok. */
enum JenisMutasi: string
{
    use ResolvesFromInput;

    case MASUK = 'masuk';
    case KELUAR = 'keluar';

    public function label(): string
    {
        return match ($this) {
            self::MASUK => 'Masuk',
            self::KELUAR => 'Keluar',
        };
    }

    /** +1 untuk masuk, -1 untuk keluar. */
    public function faktor(): int
    {
        return $this === self::MASUK ? 1 : -1;
    }

    public function kebalikan(): self
    {
        return $this === self::MASUK ? self::KELUAR : self::MASUK;
    }
}
