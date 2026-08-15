<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Karyawan */
class KaryawanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'nama' => $this->nama,
            'kontak' => $this->kontak ?? '',
            'aktif' => (bool) $this->aktif,
        ];
    }
}
