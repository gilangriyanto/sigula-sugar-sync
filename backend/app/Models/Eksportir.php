<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EksportirFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Eksportir extends Model
{
    /** @use HasFactory<EksportirFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'eksportir';

    protected $fillable = [
        'nama',
        'kontak',
        'alamat',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    /** @return HasMany<Penjualan, $this> */
    public function penjualan(): HasMany
    {
        return $this->hasMany(Penjualan::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}
