<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusPetani;
use Database\Factories\PetaniFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Petani extends Model
{
    /** @use HasFactory<PetaniFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'petani';

    protected $fillable = [
        'nama',
        'status',
        'nomor_member',
        'kontak',
        'alamat',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPetani::class,
        ];
    }

    /** @return HasMany<Pembelian, $this> */
    public function pembelian(): HasMany
    {
        return $this->hasMany(Pembelian::class);
    }

    public function scopeCari(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('nama', 'like', '%'.$term.'%')
                ->orWhere('nomor_member', 'like', '%'.$term.'%')
                ->orWhere('kontak', 'like', '%'.$term.'%');
        });
    }

    public function isMember(): bool
    {
        return $this->status === StatusPetani::MEMBER;
    }
}
