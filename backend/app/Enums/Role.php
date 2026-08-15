<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromInput;

/** Role pengguna sistem sesuai matriks hak akses SIGULA. */
enum Role: string
{
    use ResolvesFromInput;

    case OWNER = 'owner';
    case STAFF_GUDANG = 'staff_gudang';
    case STAFF_PRODUKSI = 'staff_produksi';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::STAFF_GUDANG => 'Staff Gudang',
            self::STAFF_PRODUKSI => 'Staff Produksi',
        };
    }

    /**
     * Kemampuan (gate ability) yang dimiliki role ini.
     *
     * @return array<int, string>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::OWNER => [
                'lihat-dashboard', 'lihat-keuangan', 'kelola-keuangan',
                'lihat-master', 'kelola-master',
                'lihat-petani', 'kelola-petani',
                'lihat-pembelian', 'kelola-pembelian',
                'lihat-stok', 'kelola-stok',
                'lihat-produksi', 'kelola-produksi',
                'lihat-penggajian', 'kelola-penggajian',
                'lihat-penjualan', 'kelola-penjualan',
            ],
            self::STAFF_GUDANG => [
                'lihat-dashboard',
                'lihat-master',
                'lihat-petani', 'kelola-petani',
                'lihat-pembelian', 'kelola-pembelian',
                'lihat-stok', 'kelola-stok',
                'lihat-produksi',
            ],
            self::STAFF_PRODUKSI => [
                'lihat-dashboard',
                'lihat-master',
                'lihat-stok',
                'lihat-produksi', 'kelola-produksi',
            ],
        };
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities(), true);
    }

    /** Menu sidebar yang boleh dibuka role ini (dipakai frontend). */
    public function menu(): array
    {
        $menu = [
            'dashboard' => 'lihat-dashboard',
            'petani' => 'lihat-petani',
            'master' => 'lihat-master',
            'pembelian' => 'lihat-pembelian',
            'stok' => 'lihat-stok',
            'produksi' => 'lihat-produksi',
            'penggajian' => 'lihat-penggajian',
            'penjualan' => 'lihat-penjualan',
            'keuangan' => 'lihat-keuangan',
        ];

        return array_values(array_keys(array_filter($menu, fn (string $ability): bool => $this->can($ability))));
    }
}
