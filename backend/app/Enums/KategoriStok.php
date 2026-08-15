<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromInput;

/** Kategori saldo stok: 3 grade bahan mentah + 2 produk jadi. */
enum KategoriStok: string
{
    use ResolvesFromInput;

    case NS1 = 'ns1';
    case NS2 = 'ns2';
    case KECAP = 'kecap';
    case KRISTAL = 'kristal';
    case BRONDOL = 'brondol';

    public function label(): string
    {
        return match ($this) {
            self::NS1 => 'NS 1',
            self::NS2 => 'NS 2',
            self::KECAP => 'Kecap',
            self::KRISTAL => 'Kristal',
            self::BRONDOL => 'Brondol',
        };
    }

    /** Label panjang untuk kartu stok, contoh "Bahan Mentah NS 1". */
    public function labelPanjang(): string
    {
        return $this->isBahanMentah()
            ? 'Bahan Mentah '.$this->label()
            : 'Produk '.$this->label();
    }

    public function isBahanMentah(): bool
    {
        return in_array($this, [self::NS1, self::NS2, self::KECAP], true);
    }

    public function isProdukJadi(): bool
    {
        return ! $this->isBahanMentah();
    }

    /** @return array<int, self> */
    public static function bahanMentah(): array
    {
        return [self::NS1, self::NS2, self::KECAP];
    }

    /** @return array<int, self> */
    public static function produkJadi(): array
    {
        return [self::KRISTAL, self::BRONDOL];
    }
}
