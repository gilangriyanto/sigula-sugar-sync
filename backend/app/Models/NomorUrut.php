<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NomorUrut extends Model
{
    protected $table = 'nomor_urut';

    protected $fillable = [
        'kunci',
        'nilai',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'integer',
        ];
    }
}
