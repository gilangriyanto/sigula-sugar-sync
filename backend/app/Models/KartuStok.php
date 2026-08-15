<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisMutasi;
use App\Enums\KategoriStok;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KartuStok extends Model
{
    protected $table = 'kartu_stok';

    protected $fillable = [
        'tanggal',
        'kategori',
        'jenis',
        'jumlah_kg',
        'saldo_setelah',
        'keterangan',
        'referensi_type',
        'referensi_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'kategori' => KategoriStok::class,
            'jenis' => JenisMutasi::class,
            'jumlah_kg' => 'float',
            'saldo_setelah' => 'float',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function referensi(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeKategori(Builder $query, ?KategoriStok $kategori): Builder
    {
        return $kategori === null ? $query : $query->where('kategori', $kategori->value);
    }

    public function scopeJenis(Builder $query, ?JenisMutasi $jenis): Builder
    {
        return $jenis === null ? $query : $query->where('jenis', $jenis->value);
    }

    public function scopeRentang(Builder $query, ?string $dari, ?string $sampai): Builder
    {
        return $query
            ->when($dari, fn (Builder $q, string $d): Builder => $q->whereDate('tanggal', '>=', $d))
            ->when($sampai, fn (Builder $q, string $s): Builder => $q->whereDate('tanggal', '<=', $s));
    }
}
