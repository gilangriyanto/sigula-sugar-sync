<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SesiTungku;
use App\Support\Periode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SesiTungku */
class SesiTungkuResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'tanggal' => $this->tanggal->toDateString(),
            'tanggalLabel' => Periode::tanggalIndonesia($this->tanggal),
            'kodeTungku' => $this->kode_tungku,
            'grade' => $this->grade->label(),
            'gradeKode' => $this->grade->value,
            'kgBahan' => (float) $this->kg_bahan_mentah,
            'karyawanIds' => [(string) $this->karyawan_1_id, (string) $this->karyawan_2_id],
            'karyawan' => $this->when(
                $this->relationLoaded('karyawan1') && $this->relationLoaded('karyawan2'),
                fn (): array => [
                    ['id' => (string) $this->karyawan_1_id, 'nama' => $this->karyawan1?->nama],
                    ['id' => (string) $this->karyawan_2_id, 'nama' => $this->karyawan2?->nama],
                ]
            ),
            'kgKristal' => $this->kg_kristal_total === null ? null : (float) $this->kg_kristal_total,
            'kgBrondol' => $this->kg_brondol_total === null ? null : (float) $this->kg_brondol_total,
            'rendemen' => $this->rendemen === null ? null : (float) $this->rendemen,
            'status' => $this->status->label(),
            'statusKode' => $this->status->value,
            'selesaiPada' => $this->selesai_pada?->toIso8601String(),
            'catatan' => $this->catatan,
            // Porsi hasil pembagian rata ke 2 karyawan (dipakai penggajian).
            'porsiKaryawan' => $this->whenLoaded('porsiKaryawan', fn (): array => $this->porsiKaryawan
                ->map(fn ($porsi): array => [
                    'karyawanId' => (string) $porsi->karyawan_id,
                    'kgBahan' => (float) $porsi->kg_bahan_mentah_porsi,
                    'kgKristal' => (float) $porsi->kg_kristal_porsi,
                    'kgBrondol' => (float) $porsi->kg_brondol_porsi,
                ])->all()),
        ];
    }
}
