<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisProduk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenjualanItem extends Model
{
    protected $table = 'penjualan_item';

    protected $fillable = [
        'penjualan_id',
        'jenis',
        'kilogram',
        'harga_per_kg',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisProduk::class,
            'kilogram' => 'float',
            'harga_per_kg' => 'float',
            'subtotal' => 'float',
        ];
    }

    /** @return BelongsTo<Penjualan, $this> */
    public function penjualan(): BelongsTo
    {
        return $this->belongsTo(Penjualan::class);
    }
}
