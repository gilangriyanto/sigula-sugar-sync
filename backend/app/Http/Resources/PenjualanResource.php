<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\JenisProduk;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Support\Periode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Penjualan */
class PenjualanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'noInvoice' => $this->nomor_invoice,
            'tanggal' => $this->tanggal->toDateString(),
            'tanggalLabel' => Periode::tanggalIndonesia($this->tanggal),
            'eksportirId' => (string) $this->eksportir_id,
            'namaEksportir' => $this->whenLoaded('eksportir', fn (): string => $this->eksportir->nama, null),
            // Dua baris terpisah dengan kg & harga masing-masing, null bila tidak dijual.
            'kristal' => $this->baris(JenisProduk::KRISTAL),
            'brondol' => $this->baris(JenisProduk::BRONDOL),
            'total' => (float) $this->total,
            'statusPembayaran' => $this->status_pembayaran->label(),
            'statusPembayaranKode' => $this->status_pembayaran->value,
            'dibayarPada' => $this->dibayar_pada?->toIso8601String(),
            'catatan' => $this->catatan,
        ];
    }

    /** @return array{kg: float, harga: float, subtotal: float}|null */
    private function baris(JenisProduk $jenis): ?array
    {
        if (! $this->relationLoaded('items')) {
            return null;
        }

        /** @var PenjualanItem|null $item */
        $item = $this->items->firstWhere('jenis', $jenis);

        if ($item === null) {
            return null;
        }

        return [
            'kg' => (float) $item->kilogram,
            'harga' => (float) $item->harga_per_kg,
            'subtotal' => (float) $item->subtotal,
        ];
    }
}
