<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromInput;

/** Kategori biaya operasional lain-lain. */
enum KategoriBiaya: string
{
    use ResolvesFromInput;

    case LISTRIK = 'listrik';
    case TRANSPORT = 'transport';
    case SEWA = 'sewa';
    case LAINNYA = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::LISTRIK => 'Listrik',
            self::TRANSPORT => 'Transport',
            self::SEWA => 'Sewa',
            self::LAINNYA => 'Lainnya',
        };
    }
}
