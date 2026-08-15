<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromInput;

/** Jenis tarif yang dipakai modul penggajian. */
enum JenisTarif: string
{
    use ResolvesFromInput;

    case KRISTAL = 'kristal';
    case BRONDOL = 'brondol';
    case UANG_MAKAN = 'uang_makan';

    public function label(): string
    {
        return match ($this) {
            self::KRISTAL => 'Tarif Gula Kristal per Kg',
            self::BRONDOL => 'Tarif Gula Brondol per Kg',
            self::UANG_MAKAN => 'Uang Makan Harian',
        };
    }

    /** Kunci yang dipakai frontend pada objek tarif. */
    public function kunciFrontend(): string
    {
        return match ($this) {
            self::KRISTAL => 'kristal',
            self::BRONDOL => 'brondol',
            self::UANG_MAKAN => 'uangMakan',
        };
    }

    public function nilaiDefault(): float
    {
        return match ($this) {
            self::KRISTAL => 1150.0,
            self::BRONDOL => 800.0,
            self::UANG_MAKAN => 5000.0,
        };
    }
}
