<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisTarif;
use Database\Factories\TarifUpahFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris = satu versi tarif (kristal / brondol / uang makan). */
class TarifUpah extends Model
{
    /** @use HasFactory<TarifUpahFactory> */
    use HasFactory;

    protected $table = 'tarif_upah';

    protected $fillable = [
        'jenis',
        'nilai',
        'berlaku_dari',
        'catatan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisTarif::class,
            'nilai' => 'float',
            'berlaku_dari' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeJenis(Builder $query, JenisTarif $jenis): Builder
    {
        return $query->where('jenis', $jenis->value);
    }
}
