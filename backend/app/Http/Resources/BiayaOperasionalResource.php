<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BiayaOperasional;
use App\Support\Periode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BiayaOperasional */
class BiayaOperasionalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tanggal' => $this->tanggal->toDateString(),
            'tanggalLabel' => Periode::tanggalIndonesia($this->tanggal),
            'keterangan' => $this->keterangan,
            'kategori' => $this->kategori->label(),
            'kategoriKode' => $this->kategori->value,
            'jumlah' => (float) $this->jumlah,
        ];
    }
}
