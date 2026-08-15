<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromInput;

/** Grade bahan mentah yang dibeli dari petani. */
enum Grade: string
{
    use ResolvesFromInput;

    case NS1 = 'ns1';
    case NS2 = 'ns2';
    case KECAP = 'kecap';

    public function label(): string
    {
        return match ($this) {
            self::NS1 => 'NS 1',
            self::NS2 => 'NS 2',
            self::KECAP => 'Kecap',
        };
    }

    /** Kategori stok bahan mentah yang berkaitan dengan grade ini. */
    public function kategoriStok(): KategoriStok
    {
        return match ($this) {
            self::NS1 => KategoriStok::NS1,
            self::NS2 => KategoriStok::NS2,
            self::KECAP => KategoriStok::KECAP,
        };
    }
}
