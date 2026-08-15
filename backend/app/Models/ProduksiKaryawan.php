<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Porsi hasil produksi per karyawan (selalu setengah dari total sesi tungku). */
class ProduksiKaryawan extends Model
{
    protected $table = 'produksi_karyawan';

    protected $fillable = [
        'sesi_tungku_id',
        'karyawan_id',
        'tanggal',
        'kg_bahan_mentah_porsi',
        'kg_kristal_porsi',
        'kg_brondol_porsi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'kg_bahan_mentah_porsi' => 'float',
            'kg_kristal_porsi' => 'float',
            'kg_brondol_porsi' => 'float',
        ];
    }

    /** @return BelongsTo<SesiTungku, $this> */
    public function sesiTungku(): BelongsTo
    {
        return $this->belongsTo(SesiTungku::class);
    }

    /** @return BelongsTo<Karyawan, $this> */
    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function scopeRentang(Builder $query, string $dari, string $sampai): Builder
    {
        return $query->whereBetween('tanggal', [$dari, $sampai]);
    }
}
