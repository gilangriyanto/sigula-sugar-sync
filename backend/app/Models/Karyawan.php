<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KaryawanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Karyawan extends Model
{
    /** @use HasFactory<KaryawanFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'karyawan';

    protected $fillable = [
        'nama',
        'kontak',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    /** @return HasMany<ProduksiKaryawan, $this> */
    public function produksi(): HasMany
    {
        return $this->hasMany(ProduksiKaryawan::class);
    }

    /** @return HasMany<GajiMingguan, $this> */
    public function gaji(): HasMany
    {
        return $this->hasMany(GajiMingguan::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopeCari(Builder $query, ?string $term): Builder
    {
        return blank($term) ? $query : $query->where('nama', 'like', '%'.$term.'%');
    }
}
