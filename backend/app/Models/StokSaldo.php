<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KategoriStok;
use Illuminate\Database\Eloquent\Model;

class StokSaldo extends Model
{
    protected $table = 'stok_saldo';

    protected $fillable = [
        'kategori',
        'saldo_kg',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriStok::class,
            'saldo_kg' => 'float',
        ];
    }
}
