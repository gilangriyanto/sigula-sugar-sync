<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisProduk;
use App\Enums\StatusPembayaran;
use Database\Factories\PenjualanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penjualan extends Model
{
    /** @use HasFactory<PenjualanFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'penjualan';

    protected $fillable = [
        'nomor_invoice',
        'tanggal',
        'eksportir_id',
        'total',
        'status_pembayaran',
        'dibayar_pada',
        'catatan',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'total' => 'float',
            'status_pembayaran' => StatusPembayaran::class,
            'dibayar_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<Eksportir, $this> */
    public function eksportir(): BelongsTo
    {
        return $this->belongsTo(Eksportir::class);
    }

    /** @return HasMany<PenjualanItem, $this> */
    public function items(): HasMany
    {
        // Urutan eksplisit: tanpa ini database bebas mengembalikan baris sesuai
        // index (brondol lebih dulu), sedangkan invoice harus konsisten.
        return $this->hasMany(PenjualanItem::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphMany<KartuStok, $this> */
    public function mutasiStok(): MorphMany
    {
        return $this->morphMany(KartuStok::class, 'referensi');
    }

    public function item(JenisProduk $jenis): ?PenjualanItem
    {
        return $this->items->firstWhere('jenis', $jenis);
    }

    public function scopeRentang(Builder $query, ?string $dari, ?string $sampai): Builder
    {
        return $query
            ->when($dari, fn (Builder $q, string $d): Builder => $q->whereDate('tanggal', '>=', $d))
            ->when($sampai, fn (Builder $q, string $s): Builder => $q->whereDate('tanggal', '<=', $s));
    }
}
