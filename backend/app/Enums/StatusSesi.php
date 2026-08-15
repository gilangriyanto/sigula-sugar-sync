<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromInput;

/** Status satu sesi tungku produksi. */
enum StatusSesi: string
{
    use ResolvesFromInput;

    case SEDANG_DIPROSES = 'sedang_diproses';
    case SELESAI = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::SEDANG_DIPROSES => 'Sedang Diproses',
            self::SELESAI => 'Selesai',
        };
    }
}
