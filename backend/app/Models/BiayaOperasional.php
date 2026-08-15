<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KategoriBiaya;
use Database\Factories\BiayaOperasionalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BiayaOperasional extends Model
{
    /** @use HasFactory<BiayaOperasionalFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'biaya_operasional';

    protected $fillable = [
        'tanggal',
        'keterangan',
        'kategori',
        'jumlah',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'kategori' => KategoriBiaya::class,
            'jumlah' => 'float',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeRentang(Builder $query, ?string $dari, ?string $sampai): Builder
    {
        return $query
            ->when($dari, fn (Builder $q, string $d): Builder => $q->whereDate('tanggal', '>=', $d))
            ->when($sampai, fn (Builder $q, string $s): Builder => $q->whereDate('tanggal', '<=', $s));
    }
}
