<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Eksportir;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Eksportir */
class EksportirResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'nama' => $this->nama,
            'kontak' => $this->kontak ?? '',
            'alamat' => $this->alamat ?? '',
            'aktif' => (bool) $this->aktif,
        ];
    }
}
