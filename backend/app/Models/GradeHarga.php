<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Grade;
use Database\Factories\GradeHargaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu versi harga beli untuk satu grade.
 * Tidak pernah di-update; perubahan harga selalu menambah baris baru.
 */
class GradeHarga extends Model
{
    /** @use HasFactory<GradeHargaFactory> */
    use HasFactory;

    protected $table = 'grade_harga';

    protected $fillable = [
        'grade',
        'harga_per_kg',
        'berlaku_dari',
        'catatan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'grade' => Grade::class,
            'harga_per_kg' => 'float',
            'berlaku_dari' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeGrade(Builder $query, Grade $grade): Builder
    {
        return $query->where('grade', $grade->value);
    }

    public function scopeBerlakuPada(Builder $query, \DateTimeInterface $waktu): Builder
    {
        return $query->where('berlaku_dari', '<=', $waktu)
            ->orderByDesc('berlaku_dari')
            ->orderByDesc('id');
    }
}
