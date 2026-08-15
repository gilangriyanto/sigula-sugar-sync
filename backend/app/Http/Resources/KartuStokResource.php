<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\KartuStok;
use App\Support\Periode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KartuStok */
class KartuStokResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tanggal' => $this->tanggal->toDateString(),
            'tanggalLabel' => Periode::tanggalIndonesia($this->tanggal),
            'jenis' => $this->jenis->label(),
            'jenisKode' => $this->jenis->value,
            'kategori' => $this->kategori->label(),
            'kategoriKode' => $this->kategori->value,
            'kategoriLabel' => $this->kategori->labelPanjang(),
            'jumlah' => (float) $this->jumlah_kg,
            'saldoSetelah' => (float) $this->saldo_setelah,
            'keterangan' => $this->keterangan,
            'referensiTipe' => $this->referensi_type,
            'referensiId' => $this->referensi_id === null ? null : (string) $this->referensi_id,
        ];
    }
}
