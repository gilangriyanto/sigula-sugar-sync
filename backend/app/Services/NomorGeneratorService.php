<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NomorUrut;
use App\Support\Periode;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Generator nomor dokumen. Counter dikunci di database (bukan MAX(...)+1)
 * supaya dua request bersamaan tidak pernah menghasilkan nomor kembar.
 */
final class NomorGeneratorService
{
    /** Contoh: KW/2026/08/0001 — reset tiap bulan. */
    public function kwitansiPembelian(CarbonInterface|string $tanggal): string
    {
        $ref = Periode::tanggal($tanggal);
        $urut = $this->berikutnya(sprintf('kwitansi:%s', $ref->format('Y-m')));

        return sprintf('KW/%s/%s/%04d', $ref->format('Y'), $ref->format('m'), $urut);
    }

    /** Contoh: INV/2026/0001 — reset tiap tahun (mengikuti format prototype frontend). */
    public function invoicePenjualan(CarbonInterface|string $tanggal): string
    {
        $ref = Periode::tanggal($tanggal);
        $urut = $this->berikutnya(sprintf('invoice:%s', $ref->format('Y')));

        return sprintf('INV/%s/%04d', $ref->format('Y'), $urut);
    }

    /** Kode tungku default per hari, contoh TGK-01. */
    public function kodeTungku(CarbonInterface|string $tanggal): string
    {
        $ref = Periode::tanggal($tanggal);
        $urut = $this->berikutnya(sprintf('tungku:%s', $ref->format('Y-m-d')));

        return sprintf('TGK-%02d', $urut);
    }

    private function berikutnya(string $kunci): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Nomor dokumen harus di-generate di dalam database transaction.');
        }

        $baris = NomorUrut::query()->where('kunci', $kunci)->lockForUpdate()->first();

        if ($baris === null) {
            NomorUrut::query()->insertOrIgnore([
                'kunci' => $kunci,
                'nilai' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $baris = NomorUrut::query()->where('kunci', $kunci)->lockForUpdate()->firstOrFail();
        }

        $baris->nilai++;
        $baris->save();

        return $baris->nilai;
    }
}
