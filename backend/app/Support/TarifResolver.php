<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\JenisTarif;
use App\Models\TarifUpah;
use Carbon\CarbonInterface;

/**
 * Menahan seluruh histori tarif di memori supaya penggajian bisa memakai tarif
 * yang berlaku pada TANGGAL PRODUKSI tanpa query berulang per baris (N+1).
 */
final class TarifResolver
{
    /** @var array<string, array<int, array{berlaku_dari: string, nilai: float}>> */
    private array $timeline = [];

    /** @param iterable<int, TarifUpah> $tarif */
    public function __construct(iterable $tarif)
    {
        foreach ($tarif as $baris) {
            $this->timeline[$baris->jenis->value][] = [
                'berlaku_dari' => $baris->berlaku_dari->format('Y-m-d H:i:s'),
                'nilai' => (float) $baris->nilai,
            ];
        }

        foreach ($this->timeline as $jenis => $baris) {
            usort($baris, static fn (array $a, array $b): int => $a['berlaku_dari'] <=> $b['berlaku_dari']);
            $this->timeline[$jenis] = $baris;
        }
    }

    public static function muatSemua(): self
    {
        return new self(TarifUpah::query()->orderBy('berlaku_dari')->orderBy('id')->get());
    }

    /**
     * Tarif yang berlaku pada tanggal tersebut.
     *
     * Perubahan tarif di tanggal yang sama dianggap berlaku untuk hari itu juga
     * (dibandingkan terhadap akhir hari). Bila belum ada tarif sama sekali
     * sebelum tanggal itu, dipakai tarif paling awal yang tercatat, dan bila
     * tabel benar-benar kosong dipakai nilai default perusahaan.
     */
    public function nilai(JenisTarif $jenis, CarbonInterface|string $tanggal): float
    {
        $baris = $this->timeline[$jenis->value] ?? [];

        if ($baris === []) {
            return $jenis->nilaiDefault();
        }

        $batas = Periode::tanggal($tanggal)->endOfDay()->format('Y-m-d H:i:s');
        $nilai = null;

        foreach ($baris as $item) {
            if ($item['berlaku_dari'] <= $batas) {
                $nilai = $item['nilai'];

                continue;
            }

            break;
        }

        return $nilai ?? $baris[0]['nilai'];
    }
}
