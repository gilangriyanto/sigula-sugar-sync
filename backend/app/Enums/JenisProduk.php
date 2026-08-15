<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromInput;

/** Produk jadi yang dijual ke eksportir (satu invoice bisa berisi keduanya). */
enum JenisProduk: string
{
    use ResolvesFromInput;

    case KRISTAL = 'kristal';
    case BRONDOL = 'brondol';

    public function label(): string
    {
        return match ($this) {
            self::KRISTAL => 'Kristal',
            self::BRONDOL => 'Brondol',
        };
    }

    public function labelPanjang(): string
    {
        return match ($this) {
            self::KRISTAL => 'Gula Kristal',
            self::BRONDOL => 'Gula Brondol',
        };
    }

    public function kategoriStok(): KategoriStok
    {
        return match ($this) {
            self::KRISTAL => KategoriStok::KRISTAL,
            self::BRONDOL => KategoriStok::BRONDOL,
        };
    }
}
